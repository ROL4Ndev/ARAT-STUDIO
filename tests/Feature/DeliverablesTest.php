<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Deliverable;
use App\Models\DeliverableVersion;
use App\Models\User;
use App\Services\AuditService;
use App\Services\ClientResponseService;
use App\Services\DeliverableWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\CommercialFixtures;
use Tests\TestCase;

class DeliverablesTest extends TestCase
{
    use CommercialFixtures, RefreshDatabase;

    private $r;
    private $owner;
    private $quote;
    private $grant;
    private $raw;
    private string $requestHash;
    private string $quoteHash;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('commercial');
        Mail::fake();
        $this->tariff();
        $this->owner = User::factory()->create(['role' => 'gestor']);
        $this->r = $this->registerRequest();
        $this->approve($this->r, $this->owner);
        $this->quote = $this->emit($this->draft($this->r, $this->owner), $this->owner);
        [$this->grant, $this->raw] = $this->clientGrant($this->r);
        app(ClientResponseService::class)->respond($this->quote, $this->grant, ['decision' => 'aceptada', 'lock_version' => $this->quote->lock_version]);
        $this->requestHash = hash('sha256', serialize($this->r->fresh()->getRawOriginal()));
        $this->quoteHash = hash('sha256', serialize($this->quote->fresh()->getRawOriginal()));
    }

    private function fields(array $extra = []): array
    {
        return $extra + ['title' => 'Distribución de demostración', 'description' => 'Documento académico para revisar los ambientes.',
            'internal_note' => 'NOTA INTERNA NO PUBLICABLE', 'operation_key' => (string) Str::uuid()];
    }

    private function pdf(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('distribucion-academica.pdf', Storage::disk('commercial')->get($this->quote->pdf_path));
    }

    private function delivery(?array $fields = null, bool $file = true): Deliverable
    {
        return app(DeliverableWorkflowService::class)->create($this->r, $this->owner,
            ($fields ?? $this->fields()) + ['quotation_code' => $this->quote->code], $file ? $this->pdf() : null);
    }

    private function send(Deliverable $d): void
    {
        app(DeliverableWorkflowService::class)->send($d, $this->owner, $d->latestVersion->number, $d->fresh()->lock_version);
        $d->refresh();
    }

    private function client(): void
    {
        Auth::logout();
        $this->withSession(['client_grant' => $this->raw]);
    }

    private function conflict(callable $action, int $code = 409): void
    {
        try { $action(); $this->fail('La operación debía rechazarse.'); }
        catch (HttpException $error) { $this->assertSame($code, $error->getStatusCode()); }
        catch (\Illuminate\Auth\Access\AuthorizationException $error) { $this->assertSame(403, $code); }
    }

    public function test_complete_document_flow_preserves_accepted_quote_and_commercial_closure(): void
    {
        $this->actingAs($this->owner)->get(route('commercial.deliverables.create', $this->r))->assertOk();
        $this->post(route('commercial.deliverables.store', $this->r), $this->fields() + ['quotation_code' => $this->quote->code, 'attachment' => $this->pdf()])->assertRedirect();
        $d = Deliverable::firstOrFail();
        $this->client();
        $this->get(route('client.dossier'))->assertOk()->assertDontSeeText($d->latestVersion->title)->assertDontSeeText('NOTA INTERNA NO PUBLICABLE');
        $this->get(route('client.deliverables.download', [$d, 1]))->assertForbidden();
        $this->actingAs($this->owner)->post(route('commercial.deliverables.send', [$d, 1]), ['lock_version' => $d->lock_version])->assertRedirect();
        $this->client();
        $this->get(route('client.dossier'))->assertOk()->assertSeeText($d->latestVersion->title)->assertDontSeeText('NOTA INTERNA NO PUBLICABLE')
            ->assertDontSee('id="corregir-solicitud"', false)->assertDontSee('Confirmar cancelación');
        $this->get(route('client.deliverables.download', [$d, 1]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->post(route('client.deliverables.respond', [$d, 1]), ['decision' => 'observado', 'comment' => '', 'lock_version' => $d->fresh()->lock_version])
            ->assertRedirect(route('client.dossier').'#entregables')->assertSessionHasErrors('comment');
        $this->post(route('client.deliverables.respond', [$d, 1]), ['decision' => 'observado', 'comment' => 'Precisar las medidas del área de trabajo.', 'lock_version' => $d->fresh()->lock_version])->assertRedirect();
        $this->assertSame('observado', $d->fresh()->status);
        $this->actingAs($this->owner)->get(route('commercial.deliverables.show', $d))->assertOk()->assertSee('Precisar las medidas');
        $fields = $this->fields(['title' => 'Distribución ajustada', 'lock_version' => $d->fresh()->lock_version]);
        $this->post(route('commercial.deliverables.save', $d), $fields + ['attachment' => $this->pdf()])->assertRedirect();
        $this->client();
        $this->get(route('client.dossier'))->assertOk()->assertDontSee('Distribución ajustada');
        $this->get(route('client.deliverables.download', [$d, 2]))->assertForbidden();
        $this->actingAs($this->owner)->post(route('commercial.deliverables.send', [$d, 2]), ['lock_version' => $d->fresh()->lock_version])->assertRedirect();
        $this->client();
        $this->post(route('client.deliverables.respond', [$d, 2]), ['decision' => 'conforme', 'confirmation' => 1, 'lock_version' => $d->fresh()->lock_version])->assertRedirect();
        $this->get(route('client.dossier'))->assertOk()->assertSee('Conforme')->assertDontSee('Dar conformidad');
        foreach ([1, 2] as $n) $this->get(route('client.deliverables.download', [$d, $n]))->assertOk();
        $this->assertSame('conforme', $d->fresh()->status);
        $this->assertSame($this->requestHash, hash('sha256', serialize($this->r->fresh()->getRawOriginal())));
        $this->assertSame($this->quoteHash, hash('sha256', serialize($this->quote->fresh()->getRawOriginal())));
        Mail::assertNothingSent();
    }

    public function test_create_send_version_and_response_retries_are_idempotent_and_opposite_response_conflicts(): void
    {
        $fields = $this->fields();
        $d = $this->delivery($fields);
        $audit = AuditEvent::count();
        $this->assertSame($d->id, $this->delivery($fields)->id);
        $this->assertSame($audit, AuditEvent::count());
        $this->conflict(fn () => $this->delivery(array_replace($fields, ['title' => 'Otros datos para la misma operación'])));
        $this->send($d); $this->send($d);
        $data = ['decision' => 'observado', 'comment' => 'Revisar el detalle de distribución.', 'lock_version' => $d->fresh()->lock_version];
        $flow = app(DeliverableWorkflowService::class);
        $flow->respond($d, $this->grant, 1, $data);
        $audit = AuditEvent::count();
        $flow->respond($d, $this->grant, 1, $data);
        $this->assertSame($audit, AuditEvent::count());
        $this->conflict(fn () => $flow->respond($d, $this->grant, 1, ['decision' => 'conforme', 'confirmation' => 1, 'lock_version' => $d->fresh()->lock_version]));
        $save = $this->fields(['lock_version' => $d->fresh()->lock_version]);
        $v = $flow->saveDraft($d, $this->owner, $save, $this->pdf());
        $this->assertSame($v->id, $flow->saveDraft($d, $this->owner, $save, $this->pdf())->id);
        $this->assertSame(2, $d->versions()->count());
        $flow->send($d, $this->owner, 2, $d->fresh()->lock_version);
        $this->conflict(fn () => $flow->respond($d, $this->grant, 1, $data));
        $flow->respond($d, $this->grant, 2, ['decision' => 'conforme', 'confirmation' => 1, 'lock_version' => $d->fresh()->lock_version]);
        $this->conflict(fn () => $flow->saveDraft($d, $this->owner, $this->fields(['lock_version' => $d->fresh()->lock_version]), $this->pdf()));
    }

    public function test_staff_scope_is_current_after_reassignment_and_account_deactivation(): void
    {
        $d = $this->delivery();
        $other = User::factory()->create(['role' => 'gestor']);
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($other)->get(route('commercial.deliverables.show', $d))->assertForbidden();
        $this->get(route('commercial.deliverables.download', [$d, 1]))->assertForbidden();
        $this->actingAs($admin)->get(route('commercial.deliverables.show', $d))->assertOk();
        $this->r->update(['assigned_to' => $other->id]);
        $this->actingAs($this->owner)->get(route('commercial.deliverables.show', $d))->assertForbidden();
        $this->actingAs($other)->get(route('commercial.deliverables.show', $d))->assertOk();
        $other->forceFill(['is_active' => false])->save();
        $this->conflict(fn () => app(DeliverableWorkflowService::class)->send($d, $other, 1, $d->lock_version), 403);
        $this->actingAs($other)->get(route('commercial.deliverables.download', [$d, 1]))->assertForbidden();
    }

    public function test_rejects_unaccepted_or_foreign_quotation_and_internal_impersonation(): void
    {
        $other = $this->registerRequest();
        $this->approve($other, $this->owner);
        $q = $this->draft($other, $this->owner);
        $flow = app(DeliverableWorkflowService::class);
        $this->conflict(fn () => $flow->create($other, $this->owner, $this->fields() + ['quotation_code' => $q->code]));
        $this->conflict(fn () => $flow->create($other, $this->owner, $this->fields() + ['quotation_code' => $this->quote->code]));
        $d = $this->delivery(); $this->send($d);
        $this->actingAs($this->owner)->withSession(['client_grant' => $this->raw])
            ->post(route('client.deliverables.respond', [$d, 1]), ['decision' => 'conforme', 'confirmation' => 1, 'lock_version' => $d->fresh()->lock_version])->assertForbidden();
    }

    public function test_expired_revoked_and_foreign_grants_cannot_download_or_respond(): void
    {
        $d = $this->delivery(); $this->send($d); $this->client();
        $this->grant->update(['expires_at' => now('UTC')->subSecond()]);
        $this->get(route('client.deliverables.download', [$d, 1]))->assertForbidden();
        $this->post(route('client.deliverables.respond', [$d, 1]), ['decision' => 'observado', 'comment' => 'Acceso vencido.', 'lock_version' => 1])->assertForbidden();
        $this->grant->update(['expires_at' => now('UTC')->addMinutes(30), 'revoked_at' => now('UTC')]);
        $this->get(route('client.deliverables.download', [$d, 1]))->assertForbidden();
        $this->post(route('client.deliverables.respond', [$d, 1]), ['decision' => 'observado', 'comment' => 'Acceso revocado.', 'lock_version' => 1])->assertForbidden();
        [$otherGrant, $otherRaw] = $this->clientGrant($this->registerRequest());
        $this->withSession(['client_grant' => $otherRaw])->get(route('client.deliverables.download', [$d, 1]))->assertForbidden();
        $this->conflict(fn () => app(DeliverableWorkflowService::class)->respond($d, $otherGrant, 1,
            ['decision' => 'conforme', 'confirmation' => 1, 'lock_version' => $d->fresh()->lock_version]), 403);
    }

    public function test_draft_without_file_cannot_send_and_invalid_uploads_are_rejected(): void
    {
        $d = $this->delivery(file: false);
        $this->actingAs($this->owner)->post(route('commercial.deliverables.send', [$d, 1]), ['lock_version' => 0])->assertSessionHasErrors('attachment');
        foreach ([UploadedFile::fake()->createWithContent('falso.pdf', '<?php echo 1;'),
            UploadedFile::fake()->create('prohibido.zip', 12, 'application/zip'),
            UploadedFile::fake()->create('excesivo.pdf', 10241, 'application/pdf'),
            UploadedFile::fake()->createWithContent('incompleto.pdf', '%PDF-1.4 sin cierre')] as $file) {
            $this->post(route('commercial.deliverables.save', $d), $this->fields(['lock_version' => 0]) + ['attachment' => $file])->assertSessionHasErrors('attachment');
        }
        $this->assertNull($d->latestVersion->path);
        $this->assertSame(1, $d->versions()->count());
    }

    public function test_observation_comment_and_conformity_confirmation_are_required(): void
    {
        $d = $this->delivery(); $this->send($d); $this->client();
        $this->post(route('client.deliverables.respond', [$d, 1]), ['decision' => 'observado', 'comment' => '  ', 'lock_version' => $d->fresh()->lock_version])->assertSessionHasErrors('comment');
        $this->post(route('client.deliverables.respond', [$d, 1]), ['decision' => 'conforme', 'lock_version' => $d->fresh()->lock_version])->assertSessionHasErrors('confirmation');
        $this->assertNull($d->versions()->first()->decision);
    }

    public function test_rollback_cleans_only_new_upload_and_preserves_previous_draft_and_sent_file(): void
    {
        $d = $this->delivery();
        $files = Storage::disk('commercial')->allFiles();
        $this->mock(AuditService::class)->shouldReceive('record')->andThrow(new \RuntimeException('Fallo de auditoría inyectado'));
        try {
            app(DeliverableWorkflowService::class)->saveDraft($d, $this->owner, $this->fields(['lock_version' => 0]), $this->pdf());
            $this->fail('Debió fallar la auditoría.');
        } catch (\RuntimeException $error) { $this->assertSame('Fallo de auditoría inyectado', $error->getMessage()); }
        $this->assertSame($files, Storage::disk('commercial')->allFiles());
        $this->assertSame(0, $d->fresh()->lock_version);
        $this->assertSame(1, $d->versions()->count());
        try { $this->delivery(); $this->fail('Debió fallar la creación.'); } catch (\RuntimeException) {}
        $this->assertSame($files, Storage::disk('commercial')->allFiles());
        $this->assertSame(1, Deliverable::count());
    }

    public function test_tampered_file_is_not_sent_or_downloaded(): void
    {
        $d = $this->delivery();
        Storage::disk('commercial')->put($d->latestVersion->path, 'alterado');
        $this->actingAs($this->owner)->post(route('commercial.deliverables.send', [$d, 1]), ['lock_version' => 0])->assertSessionHasErrors('attachment');
        $this->get(route('commercial.deliverables.download', [$d, 1]))->assertStatus(409)->assertDontSee(Storage::disk('commercial')->path('deliverables'));
    }

    public function test_demo_command_is_explicit_reproducible_and_does_not_modify_original_records(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->r->update(['demo_batch' => 'arat-2026-v1', 'demo_scenario' => 'aceptada']);
        $requestBefore = $this->r->fresh()->getRawOriginal();
        $quoteBefore = $this->quote->fresh()->getRawOriginal();
        $this->artisan('demo:deliverables')->assertSuccessful();
        $this->assertSame(0, Deliverable::count());
        $this->artisan('demo:deliverables', ['--apply' => true, '--actor' => $admin->id])->assertSuccessful();
        $this->assertSame(['conforme', 'en_revision', 'en_revision'], Deliverable::orderBy('status')->pluck('status')->all());
        $this->assertSame(4, DeliverableVersion::count());
        $before = [Deliverable::all()->toJson(), DeliverableVersion::all()->toJson(), AuditEvent::count(), Storage::disk('commercial')->allFiles()];
        $this->artisan('demo:deliverables', ['--apply' => true, '--actor' => $admin->id])->assertSuccessful();
        $this->assertSame($before, [Deliverable::all()->toJson(), DeliverableVersion::all()->toJson(), AuditEvent::count(), Storage::disk('commercial')->allFiles()]);
        $this->assertSame($requestBefore, $this->r->fresh()->getRawOriginal());
        $this->assertSame($quoteBefore, $this->quote->fresh()->getRawOriginal());
        foreach (DeliverableVersion::all() as $version) {
            $bytes = Storage::disk('commercial')->get($version->path);
            $this->assertStringStartsWith('%PDF-', $bytes);
            $this->assertStringContainsString('%%EOF', $bytes);
            $this->assertSame($version->sha256, hash('sha256', $bytes));
        }
        Mail::assertNothingSent();
    }

    public function test_failed_send_and_response_roll_back_without_losing_the_file(): void
    {
        $d = $this->delivery();
        $files = Storage::disk('commercial')->allFiles();
        $this->mock(AuditService::class)->shouldReceive('record')->andThrow(new \RuntimeException('Fallo controlado'));
        try { $this->send($d); $this->fail('Debió fallar el envío.'); } catch (\RuntimeException) {}
        $this->assertNull($d->versions()->first()->sent_at);
        $this->assertSame('borrador', $d->fresh()->status);
        $this->assertSame($files, Storage::disk('commercial')->allFiles());
        $this->app->forgetInstance(AuditService::class);
        $this->send($d);
        $this->mock(AuditService::class)->shouldReceive('record')->andThrow(new \RuntimeException('Fallo controlado'));
        try {
            app(DeliverableWorkflowService::class)->respond($d, $this->grant, 1, ['decision' => 'conforme', 'confirmation' => true, 'lock_version' => $d->lock_version]);
            $this->fail('Debió fallar la respuesta.');
        } catch (\RuntimeException) {}
        $this->assertNull($d->versions()->first()->decision);
        $this->assertSame('en_revision', $d->fresh()->status);
        $this->assertSame($files, Storage::disk('commercial')->allFiles());
    }

    public function test_png_and_jpeg_are_valid_and_a_misnamed_image_is_rejected(): void
    {
        $flow = app(DeliverableWorkflowService::class);
        $png = UploadedFile::fake()->image('lamina-academica.png', 80, 60);
        $d = $flow->create($this->r, $this->owner, $this->fields() + ['quotation_code' => $this->quote->code], $png);
        $this->assertSame('image/png', $d->latestVersion->mime);
        $this->send($d);
        $this->client();
        $this->get(route('client.deliverables.download', [$d, 1]))->assertOk()->assertHeader('Content-Type', 'image/png');
        $jpeg = UploadedFile::fake()->createWithContent('lamina-academica.jpeg', file_get_contents(base_path('tests/Fixtures/deliverable-academic.jpg')));
        $other = $flow->create($this->r, $this->owner, $this->fields() + ['quotation_code' => $this->quote->code], $jpeg);
        $this->assertSame('image/jpeg', $other->latestVersion->mime);
        $spoof = UploadedFile::fake()->createWithContent('imagen-disfrazada.pdf', $png->get());
        try {
            $flow->create($this->r, $this->owner, $this->fields() + ['quotation_code' => $this->quote->code], $spoof);
            $this->fail('Debió rechazarse la extensión falsa.');
        } catch (ValidationException $error) { $this->assertArrayHasKey('attachment', $error->errors()); }
    }

    public function test_sent_content_and_conformity_cannot_be_edited_directly(): void
    {
        $d = $this->delivery(); $this->send($d);
        $v = $d->versions()->first();
        try { $v->update(['title' => 'Contenido modificado']); $this->fail('Se modificó una versión enviada.'); }
        catch (\LogicException) { $this->assertNotSame('Contenido modificado', $v->fresh()->title); }
        app(DeliverableWorkflowService::class)->respond($d, $this->grant, 1, ['decision' => 'conforme', 'confirmation' => true, 'lock_version' => $d->fresh()->lock_version]);
        try { $d->fresh()->update(['status' => 'borrador']); $this->fail('Se reabrió un entregable conforme.'); }
        catch (\LogicException) { $this->assertSame('conforme', $d->fresh()->status); }
    }
}
