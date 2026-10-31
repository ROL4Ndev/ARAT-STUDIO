<?php

use App\Http\Controllers\PublicSite\HomeController;
use App\Http\Controllers\PublicSite\ProjectController;
use App\Http\Controllers\PublicSite\QuotationCalculatorController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])
    ->name('home');

Route::get('/proyectos', [ProjectController::class, 'index'])
    ->name('projects.index');

Route::get('/proyectos/{project:slug}', [ProjectController::class, 'show'])
    ->name('projects.show');

Route::get('/cotizador', [QuotationCalculatorController::class, 'show'])
    ->name('quotation.show');