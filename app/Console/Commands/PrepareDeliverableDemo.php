<?php

namespace App\Console\Commands;

use App\Models\ClientAccessToken;
use App\Models\Deliverable;
use App\Models\Quotation;
use App\Models\User;
use App\Services\ClientAccessService;
use App\Services\DeliverableWorkflowService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Str;

class PrepareDeliverableDemo extends Command
{
    protected $signature = 'demo:deliverables {--apply : Preparar los tres casos sin reiniciar datos} {--actor= : Cuenta interna autorizada; por defecto un administrador activo}';
    protected $description = 'Plan y preparación idempotente de tres entregables académicos sobre una cotización Faker aceptada.';

    private const CASES = [
        '00000000-0000-4000-8000-000000000101' => ['pendiente', 'Propuesta de distribución · demostración académica'],
        '00000000-0000-4000-8000-000000000102' => ['revision', 'Planos arquitectónicos · demostración académica'],
        '00000000-0000-4000-8000-000000000103' => ['conforme', 'Informe de diseño · demostración académica'],
    ];

    public function handle(DeliverableWorkflowService $workflow, ClientAccessService $access): int
    {
        $this->table(['Caso', 'Resultado previsto'], [['Pendiente', 'Versión 1 enviada'], ['Revisión', 'Versión 1 observada y versión 2 enviada'], ['Conforme', 'Versión 1 conforme y descargable']]);
        if (! $this->option('apply')) {
            $this->info('Plan sin escrituras. Usa --apply en local y mantenimiento. No se envían correos ni se modifican cotizaciones.');
            return self::SUCCESS;
        }
        if (! app()->environment('local', 'testing') || (! app()->environment('testing') && ! app()->isDownForMaintenance())) {
            $this->error('La preparación requiere entorno local en mantenimiento o una base aislada de pruebas.');
            return self::FAILURE;
        }
        $actor = $this->option('actor') ? User::findOrFail($this->option('actor')) : User::where('is_active', true)->where('role', 'admin')->firstOrFail();
        $quote = Quotation::where('status', 'aceptada')->whereHas('serviceRequest', fn ($q) => $q->where('demo_batch', 'arat-2026-v1'))
            ->whereHas('serviceRequest', fn ($q) => $actor->role === 'admin' ? $q : $q->where('assigned_to', $actor->id))
            ->orderBy('id')->first();
        if (! $quote) {
            $this->error('No hay cotización aceptada del lote Faker en este ámbito. Se conservan los datos; prepara una aceptación de demostración antes de continuar.');
            return self::FAILURE;
        }
        foreach (self::CASES as $key => [$case, $title]) {
            $data = ['operation_key' => $key, 'quotation_code' => $quote->code, 'title' => $title,
                'description' => 'Documento ficticio para la revisión del servicio en la tercera presentación.',
                'internal_note' => 'Preparación académica; esta nota no se publica en el expediente del cliente.'];
            $d = Deliverable::where('creation_key', $key)->first();
            if (! $d) $d = $this->withPdf($title, 1, $data['description'], fn ($file) => $workflow->create($quote->serviceRequest, $actor, $data, $file));
            abort_unless($d->quotation_id === $quote->id, 409, 'El escenario existente pertenece a otra cotización; se conserva sin cambios.');
            $version = $d->latestVersion;
            if ($d->status === 'borrador') $workflow->send($d, $actor, $version->number, $d->lock_version);
            $d->refresh();
            if ($case === 'revision' && $d->versions()->count() === 1 && $d->status === 'en_revision') {
                $workflow->respond($d, $this->grant($quote, $access), 1, ['decision' => 'observado',
                    'comment' => 'Aclara las anotaciones y las medidas de referencia en la segunda versión académica.', 'lock_version' => $d->lock_version]);
                $d->refresh();
            }
            if ($case === 'revision' && $d->status === 'observado' && $d->versions()->count() === 1) {
                $data['operation_key'] = '00000000-0000-4000-8000-000000000202';
                $data['lock_version'] = $d->lock_version;
                $this->withPdf($title, 2, $data['description'], fn ($file) => $workflow->saveDraft($d, $actor, $data, $file));
                $d->refresh();
            }
            if ($case === 'revision' && $d->status === 'observado' && $d->latestVersion->number === 2
                && $d->latestVersion->save_key === '00000000-0000-4000-8000-000000000202' && ! $d->latestVersion->sent_at) {
                $workflow->send($d, $actor, $d->latestVersion->number, $d->lock_version);
                $d->refresh();
            }
            if ($case === 'conforme' && $d->status === 'en_revision') {
                $workflow->respond($d, $this->grant($quote, $access), 1, ['decision' => 'conforme', 'confirmation' => true, 'lock_version' => $d->lock_version]);
                $d->refresh();
            }
            $this->line($title.' · '.$d->code.' · '.$d->status.' · versión '.$d->latestVersion->number);
        }
        $this->info('Escenarios disponibles. Las solicitudes, cotizaciones y archivos anteriores se conservaron; no se procesaron avisos.');
        return self::SUCCESS;
    }

    private function withPdf(string $title, int $number, string $description, callable $action)
    {
        $bytes = Pdf::loadView('deliverables.demo-pdf', compact('title', 'number', 'description'))
            ->setOptions(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false, 'defaultFont' => 'DejaVu Sans'])
            ->setPaper('a4')->output();
        $path = tempnam(storage_path('framework'), 'delivery-demo-');
        try {
            file_put_contents($path, $bytes);
            return $action(new UploadedFile($path, 'demostracion-academica-v'.$number.'.pdf', 'application/pdf', null, true));
        } finally { if (is_file($path)) unlink($path); }
    }

    private function grant(Quotation $quote, ClientAccessService $access)
    {
        $token = ClientAccessToken::create(['service_request_id' => $quote->service_request_id,
            'email' => $quote->serviceRequest->client_email, 'purpose' => 'access',
            'token_hash' => hash('sha256', Str::random(64)), 'expires_at' => now('UTC')->addMinutes(15)]);
        $url = $access->temporaryLink($token);
        $raw = basename(parse_url($url, PHP_URL_PATH));
        $request = Request::create('/acceso-expediente/'.$raw, 'POST');
        $request->setLaravelSession(new Store('deliverable-demo', new ArraySessionHandler(30)));
        $request->session()->start();
        $access->consume($request, $raw);

        return $access->grant($request);
    }
}
