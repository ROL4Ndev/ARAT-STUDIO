<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_uses_original_content_and_only_active_projects(): void
    {
        $category = Category::create(['name' => 'Arquitectura', 'slug' => 'arquitectura']);
        $project = Project::create(['title' => 'Proyecto visible', 'slug' => 'proyecto-visible', 'category_id' => $category->id, 'description' => 'Descripción del proyecto', 'cover_image' => 'img/LOGO.png', 'is_active' => true]);
        Project::create(['title' => 'Proyecto oculto', 'slug' => 'proyecto-oculto', 'category_id' => $category->id, 'description' => 'No debe aparecer', 'cover_image' => 'img/LOGO.png', 'is_active' => false]);
        $response = $this->get('/')->assertOk()->assertSee('Angelo Trinidad')->assertSee('Luz Nicole Arana Peláez')
            ->assertSee('Modelado BIM')->assertDontSee('Diseño de Muebles en Melamina')->assertDontSee('Diagramación de Paneles')
            ->assertSee('mailto:aratstudio.pe@gmail.com')->assertSee('https://wa.me/51918985143')
            ->assertSee(route('service-requests.create'))->assertSee(route('projects.show', $project))
            ->assertDontSee('Proyecto oculto')->assertDontSee('href="#"', false);
        $this->assertCount(1, $response->viewData('projects'));
        $this->assertTrue($response->viewData('projects')->first()->relationLoaded('projectCategory'));
    }

    public function test_empty_home_and_session_navigation(): void
    {
        $this->get('/')->assertOk()->assertSee('Próximamente compartiremos nuestros proyectos.')->assertSee('Ingresar')->assertDontSee('Cerrar sesión');
        $user = User::factory()->create();
        $user->forceFill(['role' => 'gestor'])->save();
        $this->actingAs($user)->get('/')->assertOk()->assertSee('Mi perfil')->assertSee('Cerrar sesión')->assertSee(route('management.dashboard'))->assertDontSee(route('admin.dashboard'));
        $user->forceFill(['role' => 'admin'])->save();
        $this->get('/')->assertOk()->assertSee(route('admin.dashboard'))->assertSee(route('management.dashboard'));
    }
}
