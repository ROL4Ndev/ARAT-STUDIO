<?php

namespace Tests\Feature;

use App\Models\NotificationRecord;
use App\Models\User;
use App\Models\WorkIntent;
use App\Services\ClientResponseService;
use App\Services\CommercialWorkflowService;
use App\Services\IssueQuotationService;
use App\Services\QuotationPdfService;
use App\Services\WorkProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CommercialFixtures;
use Tests\TestCase;

class OfficialQuotationTest extends TestCase
{
    use CommercialFixtures,RefreshDatabase;

    private $r;

    private $manager;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('commercial');
        Mail::fake();
        $this->tariff();
        $this->r = $this->registerRequest();
        $this->manager = User::factory()->create(['role' => 'gestor']);
    }

    public function test_only_approved_request_and_positive_adjusted_amount_are_accepted(): void
    {
        $this->action($this->r, $this->manager, 'claim');
        $this->actingAs($this->manager)->post(route('commercial.quotations.store', $this->r), $this->quoteData($this->r))->assertStatus(409);
        $this->action($this->r, $this->manager, 'start');
        $this->action($this->r, $this->manager, 'approve', ['assessment' => 'Evaluación técnica suficientemente detallada.']);
        $d = $this->quoteData($this->r);
        $this->post(route('commercial.quotations.store', $this->r), [...$d, 'official_amount' => '0'])->assertSessionHasErrors('official_amount');
        $this->post(route('commercial.quotations.store', $this->r), [...$d, 'adjustment_reason' => ''])->assertSessionHasErrors('adjustment_reason');
    }

    public function test_durable_prepare_pdf_real_exact_expiry_and_snapshots(): void
    {
        $this->approve($this->r, $this->manager);
        $q = $this->draft($this->r, $this->manager);
        $intent = app(IssueQuotationService::class)->prepare($q, $this->manager, $q->lock_version);
        $this->assertSame('preparando', $q->fresh()->status);
        $this->assertNull($q->fresh()->pdf_path);
        $this->assertSame('pending', $intent->status);
        app(WorkProcessor::class)->run($intent->id);
        $q->refresh();
        $this->assertSame('emitida', $q->status);
        $this->assertSame('ready', $q->pdf_status);
        $this->assertSame(15 * 86400, (int) $q->issued_at->diffInSeconds($q->expires_at));
        $this->assertStringStartsWith('%PDF-', Storage::disk('commercial')->get($q->pdf_path));
        $this->r->update(['client_name' => 'Otro nombre']);
        $this->assertSame('Cliente ficticio', $q->fresh()->client_snapshot['name']);
        $this->actingAs($this->manager)->get(route('commercial.quotations.show', $q))
            ->assertOk()->assertSeeText('Procesamiento de esta versión')->assertDontSeeText('ID '.$intent->id);
        $this->get(route('commercial.quotations.download', $q))->assertDownload();
        $this->actingAs(User::factory()->create(['role' => 'gestor']))->get(route('commercial.quotations.download', $q))->assertForbidden();
    }

    public function test_only_one_pending_version_and_new_draft_does_not_invalidate_v1(): void
    {
        $this->approve($this->r, $this->manager);
        $v1 = $this->emit($this->draft($this->r, $this->manager), $this->manager);
        $v2 = $this->draft($this->r, $this->manager, '12500', $v1);
        $again = $this->draft($this->r, $this->manager, '12500', $v1);
        $this->assertSame($v2->id, $again->id);
        $this->assertSame('emitida', $v1->fresh()->status);
        $this->assertSame($v1->id, $this->r->fresh()->current_quotation_id);
        $oldPath = $v1->pdf_path;
        $hash = hash('sha256', Storage::disk('commercial')->get($oldPath));
        $this->emit($v2, $this->manager);
        $this->assertSame('sustituida', $v1->fresh()->status);
        $this->assertSame($v2->id, $this->r->fresh()->current_quotation_id);
        $this->assertSame($hash, hash('sha256', Storage::disk('commercial')->get($oldPath)));
    }

    public function test_pdf_failure_and_retry_keep_same_version(): void
    {
        $this->approve($this->r, $this->manager);
        $q = $this->draft($this->r, $this->manager);
        $this->mock(QuotationPdfService::class)->shouldReceive('render')->once()->andThrow(new \RuntimeException('render failure'));
        $this->emit($q, $this->manager);
        $q->refresh();
        $this->assertSame('preparando', $q->status);
        $this->assertSame('failed', $q->pdf_status);
        $this->assertDatabaseCount('quotations', 1);
        $this->app->forgetInstance(QuotationPdfService::class);
        $this->emit($q, $this->manager);
        $this->assertSame('emitida', $q->fresh()->status);
        $this->assertDatabaseCount('quotations', 1);
        $this->assertSame(2, WorkIntent::where('kind', 'pdf')->first()->attempts);
    }

    public function test_accepting_v1_during_v2_render_prevents_incompatible_emission(): void
    {
        $this->approve($this->r, $this->manager);
        $v1 = $this->emit($this->draft($this->r, $this->manager), $this->manager);
        [$g] = $this->clientGrant($this->r);
        $v2 = $this->draft($this->r, $this->manager, '12500', $v1);
        $baseline = DB::transactionLevel();
        $this->mock(QuotationPdfService::class)->shouldReceive('render')->once()->andReturnUsing(function () use ($v1, $g, $baseline) {
            $this->assertSame($baseline, DB::transactionLevel());
            app(ClientResponseService::class)->respond($v1, $g, ['decision' => 'aceptada', 'lock_version' => $v1->lock_version]);

            return "%PDF-1.4\ntechnical";
        });
        $this->emit($v2, $this->manager);
        $this->assertSame('aceptada', $v1->fresh()->status);
        $this->assertSame('anulada', $v2->fresh()->status);
        $this->assertSame('cerrada', $this->r->fresh()->status);
    }

    public function test_new_emission_before_old_acceptance_returns_conflict(): void
    {
        $this->approve($this->r, $this->manager);
        $v1 = $this->emit($this->draft($this->r, $this->manager), $this->manager);
        [$g,$raw] = $this->clientGrant($this->r);
        $v2 = $this->emit($this->draft($this->r, $this->manager, '12500', $v1), $this->manager);
        $this->withSession(['client_grant' => $raw])->post(route('client.respond', $v1), ['decision' => 'aceptada', 'lock_version' => $v1->lock_version])->assertStatus(409);
        $this->assertSame('emitida', $v2->fresh()->status);
    }

    public function test_cancel_during_render_preserves_trace_without_confirming_pdf(): void
    {
        $this->approve($this->r, $this->manager);
        $q = $this->draft($this->r, $this->manager);
        $this->mock(QuotationPdfService::class)->shouldReceive('render')->andReturnUsing(function () {
            app(CommercialWorkflowService::class)->cancel($this->r->fresh(), $this->manager, $this->r->fresh()->lock_version, 'Cancelación durante preparación');

            return "%PDF-1.4\ntechnical";
        });
        $this->emit($q, $this->manager);
        $this->assertSame('anulada', $q->fresh()->status);
        $this->assertNull($q->fresh()->issued_at);
        $this->assertSame('cancelled', WorkIntent::where('kind', 'pdf')->first()->status);
    }

    public function test_expiry_boundary_is_enforced_without_scheduler(): void
    {
        $this->approve($this->r, $this->manager);
        $q = $this->emit($this->draft($this->r, $this->manager), $this->manager);
        [$g,$raw] = $this->clientGrant($this->r);
        $g->update(['expires_at' => $q->expires_at->addHour()]);
        $this->travelTo($q->expires_at);
        $this->withSession(['client_grant' => $raw])->post(route('client.respond', $q), ['decision' => 'aceptada', 'lock_version' => $q->lock_version])->assertStatus(409);
        $this->assertSame('emitida', $q->fresh()->status);
    }

    public function test_mail_failure_is_separate_from_issued_pdf_and_retry_does_not_create_version(): void
    {
        $this->approve($this->r, $this->manager);
        $q = $this->emit($this->draft($this->r, $this->manager), $this->manager);
        $notice = NotificationRecord::where('kind', 'quotation')->first();
        $work = WorkIntent::where('key', 'notification:'.$notice->id)->first();
        $originalMail = Mail::getFacadeRoot();
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp unavailable'));
        app(WorkProcessor::class)->run($work->id);
        $this->assertSame('failed', $notice->fresh()->status);
        $this->assertSame('emitida', $q->fresh()->status);
        $this->assertSame('ready', $q->fresh()->pdf_status);
        $this->assertDatabaseCount('quotations', 1);
        Mail::swap($originalMail);
        $work->refresh()->update(['status' => 'pending']);
        app(WorkProcessor::class)->run($work->id);
        $this->assertSame('simulated', $notice->fresh()->status);
        $this->assertDatabaseCount('quotations', 1);
    }
}
