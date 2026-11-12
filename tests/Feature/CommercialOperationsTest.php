<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ClientResponseService;
use App\Services\IssueQuotationService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CommercialFixtures;
use Tests\TestCase;

class CommercialOperationsTest extends TestCase
{
    use CommercialFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('commercial');
        Mail::fake();
        $this->tariff();
    }

    public function test_abandoned_pdf_intent_is_recovered_without_a_new_version(): void
    {
        $manager = User::factory()->create(['role' => 'gestor']);
        $r = $this->registerRequest();
        $this->approve($r, $manager);
        $q = $this->draft($r, $manager);
        $intent = app(IssueQuotationService::class)->prepare($q, $manager, $q->lock_version);
        $intent->update(['status' => 'processing', 'lease' => 'abandoned', 'leased_at' => now('UTC')->subMinutes(6)]);
        $this->artisan('commercial:recover', ['--inline' => true])->assertSuccessful();
        $this->assertSame('done', $intent->fresh()->status);
        $this->assertSame('emitida', $q->fresh()->status);
        $this->assertDatabaseCount('quotations', 1);
        $this->artisan('commercial:recover', ['--inline' => true])->assertSuccessful();
        $this->assertSame('simulated', $q->fresh()->notification_status);
    }

    public function test_acceptance_indicator_counts_requests_once_with_multiple_emissions(): void
    {
        $manager = User::factory()->create(['role' => 'gestor']);
        $r = $this->registerRequest();
        $this->approve($r, $manager);
        $v1 = $this->emit($this->draft($r, $manager), $manager);
        $v2 = $this->emit($this->draft($r, $manager, '12500', $v1), $manager);
        [$grant, $raw] = $this->clientGrant($r);
        app(ClientResponseService::class)->respond($v2, $grant, ['decision' => 'aceptada', 'lock_version' => $v2->lock_version]);
        $this->actingAs($manager)->get('/gestion')->assertOk()->assertViewHas('denominator', 1)->assertViewHas('accepted', 1)->assertViewHas('issued', 2);
        $this->actingAs(User::factory()->create(['role' => 'gestor']))->get('/gestion')->assertOk()->assertViewHas('denominator', 0)->assertViewHas('accepted', 0);
    }

    public function test_first_evaluation_period_uses_local_creation_day_and_utc_evaluation(): void
    {
        $manager = User::factory()->create(['role' => 'gestor']);
        $r = $this->registerRequest();
        $this->action($r, $manager, 'claim');
        $r->update(['created_at' => CarbonImmutable::parse('2026-11-11 00:01:00', 'America/Lima'), 'first_evaluated_at' => CarbonImmutable::parse('2026-11-11 05:11:00', 'UTC')]);
        $this->actingAs($manager)->get('/gestion?from=2026-11-11&to=2026-11-11')->assertOk()->assertViewHas('minutes', 10.0);
    }
}
