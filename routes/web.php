<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])
    ->name('home');

Route::get('/proyectos', [ProjectController::class, 'index'])
    ->name('projects.index');

Route::get('/proyectos/{project:slug}', [ProjectController::class, 'show'])
    ->name('projects.show');