<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Project;
use App\Services\PublicServicePresentation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudioMediaPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_video_markup_has_real_posters_and_no_initial_video_download(): void
    {
        $response = $this->get('/')->assertOk()->assertDontSee('Pexels')
            ->assertDontSee('Charlotte May')->assertDontSee('ArtHouse Studio')->assertSee('media/studio/angelo-trinidad.webp')
            ->assertSee('media/studio/nicole-arana.webp')->assertDontSee('C:\\Users\\');
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());
        $videos = $document->getElementsByTagName('video');
        $this->assertCount(2, $videos);
        foreach ($videos as $video) {
            $this->assertFalse($video->hasAttribute('src'));
            $this->assertTrue($video->hasAttribute('muted'));
            $this->assertTrue($video->hasAttribute('playsinline'));
            $this->assertSame('none', $video->getAttribute('preload'));
            $this->assertStringEndsWith('-poster.webp', $video->getAttribute('poster'));
            $this->assertStringEndsWith('.mp4', $video->getAttribute('data-src'));
        }
        $this->get('/nosotros')->assertOk()->assertSee('Especialista BIM en Edificaciones')->assertSee('Bachiller en Arquitectura y Urbanismo');
    }

    public function test_service_images_and_links_require_published_projects_and_active_categories(): void
    {
        $category = Category::create(['name' => 'Interiores', 'slug' => 'interiores']);
        $project = Project::create([
            'title' => 'Proyecto Sala T', 'slug' => 'proyecto-sala-t', 'category_id' => $category->id,
            'description' => 'Representación interior verificada del proyecto.', 'cover_image' => 'img/Proyecto Sala T/1.webp', 'is_active' => true,
        ]);
        $groups = app(PublicServicePresentation::class)->groups()->flatten(1);
        $interiors = $groups->firstWhere('name', 'Diseño de interiores');
        $this->assertSame($project->id, $interiors['project']->id);
        $this->assertSame('media/studio/servicio-interiores.webp', $interiors['image']);
        $project->update(['is_active' => false]);
        $interiors = app(PublicServicePresentation::class)->groups()->flatten(1)->firstWhere('name', 'Diseño de interiores');
        $this->assertNull($interiors['image']);
        $this->assertNull($interiors['project']);
        $project->update(['is_active' => true]);
        $category->update(['is_active' => false]);
        $this->get('/servicios')->assertOk()->assertDontSee(route('projects.show', $project))->assertDontSee('media/studio/servicio-interiores.webp');
    }

    public function test_bim_reference_is_not_presented_as_a_verified_bim_case(): void
    {
        $category = Category::create(['name' => 'Interiores', 'slug' => 'interiores']);
        Project::create([
            'title' => 'Proyecto AT', 'slug' => 'proyecto-at', 'category_id' => $category->id,
            'description' => 'Modelo tridimensional de mobiliario de estudio.', 'cover_image' => 'img/Proyecto AT/2.webp', 'is_active' => true,
        ]);
        $bim = app(PublicServicePresentation::class)->groups()->flatten(1)->firstWhere('name', 'Modelado BIM');
        $this->assertSame('media/studio/servicio-modelado.webp', $bim['image']);
        $this->assertNull($bim['project']);
        $this->assertStringContainsString('referencia visual', $bim['caption']);
        $this->get('/servicios')->assertOk()->assertDontSee('href="'.route('projects.show', ['project' => 'proyecto-at']).'"', false);
    }

    public function test_portfolio_keeps_get_fallback_and_no_pagination_field_in_filter_form(): void
    {
        $html = $this->get('/proyectos?page=2')->assertOk()->getContent();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($document);
        $this->assertSame(1, $xpath->query('//form[@data-portfolio-filter][@method="GET"]')->length);
        $this->assertSame(0, $xpath->query('//form[@data-portfolio-filter]//input[@name="page"]')->length);
        $this->assertSame(1, $xpath->query('//form[@data-portfolio-filter]//button[@data-filter-submit]')->length);
        $this->assertSame(1, $xpath->query('//select[@name="category"][@aria-describedby="category-help"]')->length);
    }
}
