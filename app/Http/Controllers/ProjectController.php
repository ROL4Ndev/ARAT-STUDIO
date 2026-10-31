<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\View\View;

class ProjectController extends Controller
{
    public function index(): View
    {
        $projects = Project::query()
            ->orderBy('id')
            ->paginate(9);

        return view('projects.index', compact('projects'));
    }

    public function show(Project $project): View
    {
        return view('projects.show', compact('project'));
    }
}