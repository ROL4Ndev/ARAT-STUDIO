<?php

use App\Models\User;
use App\Services\ClientResponseService;
use App\Services\ServiceRequestService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\CommercialFixtures;

$app = require __DIR__.'/bootstrap.php';
$app->make(Kernel::class)->bootstrap();
if (DB::getDriverName() !== 'sqlite' || ! str_ends_with(str_replace('\\', '/', DB::connection()->getDatabaseName()), '/design-preview-final/demo.sqlite')) {
    throw new RuntimeException('Solo se preparan sesiones ficticias en SQLite de vista previa.');
}
$stateFile = storage_path('document-state.json');
$title = 'Propuesta de distribución · demostración académica';
$description = 'Archivo académico para la revisión en navegador.';
foreach ([1, 2] as $number) {
    file_put_contents(storage_path('document-example-v'.$number.'.pdf'), \Barryvdh\DomPDF\Facade\Pdf::loadView('deliverables.demo-pdf', compact('title', 'number', 'description'))
        ->setOptions(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false, 'defaultFont' => 'DejaVu Sans'])->setPaper('a4')->output());
}
if (is_file($stateFile) && ($argv[1] ?? '') !== '--new-flow') { echo 'Escenario documental aislado existente; se conserva.'.PHP_EOL; exit; }
$fixtures = new class {
    use CommercialFixtures;
    public function call(string $method, ...$args) { return $this->$method(...$args); }
};
$password = Str::random(36).'1aA!';
$manager = User::firstOrNew(['email' => 'gestor-documental@example.test']);
$manager->forceFill(['name' => 'Gestor de demostración documental', 'email' => 'gestor-documental@example.test',
    'password' => $password, 'role' => 'gestor', 'is_active' => true])->save();
$other = User::firstOrNew(['email' => 'gestor-ajeno-documental@example.test']);
$other->forceFill(['name' => 'Otro gestor de demostración', 'email' => 'gestor-ajeno-documental@example.test',
    'password' => $password, 'role' => 'gestor', 'is_active' => true])->save();
$data = $fixtures->call('requestData');
$data = array_replace($data, ['client_name' => 'Cliente académico de documentos', 'client_email' => 'revision-documental@example.com',
    'client_phone' => '000000000', 'description' => 'Solicitud académica aislada para demostrar la entrega de documentos y su revisión.']);
$request = app(ServiceRequestService::class)->register($data, null)[0];
$fixtures->call('approve', $request, $manager);
$quote = $fixtures->call('emit', $fixtures->call('draft', $request, $manager), $manager);
[$grant] = $fixtures->call('clientGrant', $request);
app(ClientResponseService::class)->respond($quote, $grant, ['decision' => 'aceptada', 'lock_version' => $quote->lock_version]);
file_put_contents($stateFile, json_encode(['request' => $request->code, 'quotation' => $quote->code,
    'manager_email' => $manager->email, 'other_email' => $other->email, 'password' => $password]));
echo 'Escenario y gestores ficticios preparados solo en SQLite. No se envió correo real.'.PHP_EOL;
