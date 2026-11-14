<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Project;
use App\Models\User;
use App\Services\CatalogWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ExistingProjectCoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_existing_asset_replaces_uploaded_cover_with_private_history_and_no_duplicate_project(): void
    {
        Storage::fake('public');
        Storage::fake('commercial');
        $actor = User::factory()->create(['role' => 'admin']);
        $category = Category::create(['name' => 'Remodelación', 'slug' => 'remodelacion', 'is_active' => true]);
        $data = ['title' => 'Departamento MR', 'description' => 'Departamento con cocina y comedor integrados.', 'category_id' => $category->id, 'is_active' => true, 'reason' => 'Portada del proyecto de prueba'];
        $service = app(CatalogWorkflowService::class);
        $project = $service->save('projects', null, $data, UploadedFile::fake()->image('anterior.png'), $actor);
        $old = $project->cover_image;
        $asset = 'img/Departamento MR/1.webp';
        $hash = hash_file('sha256', public_path($asset));

        $updated = $service->save('projects', $project, $data + ['lock_version' => $project->lock_version], null, $actor, $asset);

        $this->assertSame($project->id, $updated->id);
        $this->assertDatabaseCount('projects', 1);
        $this->assertSame($asset, $updated->cover_image);
        $this->assertSame(asset($asset), $updated->cover_image_url);
        $this->assertSame($hash, hash_file('sha256', public_path($asset)));
        $previous = $project->coverHistories()->where('source_path', $old)->sole();
        Storage::disk('commercial')->assertExists($previous->private_path);
        Storage::disk('commercial')->assertExists($previous->retired_source_path);
        $current = $project->coverHistories()->where('source_path', $asset)->sole();
        $this->assertSame($hash, hash_file('sha256', Storage::disk('commercial')->path($current->private_path)));

        foreach ([route('home'), route('projects.index'), route('projects.show', $updated)] as $url) {
            $this->get($url)->assertOk()->assertSee(asset($asset))->assertDontSee('/storage/img/');
        }
        $updated->update(['is_active' => false]);
        $this->get(route('projects.show', $updated))->assertNotFound();
        $this->get(route('home'))->assertDontSee('Departamento MR');

        $uploaded = new Project(['cover_image' => 'project-covers/subida.webp']);
        $this->assertSame(Storage::disk('public')->url('project-covers/subida.webp'), $uploaded->cover_image_url);
    }

    public function test_existing_cover_cannot_reference_a_file_outside_public_images(): void
    {
        $actor = User::factory()->create(['role' => 'admin']);
        $this->expectException(ValidationException::class);
        app(CatalogWorkflowService::class)->save('projects', null, [], null, $actor, 'img/../../.env');
    }
}
