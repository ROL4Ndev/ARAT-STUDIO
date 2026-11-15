<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PortfolioSelectionTest extends TestCase
{
    use RefreshDatabase;

    private function project(Category $category, int $index, array $extra = []): Project
    {
        return Project::create($extra + [
            'category_id' => $category->id, 'title' => 'Proyecto '.$index, 'slug' => 'proyecto-'.$index,
            'description' => 'Descripción editorial confirmada del proyecto.', 'cover_image' => 'img/LOGO.png',
            'is_active' => true, 'is_featured' => true, 'featured_order' => $index,
        ]);
    }

    public function test_home_uses_configured_order_without_fixed_limit_and_keeps_visibility_rules(): void
    {
        $category = Category::create(['name' => 'Arquitectura', 'slug' => 'arquitectura']);
        for ($index = 1; $index <= 5; $index++) {
            $this->project($category, $index);
        }
        Project::where('slug', 'proyecto-5')->update(['featured_order' => 0]);
        $this->project($category, 6, ['is_featured' => false]);
        $hidden = $this->project($category, 7, ['is_active' => false]);
        $privateCategory = Category::create(['name' => 'Privada', 'slug' => 'privada', 'is_active' => false]);
        $private = $this->project($privateCategory, 8);

        $response = $this->get('/')->assertOk();
        $this->assertSame([5, 1, 2, 3, 4], $response->viewData('projects')->pluck('id')->all());
        $this->get(route('projects.show', $hidden))->assertNotFound();
        $this->get(route('projects.show', $private))->assertNotFound();
        $portfolio = $this->get(route('projects.index'))->assertOk()->viewData('projects');
        $this->assertSame(6, $portfolio->total());
    }

    public function test_all_published_projects_are_paginated_and_filtered_and_empty_featured_state_links_to_portfolio(): void
    {
        $category = Category::create(['name' => 'Arquitectura', 'slug' => 'arquitectura']);
        for ($index = 1; $index <= 13; $index++) {
            $this->project($category, $index, ['is_featured' => false]);
        }
        $first = $this->get('/proyectos')->assertOk()->viewData('projects');
        $this->assertCount(9, $first);
        $this->assertSame(13, $first->total());
        $this->assertCount(4, $this->get('/proyectos?page=2&category=arquitectura')->assertOk()->viewData('projects'));
        $this->get('/')->assertOk()->assertSee('Próximamente compartiremos nuestros proyectos.')->assertSee(route('projects.index'));
    }

    public function test_featured_controls_require_admin_and_card_queries_do_not_grow_or_write_per_project(): void
    {
        $category = Category::create(['name' => 'Arquitectura', 'slug' => 'arquitectura']);
        $project = $this->project($category, 1);
        $data = $project->only(['title', 'description', 'category_id', 'is_active']) + [
            'lock_version' => 0, 'reason' => 'Cambiar selección editorial de inicio', 'is_featured' => false, 'featured_order' => 12,
        ];
        $manager = User::factory()->create(['role' => 'gestor']);
        $this->actingAs($manager)->put(route('admin.projects.update', $project->id), $data)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'admin']))->put(route('admin.projects.update', $project->id), $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertFalse($project->fresh()->is_featured);
        $this->assertTrue($project->fresh()->is_active);
        $this->assertSame(12, $project->fresh()->featured_order);
        $this->app['auth']->forgetGuards();
        DB::enableQueryLog();
        $this->get('/')->assertOk();
        $smallCount = count(DB::getQueryLog());
        DB::disableQueryLog();
        for ($index = 2; $index <= 20; $index++) {
            $this->project($category, $index);
        }
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->get('/')->assertOk();
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        // Un lote de categorías y uno de portadas como máximo; nunca por tarjeta.
        $this->assertLessThanOrEqual($smallCount + 2, count($queries));
        foreach ($queries as $query) {
            $this->assertDoesNotMatchRegularExpression('/^(insert|update|delete)/i', $query['query']);
        }
    }
}
