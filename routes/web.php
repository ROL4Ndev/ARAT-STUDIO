<?php

use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\ProjectController as AdminProjectController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicSite\HomeController;
use App\Http\Controllers\PublicSite\ProjectController;
use App\Http\Controllers\PublicSite\QuotationCalculatorController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/ingresar', [AuthenticatedSessionController::class, 'create'])
        ->name('login');
    Route::post('/ingresar', [AuthenticatedSessionController::class, 'store'])
        ->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/salir', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    Route::get('/perfil', [ProfileController::class, 'edit'])
        ->name('profile.edit');
    Route::put('/perfil', [ProfileController::class, 'update'])
        ->name('profile.update');
    Route::put('/perfil/contrasena', [ProfileController::class, 'updatePassword'])
        ->name('profile.password.update');

    Route::get('/panel', [DashboardController::class, 'redirect'])
        ->middleware('role:admin,gestor')
        ->name('panel');

    Route::get('/admin', [DashboardController::class, 'admin'])
        ->middleware('role:admin')
        ->name('admin.dashboard');

    Route::get('/gestion', [DashboardController::class, 'management'])
        ->middleware('role:admin,gestor')
        ->name('management.dashboard');
});

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'role:admin'])
    ->group(function () {
        Route::resource('categorias', AdminCategoryController::class)
            ->except('show')
            ->parameters(['categorias' => 'category'])
            ->names('categories');

        Route::resource('proyectos', AdminProjectController::class)
            ->except('destroy')
            ->parameters(['proyectos' => 'project'])
            ->names('projects');
    });

Route::get('/', [HomeController::class, 'index'])
    ->name('home');

Route::get('/proyectos', [ProjectController::class, 'index'])
    ->name('projects.index');

Route::get('/proyectos/{project:slug}', [ProjectController::class, 'show'])
    ->name('projects.show');

Route::get('/cotizador', [QuotationCalculatorController::class, 'show'])
    ->name('quotation.show');
