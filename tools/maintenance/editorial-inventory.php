<?php

// Solo lectura. No borra ensayos, no prepara el lote y no procesa avisos.
use App\Console\Commands\PrepareDemo;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('local') || DB::getDriverName() !== 'mysql' || DB::connection()->getDatabaseName() !== 'arat_studios') {
    throw new RuntimeException('Solo se inspecciona la instalación local conocida.');
}
DB::statement('SET TRANSACTION READ ONLY');
$report = DB::transaction(function () {
    $expected = array_keys((new ReflectionClass(PrepareDemo::class))->getConstant('SCENARIOS'));
    $requests = DB::table('service_requests')->where('demo_batch', 'arat-2026-v1')
        ->orderBy('demo_scenario')->get(['id', 'demo_scenario', 'status']);
    $actual = $requests->pluck('demo_scenario')->all();
    sort($expected);
    if ($expected !== $actual || DB::table('users')->count() !== 4) {
        throw new RuntimeException('El lote o las cuentas requieren revisión; se conservan sin modificaciones.');
    }
    $scenarios = $requests->map(fn ($request) => [
        'scenario' => $request->demo_scenario,
        'request_status' => $request->status,
        'quote_statuses' => DB::table('quotations')->where('service_request_id', $request->id)->orderBy('version')->pluck('status')->all(),
        'quote_deadlines_utc' => DB::table('quotations')->where('service_request_id', $request->id)->orderBy('version')->pluck('expires_at')->all(),
    ])->all();

    return ['date' => '2026-11-25', 'read_only' => true, 'batch_complete' => true, 'batch' => 'arat-2026-v1',
        'scenarios' => $scenarios, 'team_accounts' => 4,
        'non_batch_requests' => DB::table('service_requests')->whereNull('demo_batch')->count(),
        'records_removed' => 0,
        'expiry_note' => 'Los nombres de los escenarios identifican su preparación original. Las propuestas vencidas se conservan con sus fechas y estados actuales, sin renovarlas para la presentación.',
        'reason' => 'No hay cuentas temporales ni solicitudes adicionales identificadas como ensayos. Se conserva la solicitud de origen dudoso.'];
});
file_put_contents(base_path('docs/editorial-20261125/data-inventory.json'), json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL);
echo json_encode(['batch_complete' => true, 'scenarios' => count($report['scenarios']), 'records_removed' => 0]).PHP_EOL;
