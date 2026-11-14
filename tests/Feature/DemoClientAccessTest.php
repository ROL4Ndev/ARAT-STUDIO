<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\ClientAccessToken;
use App\Models\NotificationRecord;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\Support\CommercialFixtures;
use Tests\TestCase;

class DemoClientAccessTest extends TestCase
{
    use CommercialFixtures, RefreshDatabase;

    private $serviceRequest;

    private $manager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tariff();
        $this->serviceRequest = $this->registerRequest();
        $this->manager = User::factory()->create(['role' => 'gestor']);
        $this->serviceRequest->update(['assigned_to' => $this->manager->id]);
        config(['app.env' => 'local', 'client_access.demo_enabled' => true]);
    }

    public function test_authorized_staff_can_prepare_a_private_hashed_single_use_link_for_only_its_dossier(): void
    {
        $otherRequest = $this->registerRequest();
        $notificationCount = NotificationRecord::count();
        $this->actingAs($this->manager)
            ->get(route('commercial.requests.show', $this->serviceRequest))
            ->assertOk()
            ->assertSee('Preparar acceso de cliente para demostración');

        $this->post(route('commercial.requests.demo-access.prepare', $this->serviceRequest))
            ->assertRedirect(route('commercial.requests.demo-access.show', $this->serviceRequest));

        $screen = $this->get(route('commercial.requests.demo-access.show', $this->serviceRequest))
            ->assertOk()
            ->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertSee('Copia este enlace y ábrelo en una ventana de incógnito')
            ->assertSee('Abrir otra pestaña normal comparte la sesión interna y no sirve.');
        $this->assertStringContainsString('no-store', $screen->headers->get('Cache-Control'));

        preg_match('/id="demo-access-link" class="form-control" type="text" value="([^"]+)"/', $screen->getContent(), $matches);
        $link = html_entity_decode($matches[1] ?? '', ENT_QUOTES);
        $raw = basename(parse_url($link, PHP_URL_PATH));
        $token = ClientAccessToken::firstOrFail();

        $this->assertSame(64, strlen($raw));
        $this->assertSame(hash('sha256', $raw), $token->getRawOriginal('token_hash'));
        $this->assertSame(900, $token->expires_at->getTimestamp() - $token->created_at->getTimestamp());
        $this->assertSame($this->serviceRequest->id, $token->service_request_id);
        $this->assertSame($notificationCount, NotificationRecord::count());
        $this->assertDatabaseHas('audit_events', [
            'resource_id' => $this->serviceRequest->id,
            'action' => 'demo_client_access_prepared',
            'actor_id' => $this->manager->id,
        ]);
        $audit = AuditEvent::where('action', 'demo_client_access_prepared')->firstOrFail();
        $this->assertStringNotContainsString($raw, $audit->reason.json_encode($audit->before).json_encode($audit->after));

        $this->post(route('client.access.consume', $raw))->assertForbidden();
        Auth::logout();
        $this->get(route('client.access.form', $raw))
            ->assertOk()
            ->assertSee('Verificar y abrir expediente');
        $this->get(route('client.dossier'))->assertForbidden();
        $this->post(route('client.access.consume', $raw))->assertRedirect(route('client.dossier'));
        $this->get(route('client.dossier'))
            ->assertOk()
            ->assertSee($this->serviceRequest->code)
            ->assertDontSee($otherRequest->code);
        $this->assertNotNull($token->fresh()->consumed_at);
        $this->post(route('client.access.consume', $raw))->assertForbidden();
    }

    public function test_unassigned_staff_cannot_prepare_or_view_the_demo_access(): void
    {
        $other = User::factory()->create(['role' => 'gestor']);

        $this->actingAs($other)
            ->post(route('commercial.requests.demo-access.prepare', $this->serviceRequest))
            ->assertForbidden();
        $this->get(route('commercial.requests.demo-access.show', $this->serviceRequest))
            ->assertForbidden();
        $this->assertSame(0, ClientAccessToken::count());
    }

    public function test_demo_access_is_unavailable_without_explicit_local_configuration_or_outside_local(): void
    {
        config(['client_access.demo_enabled' => false]);
        $this->actingAs($this->manager)
            ->get(route('commercial.requests.show', $this->serviceRequest))
            ->assertDontSee('Preparar acceso de cliente para demostración');
        $this->post(route('commercial.requests.demo-access.prepare', $this->serviceRequest))->assertNotFound();

        config(['client_access.demo_enabled' => true, 'app.env' => 'production']);
        $this->post(route('commercial.requests.demo-access.prepare', $this->serviceRequest))->assertNotFound();
        $this->get(route('commercial.requests.demo-access.show', $this->serviceRequest))->assertNotFound();
        Auth::logout();
        $this->post(route('commercial.requests.demo-access.prepare', $this->serviceRequest))->assertNotFound();
        $this->get(route('commercial.requests.demo-access.show', $this->serviceRequest))->assertNotFound();
        $this->assertSame(0, ClientAccessToken::count());
    }

    public function test_demo_link_expires_after_fifteen_minutes(): void
    {
        $this->actingAs($this->manager)
            ->post(route('commercial.requests.demo-access.prepare', $this->serviceRequest))
            ->assertRedirect();
        $raw = basename(parse_url(session('demo_access_url'), PHP_URL_PATH));

        Auth::logout();
        $this->travelTo(ClientAccessToken::firstOrFail()->expires_at);
        $this->post(route('client.access.consume', $raw))->assertForbidden();
        $this->assertNull(ClientAccessToken::first()->fresh()->consumed_at);
    }
}
