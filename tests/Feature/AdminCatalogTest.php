<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Category;
use App\Models\OfferedService;
use App\Models\Project;
use App\Models\TariffParam;
use App\Models\User;
use App\Services\AuditService;
use App\Services\CatalogWorkflowService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('commercial');
        Storage::fake('public');
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function category(): Category
    {
        $this->post(route('admin.categories.store'), ['name' => 'Categoría de ensayo', 'reason' => 'Alta para prueba aislada'])->assertSessionHasNoErrors()->assertRedirect();

        return Category::latest('id')->first();
    }

    private function data(Category $c): array
    {
        return ['title' => 'Proyecto de ensayo', 'description' => 'Descripción suficientemente detallada del proyecto.', 'category_id' => $c->id, 'is_active' => '1', 'reason' => 'Alta o edición en prueba aislada'];
    }

    public function test_category_crud_has_consultation_immutable_slug_and_status_instead_of_delete(): void
    {
        $c = $this->category();
        $slug = $c->slug;
        $this->get(route('admin.categories.show', $c))->assertOk();
        $this->get(route('admin.categories.edit', $c))->assertOk();
        $this->put(route('admin.categories.update', $c), ['name' => 'Nuevo nombre', 'lock_version' => $c->lock_version, 'reason' => 'Renombrado sin alterar slug'])->assertSessionHasNoErrors()->assertRedirect();
        $c->refresh();
        $this->assertSame($slug, $c->slug);
        foreach ([0, 1] as $active) {
            $this->post(route('admin.categories.status', $c), ['active' => $active, 'lock_version' => $c->lock_version, 'reason' => 'Cambio de estado de ensayo'])->assertSessionHasNoErrors();
            $c->refresh();
            $this->assertSame((bool) $active, $c->is_active);
        }
        $this->delete('/admin/categorias/'.$c->id)->assertStatus(405);
        $this->assertDatabaseCount('categories', 1);
    }

    public function test_unique_names_length_and_generated_slugs(): void
    {
        foreach (['Café', 'Cafe'] as $name) {
            $this->post(route('admin.categories.store'), ['name' => $name, 'reason' => 'Prueba de slug único'])->assertSessionHasNoErrors();
        }
        $this->assertSame(2, Category::distinct()->count('slug'));
        foreach (['Café', 'A', str_repeat('x', 101)] as $name) {
            $this->post(route('admin.categories.store'), ['name' => $name, 'reason' => 'Datos inválidos de prueba'])->assertSessionHasErrors('name');
        }
    }

    public function test_project_upload_edit_history_and_search(): void
    {
        $c = $this->category();
        $data = $this->data($c);
        $this->post(route('admin.projects.store'), $data + ['cover_image' => UploadedFile::fake()->image('cover.png')])->assertSessionHasNoErrors()->assertRedirect();
        $p = Project::firstOrFail();
        $slug = $p->slug;
        $old = $p->cover_image;
        Storage::disk('public')->assertExists($old);
        $this->get(route('admin.projects.show', $p))->assertOk()->assertSee('Portadas conservadas');
        $this->get(route('admin.projects.edit', $p))->assertOk();
        $this->put(route('admin.projects.update', $p), [...$data, 'title' => 'Proyecto renovado', 'lock_version' => $p->lock_version, 'cover_image' => UploadedFile::fake()->image('new.png')])->assertSessionHasNoErrors();
        $p->refresh();
        $this->assertSame($slug, $p->slug);
        Storage::disk('public')->assertMissing($old);
        Storage::disk('commercial')->assertExists($p->coverHistories()->where('source_path', $old)->firstOrFail()->retired_source_path);
        $this->assertCount(2, $p->coverHistories);
        $this->get(route('admin.projects.index', ['q' => 'renovado']))->assertOk()->assertSee('Proyecto renovado');
        $this->get('/proyectos')->assertSee($p->cover_image_url);
    }

    public function test_inactive_category_allows_draft_but_not_publication(): void
    {
        $c = $this->category();
        $c->update(['is_active' => false]);
        $data = $this->data($c);
        $this->post(route('admin.projects.store'), $data + ['cover_image' => UploadedFile::fake()->image('c.png')])->assertSessionHasErrors('category_id');
        $this->assertDatabaseCount('projects', 0);
        $this->post(route('admin.projects.store'), [...$data, 'is_active' => 0, 'cover_image' => UploadedFile::fake()->image('draft.png')])->assertSessionHasNoErrors();
        $p = Project::first();
        $this->get('/proyectos/'.$p->slug)->assertNotFound();
    }

    public function test_invalid_category_description_and_cover_content_or_size_are_rejected(): void
    {
        $c = $this->category();
        $data = $this->data($c);
        $this->post(route('admin.projects.store'), [...$data, 'description' => 'Corta', 'category_id' => 999999, 'cover_image' => UploadedFile::fake()->createWithContent('fake.png', 'not an image')])->assertSessionHasErrors(['description', 'category_id', 'cover_image']);
        $this->post(route('admin.projects.store'), $data + ['cover_image' => UploadedFile::fake()->image('large.png')->size(5121)])->assertSessionHasErrors('cover_image');
    }

    public function test_failed_project_update_preserves_confirmed_cover_and_record(): void
    {
        $c = $this->category();
        $data = $this->data($c);
        $this->post(route('admin.projects.store'), $data + ['cover_image' => UploadedFile::fake()->image('original.png')]);
        $p = Project::first();
        $old = $p->cover_image;
        Project::updating(fn () => throw new \RuntimeException('database failure'));
        try {
            app(CatalogWorkflowService::class)->save('projects', $p, $data + ['lock_version' => $p->lock_version], UploadedFile::fake()->image('replacement.png'), auth()->user());
            $this->fail('Se esperaba fallo de guardado');
        } catch (\RuntimeException $e) {
            $this->assertSame('database failure', $e->getMessage());
        } finally {
            Project::flushEventListeners();
        }
        Storage::disk('public')->assertExists($old);
        $this->assertSame($old, $p->fresh()->cover_image);
        $this->assertCount(1, Storage::disk('public')->allFiles());
    }

    public function test_services_and_tariffs_roundtrip_with_policies_and_filters(): void
    {
        $service = ['code' => 'NUEVO_SERVICIO', 'name' => 'Servicio de prueba', 'description' => 'Descripción del servicio para evaluación manual.', 'audience' => 'estudiantes', 'display_order' => 20, 'estimation_mode' => 'manual', 'reason' => 'Alta de catálogo para ensayo'];
        $this->post(route('admin.services.store'), $service)->assertSessionHasNoErrors();
        $s = OfferedService::where('code', 'NUEVO_SERVICIO')->first();
        $this->get(route('admin.services.show', $s))->assertOk();
        $this->get(route('admin.services.edit', $s))->assertOk();
        unset($service['code']);
        $this->put(route('admin.services.update', $s), $service + ['lock_version' => $s->lock_version])->assertSessionHasNoErrors();
        $this->get(route('admin.services.index', ['audience' => 'estudiantes']))->assertSee('Servicio de prueba');
        $d = ['lod_level' => 'LOD_300', 'base_price_m2' => '100', 'lod_factor' => '1.35', 'max_area_m2' => '500', 'reason' => 'Tarifa de ensayo inicial'];
        $this->post(route('admin.tariffs.store'), $d)->assertSessionHasNoErrors();
        $t = TariffParam::latest('id')->first();
        $this->get(route('admin.tariffs.show', $t))->assertOk();
        $this->get(route('admin.tariffs.edit', $t))->assertOk();
        $this->post(route('admin.tariffs.status', $t), ['active' => 1, 'reason' => 'Activar tarifa de prueba', 'lock_version' => $t->lock_version, 'expected_active_id' => null])->assertSessionHasNoErrors();
    }

    public function test_audit_is_append_only_and_scrubs_sensitive_keys(): void
    {
        $c = $this->category();
        app(AuditService::class)->record($c, 'test', 'Auditoría de prueba', ['password' => 'no', 'token_hash' => 'no'], ['name' => 'permitido']);
        $event = AuditEvent::latest('id')->first();
        $this->assertSame([], $event->before);
        $this->expectException(QueryException::class);
        DB::table('audit_events')->where('id', $event->id)->update(['reason' => 'Alteración']);
    }
}
