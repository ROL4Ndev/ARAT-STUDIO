<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\OfferedService;
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
        $project = Project::create(['title' => 'Proyecto visible', 'slug' => 'proyecto-visible', 'category_id' => $category->id, 'description' => 'Descripción del proyecto', 'cover_image' => 'img/LOGO.png', 'is_active' => true, 'is_featured' => true]);
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
        $this->get('/')->assertOk()->assertSee('Explora nuestros proyectos')->assertSee('Acceso al equipo')->assertSee('Consultar mi expediente')->assertDontSee('Cerrar sesión');
        $user = User::factory()->create();
        $user->forceFill(['role' => 'gestor'])->save();
        $this->actingAs($user)->get('/')->assertOk()->assertSee('Mi perfil')->assertSee('Cerrar sesión')->assertSee(route('panel'))->assertDontSee(route('admin.dashboard'));
        $user->forceFill(['role' => 'admin'])->save();
        $this->get('/')->assertOk()->assertSee(route('panel'))->assertSee('Volver al panel');
    }

    public function test_public_pages_reuse_navigation_configuration_and_active_service_catalog(): void
    {
        OfferedService::create([
            'code' => 'PUBLIC_ACTIVE',
            'name' => 'Servicio público activo',
            'description' => 'Descripción del servicio publicado.',
            'audience' => 'general',
            'display_order' => 1,
            'estimation_mode' => 'manual',
            'is_active' => true,
        ]);
        OfferedService::create([
            'code' => 'PUBLIC_INACTIVE',
            'name' => 'Servicio privado inactivo',
            'description' => 'No debe mostrarse públicamente.',
            'audience' => 'general',
            'display_order' => 2,
            'estimation_mode' => 'manual',
            'is_active' => false,
        ]);

        $this->get(route('about'))
            ->assertOk()
            ->assertSeeText('Sobre el estudio')
            ->assertSeeText('Angelo Trinidad')
            ->assertSee(route('services'))
            ->assertSee(route('contact'));
        $this->get(route('services'))
            ->assertOk()
            ->assertSeeText('Servicio público activo')
            ->assertDontSeeText('Servicio privado inactivo')
            ->assertSee(route('service-requests.create'));
        $this->get(route('contact'))
            ->assertOk()
            ->assertSeeText(config('studio.email'))
            ->assertSeeText(config('studio.phone'))
            ->assertSee(config('studio.whatsapp'))
            ->assertDontSee('<form', false);

        $this->get('/')
            ->assertOk()
            ->assertSee('id="sobre"', false)
            ->assertSee('id="servicios"', false)
            ->assertSee('id="equipo"', false)
            ->assertSee('id="proyectos"', false)
            ->assertSee('id="contacto"', false);
    }

    public function test_navigation_has_one_tree_and_groups_public_and_internal_access(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//*[@id="site-navigation"]')->length);
        $this->assertSame(1, $xpath->query('//nav[@aria-label="Navegación principal"]')->length);
        $this->assertSame(0, $xpath->query('//nav[contains(@class,"home-section-navigation")]')->length);
        $this->assertSame(3, $xpath->query('//*[@id="site-navigation"]/details[summary="Estudio"]//a')->length);
        $this->assertSame(0, $xpath->query('//*[@id="site-navigation"]//a[@href="'.route('login').'"]')->length);
        $this->assertSame(1, $xpath->query('//footer//a[@href="'.route('login').'"][text()="Acceso al equipo"]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="site-navigation"]/a[text()="Consultar mi expediente"]')->length);
        $this->assertSame(1, $xpath->query('//*[@id="site-navigation"]/a[contains(@class,"site-navigation__primary")]')->length);

        foreach (['admin', 'gestor', null] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $response = $this->actingAs($user)->get('/')->assertOk()
                ->assertSeeText('Mi cuenta')->assertSeeText('Mi perfil')->assertSeeText('Cerrar sesión')
                ->assertSee(route('client.access'));
            if ($role !== 'admin') {
                $response->assertDontSee(route('admin.dashboard'));
            }
            if ($role === null) {
                $response->assertDontSee(route('panel'));
            }
        }
    }

    public function test_about_only_illustrates_the_practice_with_a_published_project(): void
    {
        $category = Category::create(['name' => 'Interiores', 'slug' => 'interiores']);
        $project = Project::create([
            'title' => 'Proyecto Sala T', 'slug' => 'proyecto-sala-t', 'category_id' => $category->id,
            'description' => 'Una sala con materialidad y luz natural.', 'cover_image' => 'img/Proyecto Sala T/1.webp',
            'is_active' => false,
        ]);
        $this->get('/nosotros')->assertOk()->assertDontSee('Vista interior de Proyecto Sala T');
        $project->update(['is_active' => true]);
        $this->get('/nosotros')->assertOk()->assertSee('Vista interior de Proyecto Sala T')
            ->assertSee('Contactar por WhatsApp')->assertSee(route('service-requests.create'));
        $category->update(['is_active' => false]);
        $this->get('/nosotros')->assertOk()->assertDontSee('Vista interior de Proyecto Sala T')
            ->assertSeeText(config('studio.team.0.qualification'))
            ->assertSeeText(config('studio.team.1.qualification'));
    }
}
