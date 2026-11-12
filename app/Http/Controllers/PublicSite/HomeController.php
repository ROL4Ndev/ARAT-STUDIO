<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\OfferedService;
use App\Models\Project;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $projects = Project::query()
            ->with('projectCategory')
            ->where('is_active', true)
            ->whereHas('projectCategory', fn ($q) => $q->where('is_active', true))
            ->orderBy('id')
            ->limit(3)
            ->get();

        $services = OfferedService::where('is_active', true)->orderBy('display_order')->orderBy('id')->get()->groupBy(fn ($s) => $s->audience === 'estudiantes' ? 'Servicios para estudiantes' : 'Servicios generales')->map(fn ($items) => $items->map(fn ($s) => ['name' => $s->name, 'description' => $s->description])->all())->all();

        return view('home', compact('projects', 'services'));
    }
}
