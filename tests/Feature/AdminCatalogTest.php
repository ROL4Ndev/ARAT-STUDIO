<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminCatalogTest extends TestCase
{
    use RefreshDatabase;

    private string $password;

    protected function setUp(): void
    {
        parent::setUp();

        $this->password = Str::random(32);
        Storage::fake('public');
    }

    public function test_admin_can_create_update_and_delete_categories(): void
    {
        $admin = $this->internalUser('admin');

        $this->actingAs($admin)->post(route('admin.categories.store'), [
            'name' => 'Diseño de interiores',
        ])->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas('success');

        $category = Category::query()->where('name', 'Diseño de interiores')->firstOrFail();
        $this->assertSame('diseno-de-interiores', $category->slug);

        $this->actingAs($admin)->put(route('admin.categories.update', $category), [
            'name' => 'Diseño interior',
        ])->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Diseño interior',
            'slug' => 'diseno-interior',
        ]);

        $this->actingAs($admin)->delete(route('admin.categories.destroy', $category->fresh()))
            ->assertRedirect(route('admin.categories.index'));

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    public function test_category_validation_requires_unique_name_within_limit(): void
    {
        $admin = $this->internalUser('admin');
        Category::create(['name' => 'Existente', 'slug' => 'existente']);

        $this->actingAs($admin)->from(route('admin.categories.create'))
            ->post(route('admin.categories.store'), ['name' => ''])
            ->assertRedirect(route('admin.categories.create'))
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)->from(route('admin.categories.create'))
            ->post(route('admin.categories.store'), ['name' => 'Existente'])
            ->assertRedirect(route('admin.categories.create'))
            ->assertSessionHasErrors('name');

        $this->actingAs($admin)->from(route('admin.categories.create'))
            ->post(route('admin.categories.store'), ['name' => Str::random(101)])
            ->assertRedirect(route('admin.categories.create'))
            ->assertSessionHasErrors('name');
    }

    public function test_category_slugs_are_generated_and_unique(): void
    {
        $admin = $this->internalUser('admin');

        foreach (['Café', 'Cafe'] as $name) {
            $this->actingAs($admin)->post(route('admin.categories.store'), [
                'name' => $name,
            ])->assertRedirect(route('admin.categories.index'));
        }

        $this->assertDatabaseHas('categories', ['name' => 'Café', 'slug' => 'cafe']);
        $this->assertDatabaseHas('categories', ['name' => 'Cafe', 'slug' => 'cafe-2']);
    }

    public function test_category_in_use_cannot_be_deleted(): void
    {
        $admin = $this->internalUser('admin');
        $category = Category::create(['name' => 'Arquitectura', 'slug' => 'arquitectura']);
        $this->createProject($category);

        $this->actingAs($admin)
            ->from(route('admin.categories.index'))
            ->delete(route('admin.categories.destroy', $category))
            ->assertRedirect(route('admin.categories.index'))
            ->assertSessionHas('error', 'No se puede eliminar esta categoría porque tiene proyectos asociados.');

        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_admin_can_create_and_edit_project_with_public_storage_cover(): void
    {
        $admin = $this->internalUser('admin');
        $category = Category::create(['name' => 'Interiores', 'slug' => 'interiores']);
        $cover = $this->validPng();

        $this->actingAs($admin)->post(route('admin.projects.store'), [
            'title' => 'Sala contemporánea',
            'category_id' => $category->id,
            'description' => 'Diseño interior con luz natural.',
            'cover_image' => $cover,
            'is_active' => '1',
        ])->assertRedirect(route('admin.projects.index'))
            ->assertSessionHas('success');

        $project = Project::query()->where('title', 'Sala contemporánea')->firstOrFail();
        $this->assertSame('sala-contemporanea', $project->slug);
        $this->assertSame($category->id, $project->category_id);
        Storage::disk('public')->assertExists($project->cover_image);

        $oldPath = $project->cover_image;
        $originalSlug = $project->slug;
        $newCover = $this->validPng('replacement.png');

        $this->actingAs($admin)->put(route('admin.projects.update', $project), [
            'title' => 'Sala renovada',
            'category_id' => $category->id,
            'description' => 'Descripción actualizada.',
            'cover_image' => $newCover,
        ])->assertRedirect(route('admin.projects.index'));

        $project->refresh();
        $this->assertSame($originalSlug, $project->slug);
        $this->assertFalse($project->is_active);
        $this->assertSame('Sala renovada', $project->title);
        $this->assertNotSame($oldPath, $project->cover_image);
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($project->cover_image);

        $this->get(route('projects.index'))->assertOk()->assertDontSee('Sala renovada');
        $this->get(route('projects.show', $project->slug))->assertNotFound();
    }

    public function test_new_project_slug_is_unique_and_uploaded_cover_is_publicly_rendered(): void
    {
        $admin = $this->internalUser('admin');
        $category = Category::create(['name' => 'Vivienda', 'slug' => 'vivienda']);

        foreach (['Casa nueva', 'Casa nueva'] as $index => $title) {
            $this->actingAs($admin)->post(route('admin.projects.store'), [
                'title' => $title,
                'category_id' => $category->id,
                'description' => 'Descripción del proyecto '.$index,
                'cover_image' => $this->validPng('casa-'.$index.'.png'),
                'is_active' => '1',
            ])->assertRedirect(route('admin.projects.index'));
        }

        $projects = Project::query()->where('title', 'Casa nueva')->orderBy('id')->get();
        $this->assertSame(['casa-nueva', 'casa-nueva-2'], $projects->pluck('slug')->all());

        $response = $this->get(route('projects.index'))->assertOk();
        $response->assertSee($projects[0]->cover_image_url);
        $response->assertSee($category->name);
        Storage::disk('public')->assertExists($projects[0]->cover_image);
    }

    public function test_project_admin_list_searches_by_title_and_shows_project_details(): void
    {
        $admin = $this->internalUser('admin');
        $category = Category::create(['name' => 'Comercial', 'slug' => 'comercial']);
        $match = $this->createProject($category);
        $match->update(['title' => 'Local comercial']);
        $other = $this->createProject($category);
        $other->update(['title' => 'Casa familiar']);

        $this->actingAs($admin)
            ->get(route('admin.projects.index', ['q' => 'comercial']))
            ->assertOk()
            ->assertSee('Local comercial')
            ->assertDontSee('Casa familiar');

        $this->get(route('admin.projects.show', $match))
            ->assertOk()
            ->assertSee('Local comercial')
            ->assertSee('Comercial');
    }

    public function test_replacing_a_legacy_catalog_image_does_not_delete_it(): void
    {
        $admin = $this->internalUser('admin');
        $category = Category::create(['name' => 'Legado', 'slug' => 'legado']);
        $project = $this->createProject($category);
        $project->update(['cover_image' => 'img/LOGO.png']);
        $legacyImage = public_path('img/LOGO.png');

        $this->assertFileExists($legacyImage);

        $this->actingAs($admin)->put(route('admin.projects.update', $project), [
            'title' => $project->title,
            'category_id' => $category->id,
            'description' => $project->description,
            'cover_image' => $this->validPng('nueva-portada.png'),
            'is_active' => '1',
        ])->assertRedirect(route('admin.projects.index'));

        $this->assertFileExists($legacyImage);
        Storage::disk('public')->assertExists($project->fresh()->cover_image);
    }

    public function test_failed_project_update_keeps_old_record_and_cleans_uploaded_file(): void
    {
        $admin = $this->internalUser('admin');
        $category = Category::create(['name' => 'Guardado', 'slug' => 'guardado']);
        $oldPath = 'project-covers/portada-existente.png';
        Storage::disk('public')->put($oldPath, 'existing cover');
        $project = $this->createProject($category);
        $project->update(['cover_image' => $oldPath]);

        Project::updating(function (): void {
            throw new \RuntimeException('Fallo de guardado para la prueba.');
        });

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($admin)->put(route('admin.projects.update', $project), [
                'title' => 'Cambio no guardado',
                'category_id' => $category->id,
                'description' => 'Descripción nueva.',
                'cover_image' => $this->validPng('subida-temporal.png'),
                'is_active' => '1',
            ]);

            $this->fail('Se esperaba que el error de guardado se propagara.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Fallo de guardado para la prueba.', $exception->getMessage());
        } finally {
            Project::flushEventListeners();
        }

        $this->assertSame($oldPath, $project->fresh()->cover_image);
        Storage::disk('public')->assertExists($oldPath);
        $this->assertSame([$oldPath], Storage::disk('public')->allFiles('project-covers'));
    }

    public function test_project_validation_rejects_missing_category_and_unsupported_or_oversized_images(): void
    {
        $admin = $this->internalUser('admin');

        $this->actingAs($admin)->from(route('admin.projects.create'))
            ->post(route('admin.projects.store'), [
                'title' => 'Proyecto inválido',
                'category_id' => 99999,
                'description' => 'Descripción.',
                'cover_image' => $this->validPng(),
            ])->assertRedirect(route('admin.projects.create'))
            ->assertSessionHasErrors('category_id');

        $category = Category::create(['name' => 'Casa', 'slug' => 'casa']);

        $this->actingAs($admin)->from(route('admin.projects.create'))
            ->post(route('admin.projects.store'), [
                'title' => 'Archivo incompatible',
                'category_id' => $category->id,
                'description' => 'Descripción.',
                'cover_image' => UploadedFile::fake()->create('portada.gif', 20, 'image/gif'),
            ])->assertRedirect(route('admin.projects.create'))
            ->assertSessionHasErrors('cover_image');

        $this->actingAs($admin)->from(route('admin.projects.create'))
            ->post(route('admin.projects.store'), [
                'title' => 'Archivo demasiado grande',
                'category_id' => $category->id,
                'description' => 'Descripción.',
                'cover_image' => UploadedFile::fake()->create('portada.jpg', 5121, 'image/jpeg'),
            ])->assertRedirect(route('admin.projects.create'))
            ->assertSessionHasErrors('cover_image');
    }

    public function test_guest_and_gestor_cannot_access_any_catalog_operation(): void
    {
        $category = Category::create(['name' => 'Protegida', 'slug' => 'protegida']);
        $project = $this->createProject($category);

        $routes = [
            ['GET', route('admin.categories.index')],
            ['GET', route('admin.categories.create')],
            ['GET', route('admin.categories.edit', $category)],
            ['POST', route('admin.categories.store')],
            ['PUT', route('admin.categories.update', $category)],
            ['DELETE', route('admin.categories.destroy', $category)],
            ['GET', route('admin.projects.index')],
            ['GET', route('admin.projects.create')],
            ['GET', route('admin.projects.show', $project)],
            ['GET', route('admin.projects.edit', $project)],
            ['POST', route('admin.projects.store')],
            ['PUT', route('admin.projects.update', $project)],
        ];

        foreach ($routes as [$method, $uri]) {
            $this->call($method, $uri)->assertRedirect('/ingresar');
        }

        $gestor = $this->internalUser('gestor');
        $this->actingAs($gestor);

        foreach ($routes as [$method, $uri]) {
            $this->call($method, $uri)->assertForbidden();
        }
    }

    public function test_catalog_seeders_work_without_legacy_category_column(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertFalse(Schema::hasColumn('projects', 'category'));
        $this->assertSame(3, Project::query()->count());
        $this->assertSame(4, Category::query()->count());
        $this->assertSame(0, Project::query()->whereNull('category_id')->count());
        $this->assertDatabaseHas('projects', [
            'slug' => 'proyecto-sala-t',
            'category_id' => Category::query()->where('name', 'Diseño interior')->value('id'),
        ]);
    }

    public function test_transition_migration_preserves_legacy_project_categories(): void
    {
        Schema::table('projects', function ($table): void {
            $table->foreignId('category_id')->nullable()->change();
            $table->string('category')->nullable();
        });

        $category = Category::create(['name' => 'Categoría antigua', 'slug' => 'categoria-heredada']);
        $projectId = DB::table('projects')->insertGetId([
            'title' => 'Proyecto heredado',
            'slug' => 'proyecto-heredado',
            'category' => 'Categoría heredada',
            'category_id' => null,
            'description' => 'Proyecto existente.',
            'cover_image' => 'img/proyecto-antiguo.webp',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $migration = require database_path('migrations/2026_11_11_160000_finalize_project_categories_transition.php');
        $migration->up();

        $this->assertFalse(Schema::hasColumn('projects', 'category'));
        $this->assertDatabaseHas('projects', ['id' => $projectId]);
        $this->assertSame(
            'Categoría heredada',
            Category::query()->findOrFail(DB::table('projects')->where('id', $projectId)->value('category_id'))->name
        );
        $this->assertSame('categoria-heredada', $category->fresh()->slug);
        $this->assertDatabaseHas('categories', [
            'name' => 'Categoría heredada',
            'slug' => 'categoria-heredada-2',
        ]);
        $categoryColumn = collect(Schema::getColumns('projects'))->firstWhere('name', 'category_id');
        $this->assertFalse($categoryColumn['nullable']);
    }

    private function internalUser(string $role): User
    {
        $user = User::factory()->create(['password' => $this->password]);
        $user->forceFill(['role' => $role])->save();

        return $user;
    }

    private function createProject(Category $category): Project
    {
        return Project::create([
            'title' => 'Proyecto de prueba',
            'slug' => 'proyecto-de-prueba-'.Str::lower(Str::random(8)),
            'category_id' => $category->id,
            'description' => 'Descripción de prueba.',
            'cover_image' => 'img/proyecto-de-prueba.webp',
            'is_active' => true,
        ]);
    }

    private function validPng(string $name = 'portada.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l9sAAAAASUVORK5CYII=')
        );
    }
}
