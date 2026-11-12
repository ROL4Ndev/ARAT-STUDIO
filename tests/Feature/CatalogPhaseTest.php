<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\OfferedService;
use App\Models\User;
use App\Services\CatalogWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CatalogPhaseTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('commercial');
        Storage::fake('public');
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function save(string $kind, array $data, $record = null, $file = null)
    {
        return app(CatalogWorkflowService::class)->save($kind, $record, $data + ['reason' => 'Prueba aislada del catálogo', 'lock_version' => $record?->lock_version ?? 0], $file, $this->admin);
    }

    public function test_four_catalogs_are_admin_only_and_have_no_delete_routes(): void
    {
        foreach (['categories', 'projects', 'services', 'tariffs'] as $kind) {
            $this->actingAs($this->admin)->get(route('admin.'.$kind.'.index'))->assertOk();
        }
        $this->actingAs(User::factory()->create(['role' => 'gestor']));
        foreach (['categories', 'projects', 'services', 'tariffs'] as $kind) {
            $this->get(route('admin.'.$kind.'.index'))->assertForbidden();
        }
        foreach (app('router')->getRoutes() as $route) {
            $this->assertNotContains('DELETE', $route->methods());
        }
    }

    public function test_category_slug_lock_and_dependency_block_are_preserved(): void
    {
        $c = $this->save('categories', ['name' => 'Categoría inicial']);
        $slug = $c->slug;
        $this->save('categories', ['name' => 'Categoría renombrada'], $c);
        $this->assertSame($slug, $c->fresh()->slug);
        $this->actingAs($this->admin)->put(route('admin.categories.update', $c->id), ['name' => 'Otra categoría', 'reason' => 'Cambio atrasado', 'lock_version' => 0])->assertStatus(409);
        $c->refresh();
        $p = $this->save('projects', ['title' => 'Proyecto de prueba', 'description' => 'Descripción suficiente para comprobar el proyecto.', 'category_id' => $c->id, 'is_active' => true], null, UploadedFile::fake()->image('portada.png'));
        $this->actingAs($this->admin)->post(route('admin.categories.status', $c->id), ['active' => 0, 'reason' => 'Intento con dependencias', 'lock_version' => $c->lock_version])->assertSessionHasErrors('active');
        $this->assertTrue($p->fresh()->is_active);
        app(CatalogWorkflowService::class)->status($p, false, $p->lock_version, 'Desactivar para prueba', $this->admin);
        app(CatalogWorkflowService::class)->status($c, false, $c->lock_version, 'Desactivar categoría', $this->admin);
        $this->assertFalse($c->fresh()->is_active);
        $this->get('/proyectos/'.$p->slug)->assertNotFound();
    }

    public function test_service_codes_are_immutable_and_imports_do_not_assume_equivalence(): void
    {
        $this->assertDatabaseCount('services', 12);
        $this->assertSame(4, OfferedService::where('is_active', true)->count());
        $service = $this->save('services', ['code' => 'SERVICIO_PRUEBA', 'name' => 'Servicio manual', 'description' => 'Descripción del servicio manual para evaluación.', 'audience' => 'general', 'display_order' => 5, 'estimation_mode' => 'manual']);
        $this->assertFalse($service->is_active);
        $this->actingAs($this->admin)->put(route('admin.services.update', $service->id), ['code' => 'ALTERADO', 'lock_version' => 0, 'reason' => 'Intento de cambio'])->assertSessionHasErrors('code');
    }

    public function test_published_tariff_edit_creates_version_and_empty_group_can_be_activated(): void
    {
        $v1 = $this->save('tariffs', ['lod_level' => 'LOD_300', 'base_price_m2' => '100', 'lod_factor' => '1.35', 'max_area_m2' => '500']);
        $workflow = app(CatalogWorkflowService::class);
        $workflow->status($v1, true, 0, 'Publicar primera versión', $this->admin);
        $v1->refresh();
        $v2 = $this->save('tariffs', ['base_price_m2' => '110', 'lod_factor' => '1.35', 'max_area_m2' => '500'], $v1);
        $this->assertSame('100.00', $v1->fresh()->base_price_m2);
        $this->assertSame(2, $v2->version);
        $this->assertSame($v1->id, $v1->group->active_tariff_id);
        $workflow->status($v1, false, $v1->lock_version, 'Retirar tarifa vigente', $this->admin);
        $this->assertNull($v1->group()->first()->active_tariff_id);
        $workflow->status($v2, true, $v2->lock_version, 'Activar nueva versión', $this->admin);
        $this->assertSame($v2->id, $v2->group()->first()->active_tariff_id);
    }

    public function test_replacing_cover_keeps_private_history_and_original_file(): void
    {
        $c = $this->save('categories', ['name' => 'Categoría para portadas']);
        $data = ['title' => 'Proyecto con portadas', 'description' => 'Descripción suficiente para conservar dos portadas.', 'category_id' => $c->id, 'is_active' => true];
        $p = $this->save('projects', $data, null, UploadedFile::fake()->image('primera.png'));
        $old = $p->cover_image;
        $this->save('projects', $data, $p, UploadedFile::fake()->image('segunda.png'));
        Storage::disk('public')->assertMissing($old);
        $history = $p->coverHistories()->where('source_path', $old)->firstOrFail();
        Storage::disk('commercial')->assertExists($history->retired_source_path);
        $this->assertSame($history->sha256, hash_file('sha256', Storage::disk('commercial')->path($history->retired_source_path)));
        $this->assertCount(2, $p->coverHistories);
        foreach ($p->coverHistories as $history) {
            Storage::disk('commercial')->assertExists($history->private_path);
        }
        $this->assertGreaterThanOrEqual(3, AuditEvent::count());
    }
}
