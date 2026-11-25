<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Project;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPortfolioIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_incomplete_projects_and_drafts_are_excluded_from_related_projects_and_public_routes(): void
    {
        $category = Category::create(['name' => 'Interiores', 'slug' => 'interiores']);
        $attributes = ['category_id' => $category->id, 'description' => 'Descripción completa del diseño interior de un departamento.', 'cover_image' => 'img/LOGO.png', 'is_active' => true, 'is_featured' => true];
        $main = Project::create(['title' => 'Sala principal', 'slug' => 'sala-principal'] + $attributes);
        $related = Project::create(['title' => 'Sala relacionada', 'slug' => 'sala-relacionada'] + $attributes);
        $draft = Project::create(['title' => 'Sala reservada', 'slug' => 'sala-reservada', 'is_active' => false] + $attributes);
        $incomplete = Project::create(['title' => 'Sala incompleta', 'slug' => 'sala-incompleta', 'cover_image' => ''] + $attributes);
        foreach (['/', '/proyectos', '/proyectos/'.$main->slug] as $path) {
            $this->get($path)->assertOk()->assertSee($related->title)->assertDontSee($draft->title)->assertDontSee($incomplete->title);
        }
        $this->get('/proyectos/'.$main->slug)->assertSeeText('Proyectos relacionados');
        $this->get('/proyectos/'.$draft->slug)->assertNotFound();
        $this->get('/proyectos/'.$incomplete->slug)->assertNotFound();
        $category->update(['is_active' => false]);
        $this->get('/proyectos/'.$main->slug)->assertNotFound();
        $this->get('/proyectos')->assertOk()->assertDontSee($main->title)->assertDontSee($related->title);
    }
}
