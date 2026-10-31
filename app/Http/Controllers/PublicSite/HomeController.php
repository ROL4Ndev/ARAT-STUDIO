<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;

use App\Models\Project;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $projects = Project::query()
            ->with('projectCategory')
            ->where('is_active', true)
            ->orderBy('id')
            ->limit(3)
            ->get();

        return view('home', compact('projects'));
    }
}