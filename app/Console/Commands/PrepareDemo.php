<?php

namespace App\Console\Commands;

use App\Models\ClientAccessToken;
use App\Models\ClientGrant;
use App\Models\NotificationRecord;
use App\Models\OfferedService;
use App\Models\Quotation;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Models\WorkIntent;
use App\Services\ClientAccessService;
use App\Services\ClientResponseService;
use App\Services\ClientRevisionService;
use App\Services\CommercialWorkflowService;
use App\Services\IssueQuotationService;
use App\Services\QuotationCalculatorService;
use App\Services\ServiceRequestService;
use App\Services\WorkProcessor;
use Carbon\CarbonImmutable;
use Database\Factories\DemoRequestFactory;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PrepareDemo extends Command
{
    protected $signature = 'demo:prepare {--batch=arat-2026-v1} {--seed=20261124} {--date= : Fecha de referencia YYYY-MM-DD} {--actor= : Cuenta interna activa para los escenarios} {--apply : Crear el lote; sin esta opción solo muestra el plan}';

    protected $description = 'Prepara 15 solicitudes ficticias mediante el flujo comercial, sin envíos externos ni duplicados.';

    private array $generatedFiles = [];

    private const SCENARIOS = [
        'pendiente' => ['INTERIORES', '60', 'LOD_200', 'Reorganización de sala y comedor para aprovechar la iluminación natural y conservar los muebles principales.'],
        'asignada' => ['ARQUITECTURA', '120', 'LOD_300', 'Propuesta de distribución para una vivienda de dos niveles, con estudio y patio interior.'],
        'evaluacion' => ['BIM', '95', 'LOD_300', 'Modelo de una vivienda existente a partir de planos medidos para coordinar arquitectura y documentación.'],
        'observada' => ['INTERIORES', '45', 'LOD_200', 'Diseño de dormitorio y zona de trabajo; faltan las medidas de ventanas para definir el mobiliario.'],
        'corregida' => ['BIM', '80', 'LOD_300', 'Coordinación del modelo arquitectónico de un departamento con alturas y distribución pendientes de precisar.'],
        'borrador' => ['ARQUITECTURA', '150', 'LOD_300', 'Anteproyecto de vivienda familiar con tres dormitorios, comedor integrado y una terraza accesible.'],
        'emitida' => ['INTERIORES', '72', 'LOD_300', 'Diseño interior de departamento: sala, comedor y dormitorio principal con selección de materiales.'],
        'sustituida' => ['VISUALIZACION', '90', 'LOD_200', 'Representaciones interiores de un departamento para revisar iluminación, materiales y mobiliario.'],
        'vencida' => ['ARQUITECTURA', '110', 'LOD_200', 'Estudio de distribución para ampliar una vivienda manteniendo el patio y la ventilación cruzada.'],
        'aceptada' => ['BIM', '85', 'LOD_300', 'Modelo arquitectónico y planos de un departamento, con coordinación de puertas, ventanas y niveles.'],
        'rechazada' => ['ARQUITECTURA', '180', 'LOD_300', 'Evaluación de una ampliación de vivienda sin información suficiente sobre la estructura existente.'],
        'cancelada' => ['INTERIORES', '55', 'LOD_200', 'Diseño de sala y estudio que se posterga por cambio de fecha de mudanza de la familia.'],
        'propuesta-rechazada' => ['VISUALIZACION', '65', 'LOD_200', 'Imágenes interiores de sala y cocina para comparar dos propuestas de materiales y luz natural.'],
        'cerrada' => ['BIM', '100', 'LOD_300', 'Modelo de coordinación de una vivienda para revisar el alcance documental antes de contratar.'],
        'pendiente-planos' => ['ARQUITECTURA', '200', 'LOD_400', 'Revisión de una vivienda multifamiliar con planos por nivel y propuesta de documentación detallada.'],
    ];

    public function handle(): int
    {
        $batch = (string) $this->option('batch');
        if (! app()->environment('local', 'testing') || ! preg_match('/^[a-z0-9][a-z0-9-]{2,59}$/', $batch)) {
            $this->error('Solo se permite un lote identificado en desarrollo local o pruebas.');

            return self::FAILURE;
        }
        $this->table(['Escenario', 'Servicio', 'Área', 'LOD'], collect(self::SCENARIOS)->map(fn ($s, $key) => [$key, $s[0], $s[1], $s[2]])->all());
        if (! $this->option('apply')) {
            $this->info('Plan: 15 solicitudes ficticias; correos example.com, teléfono no enrutable y transporte de correo array.');

            return self::SUCCESS;
        }
        if (! app()->environment('testing') && ! app()->isDownForMaintenance()) {
            $this->error('Activa mantenimiento y detén cola/scheduler antes de preparar el lote.');

            return self::FAILURE;
        }
        return Cache::lock('demo:prepare', 300)->block(5, function () use ($batch) {
            $existing = ServiceRequest::where('demo_batch', $batch)->pluck('demo_scenario')->sort()->values()->all();
            $expected = array_keys(self::SCENARIOS);
            sort($expected);
            if ($existing) {
                if ($existing !== $expected) {
                    $this->error('El lote está incompleto; se conserva para revisión y no se sobrescribe.');

                    return self::FAILURE;
                }
                $this->info('El lote completo ya existe: no se crean duplicados ni se modifican sus estados.');

                return self::SUCCESS;
            }
            $actor = User::whereKey($this->option('actor'))->where('is_active', true)->whereIn('role', ['admin', 'gestor'])->firstOrFail();
            $clock = $this->option('date') ? CarbonImmutable::createFromFormat('!Y-m-d', $this->option('date'), 'America/Lima')->setTime(12, 0)->utc() : CarbonImmutable::now('UTC');
            $mail = config('mail.default');
            $date = Date::getTestNow();
            $this->generatedFiles = [];
            config(['mail.default' => 'array']);
            Mail::purge();
            fake()->seed((int) $this->option('seed'));
            try {
                DB::transaction(function () use ($batch, $actor, $clock) {
                    foreach (self::SCENARIOS as $scenario => $specification) {
                        $index = array_search($scenario, array_keys(self::SCENARIOS), true);
                        Date::setTestNow($clock->subDays(2 * ($index + 1)));
                        $firstNotice = NotificationRecord::max('id') ?? 0;
                        $this->scenario($batch, $scenario, $specification, $actor);
                        foreach (WorkIntent::where('kind', 'notification')->whereIn('resource_id', NotificationRecord::where('id', '>', $firstNotice)->select('id'))->get() as $work) {
                            app(WorkProcessor::class)->run($work->id);
                            if (! in_array($work->fresh()->status, ['done', 'cancelled'], true)) {
                                throw new \RuntimeException('No se confirmó la simulación de un aviso.');
                            }
                        }
                    }
                    Date::setTestNow($clock);
                    Artisan::call('commercial:expire', ['--batch' => $batch]);
                });
            } catch (\Throwable $error) {
                foreach ($this->generatedFiles as $file) {
                    Storage::disk('commercial')->delete($file);
                }
                throw $error;
            } finally {
                Date::setTestNow($date);
                config(['mail.default' => $mail]);
                Mail::purge();
            }
            $this->info('Lote preparado: 15 solicitudes, documentos PDF reales y avisos simulados; cuentas y catálogos conservados.');

            return self::SUCCESS;
        });
    }

    private function scenario(string $batch, string $scenario, array $specification, User $actor): void
    {
        [$code, $area, $lod, $description] = $specification;
        $service = OfferedService::where('code', $code)->where('is_active', true)->firstOrFail();
        $estimate = app(QuotationCalculatorService::class)->calculate($area, $lod, $service);
        if (! $estimate['estimated_cost_decimal']) {
            throw new \RuntimeException('Se requiere una tarifa vigente para los escenarios.');
        }
        $data = DemoRequestFactory::new()->raw() + ['client_email' => $batch.'.'.$scenario.'@example.com',
            'submission_token' => (string) Str::uuid(), 'service_id' => $service->id, 'description' => $description,
            'area_m2' => $area, 'lod_selected' => $lod, 'consent' => '1', 'estimate_confirmed' => '1', 'estimate_token' => $estimate['estimate_token']];
        [$request] = app(ServiceRequestService::class)->register($data, null);
        $request->update(['demo_batch' => $batch, 'demo_scenario' => $scenario]);
        if (in_array($scenario, ['pendiente', 'pendiente-planos'])) {
            return;
        }
        $this->transition($request, $actor, $actor->role === 'admin' ? 'assign' : 'claim', ['assigned_to' => $actor->id]);
        if ($scenario === 'asignada') {
            return;
        }
        $this->transition($request, $actor, 'start');
        if ($scenario === 'evaluacion') {
            return;
        }
        if (in_array($scenario, ['observada', 'corregida'])) {
            $this->transition($request, $actor, 'observe', ['public_observation' => 'Confirma las medidas y alturas de cada ambiente para continuar con la evaluación.']);
            if ($scenario === 'corregida') {
                Date::setTestNow(now('UTC')->addHours(1));
                $grant = $this->verifiedGrant($request);
                app(ClientRevisionService::class)->correct($request->fresh(), $grant, ['lock_version' => $request->fresh()->lock_version,
                    'description' => $description.' Medidas verificadas: altura libre 2,50 m y ambientes identificados en la descripción.',
                    'area_m2' => $area, 'lod_selected' => $lod, 'reason' => 'Medidas y alturas confirmadas para retomar la evaluación.'], null);
            }

            return;
        }
        if (in_array($scenario, ['rechazada', 'cancelada'])) {
            $this->transition($request, $actor, $scenario === 'rechazada' ? 'reject' : 'cancel', ['reason' => $scenario === 'rechazada'
                ? 'Falta un levantamiento estructural que permita definir un alcance viable.' : 'La familia decidió postergar la mudanza y solicitó cancelar la evaluación.']);

            return;
        }
        $this->transition($request, $actor, 'approve', ['assessment' => 'Se verificaron área, nivel de detalle y necesidades descritas. El alcance documental es viable con las referencias disponibles.']);
        $quote = $this->draft($request, $actor);
        if ($scenario === 'borrador') {
            return;
        }
        $quote = $this->emit($quote, $actor);
        if ($scenario === 'sustituida') {
            Date::setTestNow(now('UTC')->addHours(2));
            $this->emit($this->draft($request, $actor, $quote), $actor);
        }
        if (in_array($scenario, ['aceptada', 'propuesta-rechazada'])) {
            Date::setTestNow(now('UTC')->addHours(1));
            app(ClientResponseService::class)->respond($quote, $this->verifiedGrant($request), ['decision' => $scenario === 'aceptada' ? 'aceptada' : 'rechazada',
                'lock_version' => $quote->lock_version, 'reason' => $scenario === 'aceptada' ? null : 'La familia priorizará otra etapa del proyecto antes de contratar estas imágenes.']);
        }
        if ($scenario === 'cerrada') {
            Date::setTestNow(now('UTC')->addDays(16));
            Artisan::call('commercial:expire', ['--batch' => $batch]);
            $this->transition($request, $actor, 'close', ['reason' => 'La propuesta venció sin acuerdo; se cierra el seguimiento comercial.']);
        }
    }

    private function transition(ServiceRequest $request, User $actor, string $action, array $extra = []): void
    {
        Date::setTestNow(now('UTC')->addMinutes(20));
        app(CommercialWorkflowService::class)->transition($request->fresh(), $actor, $extra + [
            'action' => $action, 'lock_version' => $request->fresh()->lock_version, 'reason' => 'Revisión del alcance y avance del expediente de demostración.',
        ]);
    }

    private function draft(ServiceRequest $request, User $actor, ?Quotation $source = null): Quotation
    {
        $request->refresh();

        return app(IssueQuotationService::class)->draft($request, $actor, [
            'request_lock_version' => $request->lock_version, 'scope' => $request->description,
            'deliverables' => 'Propuesta de distribución, documentación y vistas acordadas según el servicio y el nivel de detalle seleccionado.',
            'exclusions' => 'No incluye ejecución de obra, licencias municipales ni estudios de especialidades.',
            'execution_term' => '20 días hábiles desde la entrega de referencias completas.',
            'official_amount' => $request->estimated_cost, 'adjustment_reason' => null,
            'reason' => $source ? 'Se precisa la selección de materiales y la entrega de vistas del alcance inicial.' : 'Alcance validado, sin ajuste de la estimación de área y LOD.',
        ], $source);
    }

    private function emit(Quotation $quote, User $actor): Quotation
    {
        Date::setTestNow(now('UTC')->addMinutes(20));
        $work = app(IssueQuotationService::class)->prepare($quote, $actor, $quote->lock_version);
        app(WorkProcessor::class)->run($work->id);
        $quote->refresh();
        if ($quote->pdf_path) {
            $this->generatedFiles[] = $quote->pdf_path;
        }
        if ($quote->pdf_status !== 'ready' || ! $quote->pdf_path || ! str_starts_with(Storage::disk('commercial')->get($quote->pdf_path), '%PDF-')) {
            throw new \RuntimeException('La cotización no tiene un PDF válido.');
        }

        return $quote;
    }

    private function verifiedGrant(ServiceRequest $request): ClientGrant
    {
        $access = app(ClientAccessService::class);
        $access->request($request->code, $request->client_email);
        $token = ClientAccessToken::where('service_request_id', $request->id)->latest('id')->firstOrFail();
        $link = $access->temporaryLink($token);
        $http = Request::create('/acceso-expediente', 'POST');
        $session = new Store('demo-preparation', new ArraySessionHandler(30));
        $session->start();
        $http->setLaravelSession($session);
        $access->consume($http, basename(parse_url($link, PHP_URL_PATH)));

        return $access->grant($http);
    }
}
