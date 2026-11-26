<?php

use App\Models\ClientGrant;
use App\Models\Deliverable;
use App\Models\DeliverableVersion;
use App\Models\User;
use App\Services\ClientResponseService;
use App\Services\DeliverableWorkflowService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Process\Process;
use Tests\Support\CommercialFixtures;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

function deliveryDatabase(string $name): void
{
    if (! preg_match('/^arat_delivery_concurrency_[0-9_]+$/', $name)) throw new RuntimeException('Solo se permiten bases aisladas de entregables.');
    config(['database.connections.mysql.database' => $name, 'database.connections.mysql.url' => null, 'database.default' => 'mysql',
        'filesystems.disks.commercial.root' => storage_path('app/private/isolated/'.$name), 'mail.default' => 'array']);
    DB::purge('mysql');
    DB::setDefaultConnection('mysql');
    DB::statement('SET SESSION innodb_lock_wait_timeout = 5');
}
function deliveryCheck(bool $condition, string $message): void
{
    if (! $condition) throw new RuntimeException($message);
}
if (($argv[1] ?? '') === '--worker') {
    try {
        $data = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
        deliveryDatabase($data['database']);
        while (microtime(true) < $data['at']) usleep(10000);
        $workflow = app(DeliverableWorkflowService::class);
        $actor = User::findOrFail($data['actor']);
        $file = isset($data['file']) ? new UploadedFile($data['file'], 'ensayo.png', 'image/png', null, true) : null;
        $result = match ($data['action']) {
            'create' => $workflow->create(App\Models\ServiceRequest::findOrFail($data['request']), $actor, $data['fields'], $file),
            'save' => $workflow->saveDraft(Deliverable::findOrFail($data['deliverable']), $actor, $data['fields'], $file),
            'send' => $workflow->send(Deliverable::findOrFail($data['deliverable']), $actor, $data['number'], $data['expected']),
            'respond' => $workflow->respond(Deliverable::findOrFail($data['deliverable']), ClientGrant::findOrFail($data['grant']), $data['number'], $data['fields']),
        };
        echo json_encode(['status' => 'ok', 'id' => $result->id]);
    } catch (HttpExceptionInterface $error) {
        echo json_encode(['status' => 'conflict', 'code' => $error->getStatusCode()]);
    } catch (Throwable $error) {
        fwrite(STDERR, get_class($error).': '.$error->getMessage());
        echo json_encode(['status' => 'error']);
    }
    exit;
}
if (($argv[1] ?? '') !== '--run') { echo 'Usa --run para una base nueva y aislada.'.PHP_EOL; exit(1); }
deliveryCheck(app()->environment('local') && DB::connection()->getDatabaseName() === 'arat_studios', 'Origen local inesperado.');
$database = 'arat_delivery_concurrency_'.gmdate('Ymd_His');
DB::statement('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
deliveryDatabase($database);
Artisan::call('migrate', ['--force' => true]);
$fixtures = new class {
    use CommercialFixtures;
    public function call(string $method, ...$args) { return $this->$method(...$args); }
};
$fixtures->call('tariff');
$owner = User::factory()->create(['role' => 'gestor', 'email' => 'gestor-documental@example.test']);
$request = $fixtures->call('registerRequest');
$fixtures->call('approve', $request, $owner);
$quote = $fixtures->call('emit', $fixtures->call('draft', $request, $owner), $owner);
[$grant] = $fixtures->call('clientGrant', $request);
app(ClientResponseService::class)->respond($quote, $grant, ['decision' => 'aceptada', 'lock_version' => $quote->lock_version]);
$requestBefore = $request->fresh()->getRawOriginal();
$quoteBefore = $quote->fresh()->getRawOriginal();
$directory = storage_path('app/private/isolated/'.$database);
if (! is_dir($directory)) mkdir($directory, 0700, true);
$png = $directory.'/input.png';
file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
$base = ['database' => $database, 'actor' => $owner->id];
$fields = ['title' => 'Ensayo documental aislado', 'description' => 'Demostración académica de concurrencia.', 'operation_key' => (string) Str::uuid(), 'quotation_code' => $quote->code];
$race = function (array $one, array $two) use ($base): array {
    $at = microtime(true) + .6;
    $processes = [];
    foreach ([$one, $two] as $data) {
        $p = new Process([PHP_BINARY, __FILE__, '--worker']);
        $p->setInput(json_encode($data + $base + ['at' => $at]))->setTimeout(40)->start();
        $processes[] = $p;
    }
    return array_map(function ($p) {
        $p->wait();
        $result = json_decode($p->getOutput(), true);
        deliveryCheck(is_array($result) && $result['status'] !== 'error', 'Falló un worker: '.$p->getErrorOutput());
        return $result;
    }, $processes);
};
$results = [];
$create = ['action' => 'create', 'request' => $request->id, 'fields' => $fields, 'file' => $png];
$results['identical_create'] = $race($create, $create);
deliveryCheck($results['identical_create'][0]['id'] === $results['identical_create'][1]['id'] && Deliverable::count() === 1, 'Se duplicó el entregable.');
$d = Deliverable::firstOrFail();
$send = ['action' => 'send', 'deliverable' => $d->id, 'number' => 1, 'expected' => 0];
$results['identical_send'] = $race($send, $send);
deliveryCheck($d->fresh()->status === 'en_revision' && $d->fresh()->lock_version === 1, 'El envío repetido modificó dos veces el estado.');
$respond = ['action' => 'respond', 'deliverable' => $d->id, 'number' => 1, 'grant' => $grant->id];
$results['opposite_responses'] = $race($respond + ['fields' => ['decision' => 'conforme', 'confirmation' => true, 'lock_version' => 1]],
    $respond + ['fields' => ['decision' => 'observado', 'comment' => 'Observación del ensayo concurrente.', 'lock_version' => 1]]);
deliveryCheck(count(array_filter($results['opposite_responses'], fn ($r) => $r['status'] === 'ok')) === 1
    && count(array_filter($results['opposite_responses'], fn ($r) => ($r['code'] ?? null) === 409)) === 1, 'Se registraron respuestas incompatibles.');

$flow = app(DeliverableWorkflowService::class);
$observed = function () use ($flow, $request, $owner, $fields, $png, $grant) {
    $data = array_replace($fields, ['operation_key' => (string) Str::uuid()]);
    $d = $flow->create($request, $owner, $data, new UploadedFile($png, 'ensayo.png', 'image/png', null, true));
    $flow->send($d, $owner, 1, 0);
    $flow->respond($d, $grant, 1, ['decision' => 'observado', 'comment' => 'Revisión académica de la primera versión.', 'lock_version' => 1]);
    return $d->fresh();
};
$d = $observed();
$save = ['action' => 'save', 'deliverable' => $d->id, 'file' => $png, 'fields' => array_replace($fields, ['operation_key' => (string) Str::uuid(), 'lock_version' => $d->lock_version])];
$results['identical_version'] = $race($save, $save);
deliveryCheck(($results['identical_version'][0]['id'] ?? null) !== null && ($results['identical_version'][0]['id'] ?? null) === ($results['identical_version'][1]['id'] ?? null)
    && $d->versions()->count() === 2, 'La versión repetida no fue idempotente: '.json_encode($results['identical_version']));
$d = $observed();
$save['deliverable'] = $d->id;
$save['fields']['operation_key'] = (string) Str::uuid();
$different = $save; $different['fields']['operation_key'] = (string) Str::uuid();
$results['competing_versions'] = $race($save, $different);
deliveryCheck(count(array_filter($results['competing_versions'], fn ($r) => $r['status'] === 'ok')) === 1 && $d->versions()->count() === 2, 'Se crearon versiones incompatibles.');
$d = $flow->create($request, $owner, array_replace($fields, ['operation_key' => (string) Str::uuid()]), new UploadedFile($png, 'ensayo.png', 'image/png', null, true));
$flow->send($d, $owner, 1, 0);
$conform = ['action' => 'respond', 'deliverable' => $d->id, 'number' => 1, 'grant' => $grant->id,
    'fields' => ['decision' => 'conforme', 'confirmation' => true, 'lock_version' => 1]];
$newVersion = ['action' => 'save', 'deliverable' => $d->id, 'file' => $png,
    'fields' => array_replace($fields, ['operation_key' => (string) Str::uuid(), 'lock_version' => 1])];
$results['conformity_vs_new_version'] = $race($conform, $newVersion);
deliveryCheck($results['conformity_vs_new_version'][0]['status'] === 'ok' && ($results['conformity_vs_new_version'][1]['code'] ?? null) === 409
    && $d->fresh()->status === 'conforme' && $d->versions()->count() === 1, 'Se modificó un entregable conforme.');
deliveryCheck($requestBefore === $request->fresh()->getRawOriginal() && $quoteBefore === $quote->fresh()->getRawOriginal(), 'Se modificó el cierre comercial o su cotización.');
$report = ['database' => $database, 'status' => 'passed', 'checks' => $results, 'commercial_closure_unchanged' => true,
    'versions' => DeliverableVersion::count()];
if (! is_dir(base_path('docs/tercera-presentacion'))) mkdir(base_path('docs/tercera-presentacion'), 0755, true);
file_put_contents(base_path('docs/tercera-presentacion/concurrency.json'), json_encode($report, JSON_PRETTY_PRINT));
echo json_encode(['status' => 'passed', 'checks' => array_keys($results), 'database' => $database]).PHP_EOL;
