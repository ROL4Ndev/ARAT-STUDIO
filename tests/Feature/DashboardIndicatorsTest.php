<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CommercialIndicators;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CommercialFixtures;
use Tests\TestCase;

class DashboardIndicatorsTest extends TestCase
{
    use CommercialFixtures, RefreshDatabase;

    public function test_week_boundaries_state_counts_and_average_use_lima_and_authorized_scope(): void
    {
        Mail::fake();
        $this->tariff();
        $admin = User::factory()->create(['role' => 'admin']);
        $manager = User::factory()->create(['role' => 'gestor']);
        $other = User::factory()->create(['role' => 'gestor']);
        $rows = [
            ['assigned_to' => $manager->id, 'created_at' => '2026-11-02 00:00:00', 'first_evaluated_at' => '2026-11-02 05:45:00', 'status' => 'evaluacion'],
            ['assigned_to' => $manager->id, 'created_at' => '2026-11-09 00:00:00', 'first_evaluated_at' => '2026-11-09 06:15:00', 'status' => 'observada'],
            ['assigned_to' => $other->id, 'created_at' => '2026-11-08 23:59:59', 'status' => 'aprobado'],
            ['assigned_to' => null, 'created_at' => '2026-11-15 23:59:59', 'status' => 'pendiente'],
            ['assigned_to' => $other->id, 'created_at' => '2026-11-01 23:59:59', 'status' => 'pendiente'],
        ];
        $requests = [];
        foreach ($rows as $row) {
            $request = $this->registerRequest();
            DB::table('service_requests')->where('id', $request->id)->update($row);
            $requests[] = $request;
        }
        $indicators = app(CommercialIndicators::class);
        $period = $indicators->period(['from' => '2026-11-02', 'to' => '2026-11-15']);
        $general = $indicators->overview($admin, $period);
        $this->assertSame(4, $general['received']);
        $this->assertSame([2, 2], array_column($general['weeks'], 'count'));
        $this->assertSame(4, array_sum(array_column($general['states'], 'count')));
        $scoped = $indicators->overview($manager, $period);
        $this->assertSame(2, $scoped['received']);
        $this->assertSame([1, 1], array_column($scoped['weeks'], 'count'));
        $this->assertSame(2, $scoped['attentionCount']);
        $this->assertEqualsCanonicalizing([$requests[0]->id, $requests[1]->id], $scoped['recentRequests']->pluck('id')->all());
        DB::enableQueryLog();
        $metrics = $indicators->commercialMetrics($manager, $period);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertEqualsWithDelta(60, $metrics['minutes'], 0.0001);
        $this->assertCount(1, array_filter($queries, fn ($query) => str_contains($query['query'], 'AVG(')));
        $this->assertSame(0, $indicators->overview(User::factory()->create(['role' => null]), $period)['received']);
    }

    public function test_dashboard_is_accessible_in_empty_state_and_chart_bundle_is_only_on_relevant_pages(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/admin')->assertOk()
            ->assertSee('Todavía no hay solicitudes recibidas en este período.')
            ->assertSee('America/Lima')->assertSee('Datos de solicitudes por semana')
            ->assertSee('Datos de solicitudes por estado')->assertSee('dashboard-chart-data')
            ->assertSee('dashboard-', false)->assertDontSee('data-weekly-chart', false);
        $this->get('/admin/equipo')->assertOk()->assertDontSee('dashboard-chart-data')->assertDontSee('data-weekly-chart');
        $this->get('/')->assertOk()->assertDontSee('dashboard-chart-data')->assertDontSee('data-weekly-chart');
        $this->actingAs(User::factory()->create(['role' => 'gestor']))->get('/gestion')->assertOk()
            ->assertSee('Solo expedientes asignados a tu cuenta.')
            ->assertSee('No tienes solicitudes pendientes de atención.');
    }

    public function test_period_defaults_to_thirty_calendar_days_and_unassigned_queue_keeps_contacts_private(): void
    {
        Mail::fake();
        $this->tariff();
        $request = $this->registerRequest();
        $manager = User::factory()->create(['role' => 'gestor']);
        $response = $this->actingAs($manager)->get('/gestion')->assertOk()
            ->assertDontSee($request->client_email)->assertDontSee($request->client_name)
            ->assertDontSee($request->client_phone);
        $this->assertSame(0, $response->viewData('overview')['received']);
        $period = app(CommercialIndicators::class)->period();
        $this->assertSame(29.0, $period['from']->diffInDays($period['to']->startOfDay()));
        $this->assertSame('America/Lima', $period['from']->timezoneName);
    }
}
