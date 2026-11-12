<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ClientGrant;
use App\Models\NotificationRecord;
use App\Models\OfferedService;
use App\Models\Project;
use App\Models\TariffParam;
use App\Models\User;
use App\Models\WorkIntent;
use App\Policies\ServiceRequestPolicy;
use App\Services\AuditService;
use App\Services\CatalogWorkflowService;
use App\Services\ClientResponseService;
use App\Services\IssueQuotationService;
use App\Services\QuotationPdfService;
use App\Services\WorkProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\CommercialFixtures;
use Tests\TestCase;

class FinalReviewTest extends TestCase
{
    use CommercialFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('commercial');
        Mail::fake();
        $this->tariff();
    }

    public function test_each_catalog_completes_all_five_operations_without_decreasing_rows(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        $category = Category::create(['name' => 'Categoría para CRUD', 'slug' => 'categoria-crud', 'is_active' => true]);
        $cases = [
            'categories' => ['name' => 'Nueva categoría', 'description' => 'Descripción opcional.'],
            'projects' => ['title' => 'Proyecto de prueba', 'description' => 'Descripción suficiente del proyecto de la prueba final.', 'category_id' => $category->id, 'is_active' => 1, 'cover_image' => UploadedFile::fake()->image('cover.png')],
            'services' => ['code' => 'FINAL_TEST', 'name' => 'Servicio de revisión', 'description' => 'Descripción del servicio de revisión final.', 'audience' => 'general', 'display_order' => 9, 'estimation_mode' => 'manual'],
            'tariffs' => ['lod_level' => 'LOD_200', 'base_price_m2' => '100', 'lod_factor' => '1.100', 'max_area_m2' => '500'],
        ];
        $classes = ['categories' => Category::class, 'projects' => Project::class, 'services' => OfferedService::class, 'tariffs' => TariffParam::class];
        foreach ($cases as $kind => $data) {
            $class = $classes[$kind];
            $before = $class::count();
            $this->post(route('admin.'.$kind.'.store'), $data + ['reason' => 'Alta del ensayo final'])->assertSessionHasNoErrors()->assertRedirect();
            $item = $class::latest('id')->firstOrFail();
            $this->assertSame($before + 1, $class::count());
            $this->get(route('admin.'.$kind.'.show', $item->id))->assertOk();
            $this->get(route('admin.'.$kind.'.edit', $item->id))->assertOk();
            $edit = $data;
            unset($edit['cover_image'],$edit['code'],$edit['lod_level']);
            if ($kind === 'tariffs') {
                $edit['base_price_m2'] = '105';
            } else {
                $edit['description'] = 'Descripción editada y conservada en el ensayo final.';
            }
            $this->put(route('admin.'.$kind.'.update', $item->id), $edit + ['reason' => 'Editar para comprobar auditoría', 'lock_version' => $item->lock_version])->assertSessionHasNoErrors()->assertRedirect();
            $item->refresh();
            if (! $item->is_active) {
                $publish = ['active' => 1, 'reason' => 'Publicación inicial del borrador', 'lock_version' => $item->lock_version];
                if ($kind === 'tariffs') {
                    $publish['expected_active_id'] = $item->group->fresh()->active_tariff_id;
                }
                $this->post(route('admin.'.$kind.'.status', $item->id), $publish)->assertSessionHasNoErrors()->assertRedirect();
                $item->refresh();
                $this->assertTrue($item->is_active);
            }
            foreach ([false, true] as $active) {
                $payload = ['active' => $active ? 1 : 0, 'reason' => 'Cambio de estado comprobado', 'lock_version' => $item->lock_version];
                if ($kind === 'tariffs') {
                    $payload['expected_active_id'] = $item->group->fresh()->active_tariff_id;
                }
                $this->post(route('admin.'.$kind.'.status', $item->id), $payload)->assertSessionHasNoErrors()->assertRedirect();
                $item->refresh();
                $this->assertSame($active, $item->is_active);
                $this->assertSame($before + 1, $class::count());
            }
            $this->delete(route('admin.'.$kind.'.show', $item->id))->assertStatus(405);
            $this->assertSame($before + 1, $class::count());
        }
        $this->actingAs(User::factory()->create(['role' => 'gestor']));
        foreach (array_keys($cases) as $kind) {
            $this->post(route('admin.'.$kind.'.store'), [])->assertForbidden();
        }
    }

    public function test_issued_content_dates_and_pdf_are_immutable_even_through_the_model(): void
    {
        $manager = User::factory()->create(['role' => 'gestor']);
        $r = $this->registerRequest();
        $this->approve($r, $manager);
        $q = $this->emit($this->draft($r, $manager), $manager);
        $this->assertSame(hash('sha256', Storage::disk('commercial')->get($q->pdf_path)), $q->pdf_hash);
        foreach (['official_amount' => '1.00', 'scope' => 'Alcance alterado', 'client_snapshot' => ['name' => 'Alterado'], 'pdf_path' => 'otro.pdf', 'expires_at' => now('UTC')->addYear()] as $field => $value) {
            try {
                $q->fresh()->update([$field => $value]);
                $this->fail('Se modificó una versión emitida: '.$field);
            } catch (\LogicException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        $this->actingAs($manager)->put(route('commercial.quotations.update', $q), [...$this->quoteData($r), 'lock_version' => $q->lock_version])->assertForbidden();
        Storage::disk('commercial')->put($q->pdf_path, '%PDF-alterado');
        $this->get(route('commercial.quotations.download', $q))->assertStatus(409);
    }

    public function test_failed_v2_keeps_v1_current_until_successful_retry(): void
    {
        $u = User::factory()->create(['role' => 'gestor']);
        $r = $this->registerRequest();
        $this->approve($r, $u);
        $v1 = $this->emit($this->draft($r, $u), $u);
        $v2 = $this->draft($r, $u, '12500', $v1);
        $this->mock(QuotationPdfService::class)->shouldReceive('render')->once()->andThrow(new \RuntimeException('fallo controlado'));
        $this->emit($v2, $u);
        $this->assertSame('emitida', $v1->fresh()->status);
        $this->assertSame($v1->id, $r->fresh()->current_quotation_id);
        $this->assertSame('failed', $v2->fresh()->pdf_status);
        $this->app->forgetInstance(QuotationPdfService::class);
        $this->emit($v2, $u);
        $this->assertSame('sustituida', $v1->fresh()->status);
        $this->assertSame($v2->id, $r->fresh()->current_quotation_id);
        $this->assertDatabaseCount('quotations', 2);
    }

    public function test_stale_tariff_family_pointer_conflicts_even_if_target_version_is_unchanged(): void
    {
        $u = User::factory()->create(['role' => 'admin']);
        $w = app(CatalogWorkflowService::class);
        $base = TariffParam::first();
        $draft = $w->save('tariffs', null, ['lod_level' => 'LOD_300', 'base_price_m2' => '110', 'lod_factor' => '1.35', 'max_area_m2' => '500', 'reason' => 'Revisión de prueba'], null, $u);
        $this->actingAs($u)->post(route('admin.tariffs.status', $draft), ['active' => 1, 'lock_version' => 0, 'reason' => 'Activar con vista desactualizada', 'expected_active_id' => null])->assertStatus(409);
        $this->post(route('admin.tariffs.status', $draft), ['active' => 1, 'lock_version' => 0, 'reason' => 'Confirmar la nueva vigencia', 'expected_active_id' => $base->id])->assertRedirect();
        $this->assertSame('inactiva', $base->fresh()->status);
        try {
            $draft->fresh()->update(['base_price_m2' => '1']);
            $this->fail('Se editó una tarifa publicada.');
        } catch (\LogicException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        $draft->refresh()->update(['is_active' => false]);
        $this->assertTrue($draft->fresh()->is_active);
        $base->refresh();
        $this->post(route('admin.tariffs.status', $base), ['active' => 1, 'lock_version' => $base->lock_version, 'expected_active_id' => $draft->id, 'reason' => 'Reactivar la versión histórica confirmada'])->assertRedirect();
        $this->assertSame($base->id, $base->group()->first()->active_tariff_id);
        $this->assertSame('inactiva', $draft->fresh()->status);
    }

    public function test_notification_failure_and_retry_preserve_each_attempt_and_its_error(): void
    {
        $u = User::factory()->create(['role' => 'gestor']);
        $r = $this->registerRequest();
        $this->approve($r, $u);
        $q = $this->emit($this->draft($r, $u), $u);
        $n = NotificationRecord::where('kind', 'quotation')->firstOrFail();
        $work = WorkIntent::where('key', 'notification:'.$n->id)->firstOrFail();
        $fake = Mail::getFacadeRoot();
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp controlado'));
        app(WorkProcessor::class)->run($work->id);
        $failure = $n->deliveries()->firstOrFail();
        $this->assertSame('failed', $failure->status);
        $this->assertNotEmpty($failure->error);
        Mail::swap($fake);
        $this->actingAs($u)->post(route('commercial.notices.retry', $n), ['lock_version' => $q->lock_version])->assertRedirect();
        $this->post(route('commercial.notices.retry', $n), ['lock_version' => $q->lock_version])->assertRedirect();
        app(WorkProcessor::class)->run($work->id);
        $this->assertSame(2, $n->deliveries()->count());
        $this->assertSame('failed', $failure->fresh()->status);
        $this->assertSame('simulated', $n->deliveries()->latest('id')->first()->status);
        $this->assertDatabaseCount('quotations', 1);
        try {
            $failure->update(['status' => 'sent']);
            $this->fail('Se sobrescribió el intento fallido.');
        } catch (\LogicException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
    }

    public function test_valid_correction_resumes_evaluation_and_queues_both_required_notices(): void
    {
        $u = User::factory()->create(['role' => 'gestor']);
        $r = $this->registerRequest();
        $this->action($r, $u, 'claim');
        $this->action($r, $u, 'start');
        $this->action($r, $u, 'observe', ['public_observation' => 'Completa los planos y la distribución.']);
        [$g,$raw] = $this->clientGrant($r);
        $this->assertDatabaseHas('notification_records', ['kind' => 'observed', 'recipient' => $r->client_email]);
        $data = $this->requestData();
        unset($data['submission_token'],$data['service_id'],$data['client_email'],$data['consent'],$data['estimate_token'],$data['estimate_confirmed']);
        $this->withSession(['client_grant' => $raw])->post(route('client.correct'), $data + ['lock_version' => $r->lock_version, 'reason' => 'Corrección completa del proyecto'])->assertRedirect();
        $r->refresh();
        $this->assertSame('evaluacion', $r->status);
        $this->assertSame($g->id, $r->currentRevision->client_grant_id);
        $this->assertDatabaseHas('notification_records', ['kind' => 'corrected', 'resource_id' => $r->current_revision_id, 'recipient' => $u->email]);
    }

    public function test_staff_correction_requires_evidence_and_never_impersonates_the_client(): void
    {
        $u = User::factory()->create(['role' => 'gestor']);
        $r = $this->registerRequest();
        $this->action($r, $u, 'claim');
        $this->action($r, $u, 'start');
        $this->action($r, $u, 'observe', ['public_observation' => 'Completa el plano de distribución.']);
        $data = ['lock_version' => $r->lock_version, 'description' => 'Descripción corregida por comunicación comprobada.', 'project_type' => 'Vivienda', 'area_m2' => '80', 'lod_selected' => 'LOD_300', 'reason' => 'Registro de corrección con respaldo'];
        $url = route('commercial.requests.correct', $r);
        $this->actingAs(User::factory()->create(['role' => 'gestor']))->post($url, $data)->assertForbidden();
        $this->actingAs($u)->post($url, $data)->assertSessionHasErrors('evidence');
        $this->post($url, $data + ['evidence' => 'Comunicación ficticia del cliente, referencia DEMO-123.'])->assertSessionHasNoErrors()->assertRedirect();
        $r->refresh();
        $this->assertSame('evaluacion', $r->status);
        $this->assertSame('staff', $r->currentRevision->author_type);
        $this->assertSame($u->id, $r->currentRevision->user_id);
        $this->assertNull($r->currentRevision->client_grant_id);
    }

    public function test_first_emission_cohort_excludes_new_versions_of_old_requests(): void
    {
        $u = User::factory()->create(['role' => 'gestor']);
        $this->travelTo(now('America/Lima')->subDays(40));
        $r = $this->registerRequest();
        $this->approve($r, $u);
        $v1 = $this->emit($this->draft($r, $u), $u);
        $this->travelBack();
        $this->emit($this->draft($r, $u, '12500', $v1), $u);
        $this->actingAs($u)->get('/gestion')->assertOk()->assertViewHas('denominator', 0)->assertViewHas('issued', 1);
    }

    public function test_acceptance_one_microsecond_before_expiry_and_closed_dossier_are_read_only(): void
    {
        $u = User::factory()->create(['role' => 'gestor']);
        $r = $this->registerRequest();
        $this->approve($r, $u);
        $q = $this->emit($this->draft($r, $u), $u);
        [$g,$raw] = $this->clientGrant($r);
        $g->update(['expires_at' => $q->expires_at->addHour()]);
        $this->travelTo($q->expires_at->subMicrosecond());
        $this->withSession(['client_grant' => $raw])->post(route('client.respond', $q), ['decision' => 'aceptada', 'lock_version' => $q->lock_version])->assertRedirect();
        $this->assertNotNull($r->fresh()->closed_at);
        $this->post(route('client.email'), ['email' => 'otro@example.test'])->assertStatus(409);
        $this->get('/mi-solicitud')->assertOk()->assertDontSee('name="email"', false);
        $history = $r->histories()->where('action', 'client_response')->firstOrFail();
        $this->assertSame($g->id, $history->client_grant_id);
        $this->assertSame($q->id, $history->quotation_id);
        try {
            $history->update(['observation' => 'Alterada']);
            $this->fail('Se modificó el historial.');
        } catch (\LogicException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        try {
            $history->delete();
            $this->fail('Se borró el historial.');
        } catch (\LogicException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
    }

    public function test_revoked_grant_is_rechecked_even_if_a_service_received_an_old_instance(): void
    {
        $r = $this->registerRequest();
        [$g] = $this->clientGrant($r);
        ClientGrant::whereKey($g->id)->update(['revoked_at' => now('UTC')]);
        $this->assertFalse(app(ServiceRequestPolicy::class)->clientView($g, $r));
    }

    public function test_emission_notifies_snapshot_recipient_and_legacy_retry_returns_existing_result(): void
    {
        $u = User::factory()->create(['role' => 'gestor']);
        $r = $this->registerRequest();
        $this->approve($r, $u);
        $q = $this->draft($r, $u);
        $work = app(IssueQuotationService::class)->prepare($q, $u, $q->lock_version);
        $r->update(['client_email' => 'correo-verificado-nuevo@example.test']);
        app(WorkProcessor::class)->run($work->id);
        $q->refresh();
        $this->assertDatabaseHas('notification_records', ['kind' => 'quotation', 'resource_id' => $q->id, 'recipient' => 'cliente@example.test']);
        WorkIntent::whereKey($work->id)->delete();
        $retried = app(IssueQuotationService::class)->prepare($q, $u, $q->lock_version);
        $this->assertSame('done', $retried->status);
        $this->assertDatabaseCount('quotations', 1);
        $this->assertSame($q->pdf_path, $retried->result['generated_path']);
    }

    public function test_replaced_cover_is_private_and_failed_transaction_restores_its_public_location(): void
    {
        $u = User::factory()->create(['role' => 'admin']);
        $w = app(CatalogWorkflowService::class);
        $c = $w->save('categories', null, ['name' => 'Conservación de portada', 'reason' => 'Alta ficticia para prueba'], null, $u);
        $data = ['title' => 'Portada conservada', 'description' => 'Descripción suficiente para comprobar conservación.', 'category_id' => $c->id, 'is_active' => 1, 'reason' => 'Portada de prueba'];
        $p = $w->save('projects', null, $data, UploadedFile::fake()->image('original.png'), $u);
        $old = $p->cover_image;
        $hash = hash_file('sha256', Storage::disk('public')->path($old));
        $audit = \Mockery::mock(AuditService::class);
        $audit->shouldReceive('record')->andThrow(new \RuntimeException('Fallo posterior al traslado'));
        $broken = new CatalogWorkflowService($audit);
        try {
            $broken->save('projects', $p, $data + ['lock_version' => 0], UploadedFile::fake()->image('nueva.png'), $u);
            $this->fail('Se confirmó un guardado fallido.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Fallo posterior al traslado', $e->getMessage());
        }
        Storage::disk('public')->assertExists($old);
        $this->assertSame($hash, hash_file('sha256', Storage::disk('public')->path($old)));
        $this->assertSame($old, $p->fresh()->cover_image);
        $this->assertSame(1, $p->coverHistories()->count());
        $p = $w->save('projects', $p->fresh(), $data + ['lock_version' => 0], UploadedFile::fake()->image('definitiva.png'), $u);
        $history = $p->coverHistories()->where('source_path', $old)->firstOrFail();
        Storage::disk('public')->assertMissing($old);
        $this->assertSame($hash, hash_file('sha256', Storage::disk('commercial')->path($history->retired_source_path)));
        $this->actingAs($u)->get(route('admin.covers.download', $history))->assertDownload();
        $this->actingAs(User::factory()->create(['role' => 'gestor']))->get(route('admin.covers.download', $history))->assertForbidden();
    }

    public function test_incomplete_project_cannot_reactivate_and_published_identifiers_cannot_change(): void
    {
        $u = User::factory()->create(['role' => 'admin']);
        $c = Category::create(['name' => 'Categoría de revisión', 'slug' => 'categoria-revision', 'is_active' => true]);
        $p = Project::create(['title' => 'Proyecto sin portada', 'slug' => 'proyecto-sin-portada', 'category_id' => $c->id, 'description' => 'Descripción suficiente para la ficha del proyecto.', 'cover_image' => 'project-covers/no-existe.png', 'is_active' => false]);
        $this->actingAs($u)->post(route('admin.projects.status', $p), ['active' => 1, 'lock_version' => 0, 'reason' => 'Intento de publicación incompleta'])->assertSessionHasErrors('cover_image');
        $this->assertFalse($p->fresh()->is_active);
        foreach ([[$c, 'slug', 'alterada'], [$p, 'slug', 'alterado'], [OfferedService::first(), 'code', 'ALTERADO']] as [$record,$field,$value]) {
            try {
                $record->update([$field => $value]);
                $this->fail('Se modificó un identificador publicado.');
            } catch (\LogicException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }

    public function test_service_and_local_date_filters_apply_to_owned_queue_and_history(): void
    {
        $u = User::factory()->create(['role' => 'gestor']);
        $own = $this->registerRequest();
        $this->action($own, $u, 'claim');
        $manual = OfferedService::create(['code' => 'FILTRO_MANUAL', 'name' => 'Servicio manual de filtros', 'description' => 'Servicio ficticio para comprobar los filtros.', 'audience' => 'general', 'display_order' => 50, 'estimation_mode' => 'manual', 'is_active' => true]);
        $other = $this->registerRequest('80', null, $manual);
        $this->action($other, $u, 'claim');
        $unassigned = $this->registerRequest();
        $unassigned->update(['created_at' => now('America/Lima')->subDays(2)]);
        $date = today('America/Lima')->toDateString();
        $url = '/gestion?service_id='.$own->service_id.'&from='.$date.'&to='.$date;
        $this->actingAs($u)->get($url)->assertOk()->assertSee($own->code)->assertDontSee($other->code)->assertDontSee($unassigned->code)->assertViewHas('requests', fn ($p) => $p->total() === 1)->assertViewHas('unassigned', fn ($p) => $p->total() === 0);
    }

    public function test_close_without_agreement_requires_no_live_or_preparing_offer_and_records_reason(): void
    {
        $u = User::factory()->create(['role' => 'gestor']);
        $r = $this->registerRequest();
        $this->approve($r, $u);
        $q = $this->draft($r, $u);
        $url = route('commercial.requests.transition', $r);
        $this->actingAs($u)->patch($url, ['action' => 'close', 'lock_version' => $r->fresh()->lock_version, 'reason' => 'Intento de cierre con borrador abierto'])->assertStatus(409);
        $q = $this->emit($q, $u);
        $this->patch($url, ['action' => 'close', 'lock_version' => $r->fresh()->lock_version, 'reason' => 'Intento con oferta vigente'])->assertStatus(409);
        [$grant] = $this->clientGrant($r);
        app(ClientResponseService::class)->respond($q, $grant, ['decision' => 'rechazada', 'lock_version' => $q->lock_version, 'reason' => 'Rechazo ficticio para cierre.']);
        $this->patch($url, ['action' => 'close', 'lock_version' => $r->fresh()->lock_version, 'reason' => 'Cierre sin acuerdo comprobado'])->assertRedirect();
        $this->assertSame('sin_acuerdo', $r->fresh()->closure_outcome);
        $this->assertSame('Cierre sin acuerdo comprobado', $r->fresh()->closure_reason);
        $this->assertNotNull($r->fresh()->closed_at);
    }

    public function test_old_rejected_version_does_not_accept_an_idempotent_response_after_replacement(): void
    {
        $u = User::factory()->create(['role' => 'gestor']);
        $r = $this->registerRequest();
        $this->approve($r, $u);
        $v1 = $this->emit($this->draft($r, $u), $u);
        [$g,$raw] = $this->clientGrant($r);
        app(ClientResponseService::class)->respond($v1, $g, ['decision' => 'rechazada', 'lock_version' => $v1->lock_version, 'reason' => 'Primera respuesta ficticia.']);
        $v1->refresh();
        $this->emit($this->draft($r, $u, '12500', $v1), $u);
        $this->withSession(['client_grant' => $raw])->post(route('client.respond', $v1), ['decision' => 'rechazada', 'lock_version' => $v1->lock_version, 'reason' => 'Primera respuesta ficticia.'])->assertStatus(409);
    }
}
