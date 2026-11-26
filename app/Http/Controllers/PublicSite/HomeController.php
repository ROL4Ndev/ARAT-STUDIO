<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\PublicServicePresentation;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(PublicServicePresentation $presentation): View
    {
        $projects = Project::published()
            ->with(['projectCategory', 'coverMedia'])
            ->where('is_featured', true)
            ->orderBy('featured_order')
            ->orderBy('id')
            ->get();

        $services = $presentation->groups();

        return view('home', compact('projects', 'services'));
    }

    public function about(): View
    {
        $studioProject = Project::published()
            ->with(['projectCategory', 'coverMedia'])
            ->where('slug', 'proyecto-sala-t')
            ->first();

        return view('public.about', compact('studioProject'));
    }

    public function services(PublicServicePresentation $presentation): View
    {
        $services = $presentation->groups();

        return view('public.services', compact('services'));
    }

    public function contact(): View
    {
        return view('public.contact');
    }
}
