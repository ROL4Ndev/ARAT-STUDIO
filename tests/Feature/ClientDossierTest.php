<?php

namespace Tests\Feature;

use App\Mail\CommercialNotice;
use App\Models\ClientAccessToken;
use App\Models\NotificationRecord;
use App\Models\User;
use App\Models\WorkIntent;
use App\Services\ClientAccessService;
use App\Services\WorkProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CommercialFixtures;
use Tests\TestCase;

class ClientDossierTest extends TestCase
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

    private function sendAccess(string $purpose = 'access'): string
    {
        $notice = NotificationRecord::where('kind', $purpose)->latest('id')->firstOrFail();
        $work = WorkIntent::where('key', 'notification:'.$notice->id)->firstOrFail();
        app(WorkProcessor::class)->run($work->id);
        $m = Mail::sent(CommercialNotice::class)->last();
        $raw = basename(parse_url($m->link, PHP_URL_PATH));
        $this->assertSame(64, strlen($raw));

        return $raw;
    }

    public function test_generic_request_and_single_use_hashed_expiring_link(): void
    {
        $this->post('/acceder-expediente', ['code' => 'SOL-INEXISTENTE', 'email' => 'nadie@example.test'])->assertRedirect()->assertSessionHas('status');
        $this->assertSame(0, ClientAccessToken::count());
        $this->post('/acceder-expediente', ['code' => $this->r->code, 'email' => $this->r->client_email])->assertRedirect();
        $raw = $this->sendAccess();
        $token = ClientAccessToken::first();
        $this->assertSame(hash('sha256', $raw), $token->getRawOriginal('token_hash'));
        $this->assertArrayNotHasKey('token_hash', $token->toArray());
        $this->get(route('client.access.form', $raw))->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
        $this->assertNull($token->fresh()->consumed_at);
        $this->post(route('client.access.consume', $raw))->assertRedirect(route('client.dossier'));
        $this->get('/mi-solicitud')->assertOk()->assertSee($this->r->code);
        $this->post(route('client.access.consume', $raw))->assertForbidden();
    }

    public function test_access_link_expires_after_fifteen_minutes(): void
    {
        app(ClientAccessService::class)->request($this->r->code, $this->r->client_email);
        $raw = $this->sendAccess();
        $this->travelTo(ClientAccessToken::first()->expires_at);
        $this->post(route('client.access.consume', $raw))->assertForbidden();
        $this->get('/mi-solicitud')->assertForbidden();
    }

    public function test_client_cannot_read_other_file_quote_or_internal_notes(): void
    {
        $this->approve($this->r, $this->manager);
        $own = $this->emit($this->draft($this->r, $this->manager), $this->manager);
        $other = $this->registerRequest();
        $this->approve($other, $this->manager);
        $foreign = $this->emit($this->draft($other, $this->manager), $this->manager);
        $this->r->histories()->create(['action' => 'note', 'previous_status' => 'aprobado', 'new_status' => 'aprobado', 'is_public' => false, 'observation' => 'NOTA_INTERNA_NO_PUBLICA']);
        [$g,$raw] = $this->clientGrant($this->r);
        $this->withSession(['client_grant' => $raw])->get('/mi-solicitud')->assertOk()->assertDontSee('NOTA_INTERNA_NO_PUBLICA')->assertSee($own->code)->assertDontSee($foreign->code);
        $this->get(route('client.quotations.download', $foreign))->assertForbidden();
        $this->get(route('client.quotations.download', $own))->assertOk()->assertDownload();
    }

    public function test_accepted_response_is_idempotent_opposite_conflicts_and_staff_cannot_simulate(): void
    {
        $this->approve($this->r, $this->manager);
        $q = $this->emit($this->draft($this->r, $this->manager), $this->manager);
        [$g,$raw] = $this->clientGrant($this->r);
        $url = route('client.respond', $q);
        $d = ['decision' => 'aceptada', 'lock_version' => $q->lock_version];
        $this->actingAs($this->manager)->withSession(['client_grant' => $raw])->post($url, $d)->assertForbidden();
        Auth::logout();
        $this->withSession(['client_grant' => $raw])->post($url, $d)->assertRedirect();
        $count = $this->r->histories()->count();
        $this->post($url, $d)->assertRedirect();
        $this->assertSame($count, $this->r->histories()->count());
        $this->post($url, [...$d, 'decision' => 'rechazada', 'reason' => 'Respuesta opuesta'])->assertStatus(409);
        $this->assertSame('cerrada', $this->r->fresh()->status);
        $this->assertSame('aceptada', $this->r->fresh()->closure_outcome);
        $this->get($url)->assertStatus(405);
    }

    public function test_rejection_requires_reason_and_acceptance_prevents_cancel(): void
    {
        $this->approve($this->r, $this->manager);
        $q = $this->emit($this->draft($this->r, $this->manager), $this->manager);
        [$g,$raw] = $this->clientGrant($this->r);
        $this->withSession(['client_grant' => $raw])->post(route('client.respond', $q), ['decision' => 'rechazada', 'lock_version' => $q->lock_version])->assertSessionHasErrors('reason');
        $this->post(route('client.respond', $q), ['decision' => 'aceptada', 'lock_version' => $q->lock_version])->assertRedirect();
        $this->post(route('client.cancel'), ['reason' => 'Intento posterior a aceptación', 'lock_version' => $this->r->fresh()->lock_version])->assertStatus(409);
    }

    public function test_email_change_requires_new_email_verification_and_revokes_old_grants(): void
    {
        [$g,$oldRaw] = $this->clientGrant($this->r);
        $this->withSession(['client_grant' => $oldRaw])->post(route('client.email'), ['email' => 'nuevo@example.test'])->assertRedirect();
        $this->assertSame('cliente@example.test', $this->r->fresh()->client_email);
        $raw = $this->sendAccess('email_change');
        $this->post(route('client.access.consume', $raw))->assertRedirect();
        $this->assertSame('nuevo@example.test', $this->r->fresh()->client_email);
        $this->assertNotNull($g->fresh()->revoked_at);
        $this->get('/mi-solicitud')->assertOk()->assertSee('nuevo@example.test');
        $this->withSession(['client_grant' => $oldRaw])->get('/mi-solicitud')->assertForbidden();
    }

    public function test_session_revocation_and_expiry_are_enforced(): void
    {
        [$g,$raw] = $this->clientGrant($this->r);
        $this->withSession(['client_grant' => $raw])->post(route('client.logout'))->assertRedirect();
        $this->withSession(['client_grant' => $raw])->get('/mi-solicitud')->assertForbidden();
        [$g,$raw] = $this->clientGrant($this->r);
        $this->travelTo($g->expires_at);
        $this->withSession(['client_grant' => $raw])->get('/mi-solicitud')->assertForbidden();
    }

    public function test_correction_cannot_change_service_or_unverified_email(): void
    {
        $this->action($this->r, $this->manager, 'claim');
        $this->action($this->r, $this->manager, 'start');
        $this->action($this->r, $this->manager, 'observe', ['public_observation' => 'Completa la descripción del proyecto.']);
        [$g,$raw] = $this->clientGrant($this->r);
        $this->withSession(['client_grant' => $raw])->post(route('client.correct'), ['lock_version' => $this->r->lock_version, 'service_id' => 999, 'client_email' => 'otro@example.test'])->assertSessionHasErrors(['service_id', 'client_email']);
    }

    public function test_verified_email_is_historical_but_does_not_replace_required_correction(): void
    {
        $this->action($this->r, $this->manager, 'claim');
        $this->action($this->r, $this->manager, 'start');
        $this->action($this->r, $this->manager, 'observe', ['public_observation' => 'Completa los planos y la distribución del proyecto.']);
        [$g,$raw] = $this->clientGrant($this->r);
        $this->withSession(['client_grant' => $raw])->post(route('client.email'), ['email' => 'verificado@example.test'])->assertRedirect();
        $token = $this->sendAccess('email_change');
        $this->post(route('client.access.consume', $token))->assertRedirect();
        $r = $this->r->fresh();
        $this->assertSame('verified_email', $r->currentRevision->kind);
        $this->assertSame($g->id, $r->currentRevision->client_grant_id);
        $this->assertSame('verificado@example.test', $r->currentRevision->after['client_email']);
        $this->assertSame($r->service_id, $r->currentRevision->after['service_id']);
        $this->actingAs($this->manager)->patch(route('commercial.requests.transition', $r), ['action' => 'resume', 'lock_version' => $r->lock_version, 'reason' => 'Intento de reanudar sin corregir los planos'])->assertStatus(409);
    }
}
