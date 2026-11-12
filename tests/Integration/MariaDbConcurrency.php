<?php

// Ejecutar explícitamente: php tests/Integration/MariaDbConcurrency.php --run
// Crea una base NUEVA arat_concurrency_*. Nunca reinicia ni borra arat_studios.
use App\Models\ClientGrant;
use App\Models\Quotation;
use App\Models\ServiceRequest;
use App\Models\TariffGroup;
use App\Models\TariffParam;
use App\Models\User;
use App\Services\CatalogWorkflowService;
use App\Services\ClientResponseService;
use App\Services\CommercialWorkflowService;
use App\Services\IssueQuotationService;
use App\Services\QuotationPdfService;
use App\Services\ServiceRequestService;
use App\Services\WorkProcessor;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Process;
use Tests\Support\CommercialFixtures;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function isolated(string $database): void
{
    if (! preg_match('/^arat_concurrency_[0-9_]+$/', $database)) {
        throw new RuntimeException('Solo bases aisladas arat_concurrency_*.');
    }
    config(['database.connections.mysql.database' => $database, 'database.default' => 'mysql', 'filesystems.disks.commercial.root' => storage_path('app/private/isolated/'.$database)]);
    DB::purge('mysql');
    DB::setDefaultConnection('mysql');
    DB::statement('SET SESSION innodb_lock_wait_timeout = 3');
}
function requireTrue(bool $value, string $message): void
{
    if (! $value) {
        throw new RuntimeException($message);
    }
}
function waitFile(string $path): void
{
    $until = microtime(true) + 15;
    while (! is_file($path)) {
        if (microtime(true) > $until) {
            throw new RuntimeException('Tiempo agotado esperando barrera.');
        }usleep(20000);
    }
}
function worker(array $data): Process
{
    $p = new Process([PHP_BINARY, __FILE__, '--worker']);
    $p->setInput(json_encode($data));
    $p->setTimeout(30);
    $p->start();

    return $p;
}
function result(Process $p): array
{
    $p->wait();
    $r = json_decode($p->getOutput(), true);
    requireTrue(is_array($r), 'Salida de worker inválida.');

    return $r;
}
class FixtureAccess
{
    use CommercialFixtures;

    public function call(string $method, ...$args)
    {
        return $this->$method(...$args);
    }
}

if (($argv[1] ?? '') === '--worker') {
    try {
        $data = json_decode(stream_get_contents(STDIN), true);
        isolated($data['database']);
        if (isset($data['start_at'])) {
            while (microtime(true) < $data['start_at']) {
                usleep(10000);
            }
        }
        if (isset($data['wait_for'])) {
            waitFile($data['wait_for']);
        }
        $actor = isset($data['actor']) ? User::findOrFail($data['actor']) : null;
        switch ($data['action']) {
            case 'register':$resource = app(ServiceRequestService::class)->register($data['fields'], null)[0]->id;
                break;
            case 'claim':app(CommercialWorkflowService::class)->transition(ServiceRequest::findOrFail($data['request']), $actor, ['action' => 'claim', 'lock_version' => $data['version'], 'reason' => 'Toma concurrente aislada']);
                $resource = $data['request'];
                break;
            case 'draft':$resource = app(IssueQuotationService::class)->draft(ServiceRequest::findOrFail($data['request']), $actor, $data['fields'])->id;
                break;
            case 'activate':$t = TariffParam::findOrFail($data['tariff']);
                app(CatalogWorkflowService::class)->status($t, true, $data['version'], 'Activación concurrente aislada', $actor, $data['expected_active_id'], true);
                $resource = $t->id;
                break;
            case 'accept':$q = Quotation::findOrFail($data['quotation']);
                app(ClientResponseService::class)->respond($q, ClientGrant::findOrFail($data['grant']), ['decision' => 'aceptada', 'lock_version' => $data['version']]);
                $resource = $q->id;
                break;
            case 'generate':
                $sync = $data['sync'];
                app()->bind(QuotationPdfService::class, fn () => new class($sync) extends QuotationPdfService
                {
                    public function __construct(private string $sync) {}

                    public function render(Quotation $q): string
                    {
                        requireTrue(DB::transactionLevel() === 0, 'El PDF está manteniendo una transacción.');
                        file_put_contents($this->sync.'/render-started', '1');
                        waitFile($this->sync.'/allow-render');

                        return parent::render($q);
                    }
                });
                app(WorkProcessor::class)->run($data['intent']);
                file_put_contents($sync.'/emitted', '1');
                $resource = $data['intent'];
                break;
            default:throw new LogicException('Acción inválida.');
        }
        echo json_encode(['status' => 'ok', 'resource' => $resource]);
    } catch (HttpException $e) {
        echo json_encode(['status' => 'conflict', 'code' => $e->getStatusCode()]);
    } catch (Throwable $e) {
        echo json_encode(['status' => 'error', 'class' => get_class($e)]);
    }
    exit;
}
if (($argv[1] ?? '') !== '--run') {
    echo "Usar --run; no se modificó ninguna base.\n";
    exit(1);
}

$source = DB::connection()->getDatabaseName();
requireTrue($source === 'arat_studios', 'La conexión de origen no es la esperada.');
$database = 'arat_concurrency_'.gmdate('Ymd_His');
DB::statement('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
isolated($database);
Artisan::call('migrate', ['--force' => true]);
$fixtures = new FixtureAccess;
$fixtures->call('tariff');
$a = User::factory()->create(['role' => 'gestor', 'email' => 'gestor-a@example.test', 'password' => Str::random(48)]);
$b = User::factory()->create(['role' => 'gestor', 'email' => 'gestor-b@example.test', 'password' => Str::random(48)]);
$admin = User::factory()->create(['role' => 'admin', 'email' => 'admin-aislado@example.test', 'password' => Str::random(48)]);
$results = [];

$r = $fixtures->call('registerRequest');
$at = microtime(true) + 1;
$p1 = worker(['database' => $database, 'action' => 'claim', 'actor' => $a->id, 'request' => $r->id, 'version' => 0, 'start_at' => $at]);
$p2 = worker(['database' => $database, 'action' => 'claim', 'actor' => $b->id, 'request' => $r->id, 'version' => 0, 'start_at' => $at]);
$claims = [result($p1), result($p2)];
requireTrue(count(array_filter($claims, fn ($v) => $v['status'] === 'ok')) === 1, 'Toma doble o ninguna toma.');
requireTrue(count(array_filter($claims, fn ($v) => ($v['code'] ?? null) === 409)) === 1, 'No se informó conflicto de toma.');
$results['atomic_claim'] = $claims;
$owner = User::findOrFail($r->fresh()->assigned_to);
$fixtures->call('action', $r, $owner, 'start');
$fixtures->call('action', $r, $owner, 'approve', ['assessment' => 'Evaluación técnica de prueba concurrente.']);
$fields = $fixtures->call('quoteData', $r);
$at = microtime(true) + 1;
$base = ['database' => $database, 'action' => 'draft', 'actor' => $owner->id, 'request' => $r->id, 'fields' => $fields, 'start_at' => $at];
$p1 = worker($base);
$p2 = worker($base);
$drafts = [result($p1), result($p2)];
requireTrue($drafts[0]['status'] === 'ok' && $drafts[1]['status'] === 'ok' && $drafts[0]['resource'] === $drafts[1]['resource'], 'Borradores concurrentes no fueron idempotentes.');
requireTrue($r->quotations()->count() === 1, 'Se duplicó un borrador.');
$results['idempotent_draft'] = $drafts;

foreach (['identical', 'different'] as $case) {
    $payload = $fixtures->call('requestData');
    $other = $payload;
    if ($case === 'different') {
        $other['city'] = 'Cusco';
    }$at = microtime(true) + 1;
    $p1 = worker(['database' => $database, 'action' => 'register', 'fields' => $payload, 'start_at' => $at]);
    $p2 = worker(['database' => $database, 'action' => 'register', 'fields' => $other, 'start_at' => $at]);
    $registered = [result($p1), result($p2)];
    requireTrue(ServiceRequest::where('submission_token', $payload['submission_token'])->count() === 1, 'Registro simultáneo duplicado.');
    if ($case === 'identical') {
        requireTrue(array_column($registered, 'status') === ['ok', 'ok'] && $registered[0]['resource'] === $registered[1]['resource'], 'El mismo envío no recuperó el mismo resultado.');
    } else {
        requireTrue(count(array_filter($registered, fn ($v) => ($v['code'] ?? null) === 409)) === 1 && count(array_filter($registered, fn ($v) => $v['status'] === 'ok')) === 1, 'No se informó conflicto de contenido diferente.');
    }
    $results['submission_'.$case] = $registered;
}

foreach (['accept_first', 'emit_first'] as $ordering) {
    $r = $fixtures->call('registerRequest');
    $fixtures->call('approve', $r, $a);
    $v1 = $fixtures->call('emit', $fixtures->call('draft', $r, $a), $a);
    [$grant] = $fixtures->call('clientGrant', $r);
    $v2 = $fixtures->call('draft', $r, $a, '12500', $v1);
    $intent = app(IssueQuotationService::class)->prepare($v2, $a, $v2->lock_version);
    $sync = storage_path('framework/testing/'.$database.'_'.$ordering);
    mkdir($sync, 0700, true);
    $pdf = worker(['database' => $database, 'action' => 'generate', 'intent' => $intent->id, 'sync' => $sync]);
    waitFile($sync.'/render-started');
    $acceptData = ['database' => $database, 'action' => 'accept', 'quotation' => $v1->id, 'grant' => $grant->id, 'version' => $v1->lock_version];
    if ($ordering === 'emit_first') {
        $acceptData['wait_for'] = $sync.'/emitted';
    }
    $accept = worker($acceptData);
    if ($ordering === 'accept_first') {
        $acceptResult = result($accept);
        requireTrue($acceptResult['status'] === 'ok', 'No pudo aceptar durante el render fuera de transacción.');
        file_put_contents($sync.'/allow-render', '1');
        $pdfResult = result($pdf);
        requireTrue($v1->fresh()->status === 'aceptada' && $v2->fresh()->status === 'anulada' && $r->fresh()->status === 'cerrada', 'Emisión incompatible después de aceptación.');
    } else {
        file_put_contents($sync.'/allow-render', '1');
        $pdfResult = result($pdf);
        $acceptResult = result($accept);
        requireTrue(($acceptResult['code'] ?? null) === 409 && $v2->fresh()->status === 'emitida' && $v1->fresh()->status === 'sustituida', 'Aceptación de versión sustituida.');
    }
    requireTrue($pdfResult['status'] === 'ok', 'Falló el worker PDF.');
    $results[$ordering] = ['accept' => $acceptResult, 'pdf' => $pdfResult, 'v1' => $v1->fresh()->status, 'v2' => $v2->fresh()->status];
}
$catalog = app(CatalogWorkflowService::class);
$new = [];
foreach (['110', '120'] as $price) {
    $new[] = $catalog->save('tariffs', null, ['lod_level' => 'LOD_300', 'base_price_m2' => $price, 'lod_factor' => '1.35', 'max_area_m2' => '500', 'reason' => 'Nueva versión para concurrencia'], null, $admin);
}
$at = microtime(true) + 1;
$expected = TariffGroup::where('lod_level', 'LOD_300')->value('active_tariff_id');
$p1 = worker(['database' => $database, 'action' => 'activate', 'actor' => $admin->id, 'tariff' => $new[0]->id, 'version' => 0, 'expected_active_id' => $expected, 'start_at' => $at]);
$p2 = worker(['database' => $database, 'action' => 'activate', 'actor' => $admin->id, 'tariff' => $new[1]->id, 'version' => 0, 'expected_active_id' => $expected, 'start_at' => $at]);
$activated = [result($p1), result($p2)];
requireTrue(count(array_filter($activated, fn ($v) => $v['status'] === 'ok')) === 1 && count(array_filter($activated, fn ($v) => ($v['code'] ?? null) === 409)) === 1, 'La vista tarifaria obsoleta no produjo conflicto.');
$group = TariffGroup::where('lod_level', 'LOD_300')->first();
requireTrue(in_array($group->active_tariff_id, array_map(fn ($tariff) => $tariff->id, $new), true), 'La vigencia no apunta a una de las versiones activadas.');
requireTrue($group->versions()->where('status', 'activa')->count() === 1 && $group->activeTariff->tariff_group_id === $group->id, 'Vigencias tarifarias incompatibles.');
$results['atomic_tariff_switch'] = $activated;
file_put_contents(storage_path('framework/testing/isolated-run.json'), json_encode(['database' => $database, 'results' => $results], JSON_PRETTY_PRINT));
echo json_encode(['database' => $database, 'checks' => array_keys($results), 'status' => 'passed'], JSON_PRETTY_PRINT).PHP_EOL;
