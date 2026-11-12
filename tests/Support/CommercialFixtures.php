<?php

namespace Tests\Support;

use App\Models\ClientGrant;
use App\Models\OfferedService;
use App\Models\ServiceRequest;
use App\Models\TariffGroup;
use App\Models\TariffParam;
use App\Models\User;
use App\Services\CommercialWorkflowService;
use App\Services\IssueQuotationService;
use App\Services\QuotationCalculatorService;
use App\Services\ServiceRequestService;
use App\Services\WorkProcessor;
use Illuminate\Support\Str;

trait CommercialFixtures
{
    protected function tariff(string $base = '100', string $lod = 'LOD_300', string $factor = '1.350'): TariffParam
    {
        $g = TariffGroup::firstOrCreate(['lod_level' => $lod]);
        TariffParam::where('tariff_group_id', $g->id)->where('status', 'activa')->update(['status' => 'inactiva', 'is_active' => false]);
        $t = TariffParam::create(['tariff_group_id' => $g->id, 'version' => (int) $g->versions()->max('version') + 1, 'lod_level' => $lod, 'base_price_m2' => $base, 'lod_factor' => $factor, 'max_area_m2' => '500', 'status' => 'activa', 'is_active' => true, 'currency' => 'PEN']);
        $g->update(['active_tariff_id' => $t->id]);

        return $t;
    }

    protected function requestData(string $area = '80', ?OfferedService $service = null): array
    {
        $service ??= OfferedService::where('code', 'BIM')->firstOrFail();
        $manual = $service->estimation_mode === 'manual';
        $e = app(QuotationCalculatorService::class)->calculate($manual ? null : $area, $manual ? null : 'LOD_300', $service);

        return ['submission_token' => (string) Str::uuid(), 'service_id' => $service->id, 'client_name' => 'Cliente ficticio', 'client_email' => 'cliente@example.test', 'client_phone' => '999888777', 'city' => 'Lima', 'project_type' => 'Vivienda', 'description' => 'Solicitud ficticia con datos suficientes para evaluación.', 'area_m2' => $manual ? null : $area, 'lod_selected' => $manual ? null : 'LOD_300', 'consent' => '1', 'estimate_confirmed' => '1', 'estimate_token' => $e['estimate_token']];
    }

    protected function registerRequest(string $area = '80', $file = null, ?OfferedService $service = null): ServiceRequest
    {
        return app(ServiceRequestService::class)->register($this->requestData($area, $service), $file)[0];
    }

    protected function action(ServiceRequest $r, User $u, string $action, array $extra = []): void
    {
        $r->refresh();
        app(CommercialWorkflowService::class)->transition($r, $u, ['action' => $action, 'lock_version' => $r->lock_version, 'reason' => 'Motivo suficiente de prueba'] + $extra);
        $r->refresh();
    }

    protected function approve(ServiceRequest $r, User $u): void
    {
        if (! $r->assigned_to) {
            $this->action($r, $u, $u->role === 'admin' ? 'assign' : 'claim', ['assigned_to' => $u->id]);
        }$this->action($r, $u, 'start');
        $this->action($r, $u, 'approve', ['assessment' => 'Evaluación técnica ficticia completa y viable.']);
    }

    protected function quoteData(ServiceRequest $r, string $amount = '12000.50'): array
    {
        return ['request_lock_version' => $r->fresh()->lock_version, 'lock_version' => 0, 'scope' => 'Alcance completo del proyecto ficticio.', 'deliverables' => 'Modelo y planos de demostración.', 'exclusions' => 'No incluye ejecución de obra.', 'execution_term' => '20 días de trabajo', 'official_amount' => $amount, 'adjustment_reason' => 'Ajuste por evaluación técnica del alcance.', 'reason' => 'Versión preparada para la prueba.'];
    }

    protected function draft(ServiceRequest $r, User $u, string $amount = '12000.50', $source = null)
    {
        return app(IssueQuotationService::class)->draft($r, $u, $this->quoteData($r, $amount), $source);
    }

    protected function emit($q, User $u)
    {
        $q->refresh();
        $work = app(IssueQuotationService::class)->prepare($q, $u, $q->lock_version);
        app(WorkProcessor::class)->run($work->id);

        return $q->fresh();
    }

    protected function clientGrant(ServiceRequest $r): array
    {
        $raw = bin2hex(random_bytes(32));
        $g = ClientGrant::create(['service_request_id' => $r->id, 'email' => $r->client_email, 'session_hash' => hash('sha256', $raw), 'expires_at' => now('UTC')->addMinutes(30)]);

        return [$g, $raw];
    }
}
