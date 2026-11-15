<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Project;
use App\Models\ProjectImage;
use App\Models\User;
use App\Services\PortfolioInventory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProjectGalleryTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploads_reuse_originals_and_thumbnails_by_hash_and_reject_fake_images(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Galerías', 'slug' => 'galerias']);
        $project = Project::create([
            'title' => 'Proyecto de subidas', 'slug' => 'subidas', 'description' => 'Descripción confirmada para probar la galería.',
            'category_id' => $category->id, 'cover_image' => 'img/Proyecto Sala T/1.webp', 'is_active' => true,
        ]);
        $source = public_path('img/Proyecto Sala T/2.webp');
        $originalHash = hash_file('sha256', $source);
        $upload = fn (string $name) => new UploadedFile($source, $name, 'image/webp', null, true);
        $this->actingAs($admin)->post(route('admin.gallery.add', $project->id), [
            'lock_version' => 0, 'reason' => 'Subir la misma fotografía con nombres distintos',
            'images' => [$upload('interior.webp'), $upload('interior-repetido.webp')],
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(1, $project->images()->count());
        $image = $project->images()->first();
        $this->assertSame($originalHash, hash_file('sha256', Storage::disk('public')->path($image->path)));
        $this->assertCount(1, Storage::disk('public')->files('project-gallery'));
        $this->assertCount(1, Storage::disk('public')->files('project-thumbnails'));
        $this->post(route('admin.gallery.add', $project->id), [
            'lock_version' => $project->fresh()->lock_version, 'reason' => 'Rechazar un archivo que solo simula ser imagen',
            'images' => [UploadedFile::fake()->create('falso.webp', 5, 'image/webp')],
        ])->assertSessionHasErrors('images.0');
        $this->post(route('admin.gallery.add', $project->id), [
            'lock_version' => $project->fresh()->lock_version, 'reason' => 'Rechazar una carpeta fuera del inventario',
            'source_folder' => '../fuera-del-proyecto',
        ])->assertSessionHasErrors('source_folder');
        $this->assertSame(1, $project->images()->count());
        $this->assertSame($originalHash, hash_file('sha256', $source));
    }

    public function test_gallery_deduplication_order_cover_history_privacy_and_original_preservation(): void
    {
        Storage::fake('public');
        Storage::fake('commercial');
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Interiores', 'slug' => 'interiores']);
        $project = Project::create([
            'title' => 'Proyecto con galería', 'slug' => 'con-galeria', 'description' => 'Descripción confirmada del proyecto de interiores.',
            'category_id' => $category->id, 'cover_image' => 'img/Proyecto Sala T/1.webp', 'is_active' => true,
        ]);
        $files = glob(public_path('img/Proyecto Sala T/*.webp'));
        $originalHashes = array_map(fn ($file) => hash_file('sha256', $file), $files);
        $data = ['source_folder' => 'Proyecto Sala T', 'lock_version' => 0, 'reason' => 'Vincular fotografías originales del proyecto'];
        $this->actingAs(User::factory()->create(['role' => 'gestor']))->post(route('admin.gallery.add', $project->id), $data)->assertForbidden();
        $this->actingAs($admin)->post(route('admin.gallery.add', $project->id), $data)->assertSessionHasNoErrors()->assertRedirect();
        $project->refresh();
        $this->assertSame(count($files), $project->images()->count());
        foreach ($project->images as $image) {
            Storage::disk('public')->assertExists($image->thumbnail_path);
            $size = getimagesize(Storage::disk('public')->path($image->thumbnail_path));
            $this->assertLessThanOrEqual(480, $size[0]);
            $this->assertSame(IMAGETYPE_WEBP, $size[2]);
        }
        $this->post(route('admin.gallery.add', $project->id), [...$data, 'lock_version' => $project->lock_version])->assertSessionHasNoErrors();
        $this->assertSame(count($files), ProjectImage::count());
        $images = $project->images()->get();
        $positions = $images->reverse()->values()->mapWithKeys(fn ($image, $index) => [$image->id => $index])->all();
        $version = $project->fresh()->lock_version;
        $this->post(route('admin.gallery.order', $project->id), ['lock_version' => $version, 'positions' => $positions, 'reason' => 'Mostrar primero la última fotografía'])->assertSessionHasNoErrors();
        $this->assertSame($images->last()->id, $project->images()->first()->id);
        $this->post(route('admin.gallery.order', $project->id), ['lock_version' => $version, 'positions' => $positions, 'reason' => 'Guardar desde una vista anterior'])->assertStatus(409);
        $newCover = $images->last();
        $this->post(route('admin.gallery.cover', [$project->id, $newCover]), ['lock_version' => $project->fresh()->lock_version, 'reason' => 'Cambiar la portada del proyecto'])->assertSessionHasNoErrors();
        $this->assertSame($newCover->path, $project->fresh()->cover_image);
        $this->assertTrue($project->coverHistories()->exists());
        $this->post(route('admin.gallery.status', [$project->id, $newCover]), ['lock_version' => $project->fresh()->lock_version, 'reason' => 'Intentar archivar la portada', 'is_active' => false])->assertSessionHasErrors('image');
        $archived = $images->first();
        $this->post(route('admin.gallery.status', [$project->id, $archived]), ['lock_version' => $project->fresh()->lock_version, 'reason' => 'Retirar una imagen conservando su original', 'is_active' => false])->assertSessionHasNoErrors();
        $this->get(route('projects.show', $project))->assertOk()->assertDontSee('href="'.$archived->url.'"', false)->assertSee('data-gallery-image', false);
        $other = $project->replicate();
        $other->slug = 'otro-proyecto';
        $other->save();
        $this->post(route('admin.gallery.cover', [$other->id, $newCover]), ['lock_version' => 0, 'reason' => 'Intentar portada ajena al proyecto'])->assertNotFound();
        $this->assertSame($originalHashes, array_map(fn ($file) => hash_file('sha256', $file), $files));
    }

    public function test_inventory_is_read_only_and_import_is_idempotent_and_creates_only_drafts(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Por revisar', 'slug' => 'por-revisar']);
        $existing = Project::create(['title' => 'Título editorial existente', 'slug' => 'titulo-propio', 'category_id' => $category->id, 'description' => 'Conservar la descripción editorial existente.', 'cover_image' => 'img/Proyecto Sala T/1.webp', 'is_active' => true]);
        $before = $existing->fresh()->only(['title', 'slug', 'description', 'is_active', 'cover_image']);
        $inventory = app(PortfolioInventory::class)->inspect();
        $this->assertSame(13, $inventory['folder_count']);
        $this->assertSame(100, $inventory['image_count']);
        $this->artisan('portfolio:incorporar')->assertSuccessful();
        $this->assertSame(1, Project::count());
        Storage::fake('public');
        $options = ['--apply' => true, '--category' => $category->id, '--actor' => $admin->id];
        $this->artisan('portfolio:incorporar', $options)->assertSuccessful();
        $this->assertSame(13, Project::count());
        $this->assertSame(12, Project::where('is_active', false)->where('is_featured', false)->count());
        $this->assertSame($before, $existing->fresh()->only(array_keys($before)));
        $count = ProjectImage::count();
        $this->artisan('portfolio:incorporar', $options)->assertSuccessful();
        $this->assertSame(13, Project::count());
        $this->assertSame($count, ProjectImage::count());
        $this->assertSame($before, $existing->fresh()->only(array_keys($before)));
    }
}
