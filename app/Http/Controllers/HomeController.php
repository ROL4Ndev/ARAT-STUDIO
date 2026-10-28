<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $projects = Project::query()
            ->orderBy('id')
            ->limit(3)
            ->get();

        return view('home', compact('projects'));
    }
}