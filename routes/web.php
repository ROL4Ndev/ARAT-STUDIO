<?php

use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\ProjectController as AdminProjectController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Commercial\QuotationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicSite\HomeController;
use App\Http\Controllers\PublicSite\ProjectController;
use App\Http\Controllers\PublicSite\QuotationCalculatorController;
use App\Http\Controllers\PublicSite\ServiceRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/solicitar-proyecto', [ServiceRequestController::class, 'create'])->name('service-requests.create');
Route::post('/solicitar-proyecto', [ServiceRequestController::class, 'store'])->middleware('throttle:5,1')->name('service-requests.store');
Route::get('/solicitar-proyecto/confirmacion', [ServiceRequestController::class, 'confirmation'])->name('service-requests.confirmation');

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

    Route::get('/gestion', [App\Http\Controllers\Commercial\ServiceRequestController::class, 'index'])
        ->middleware('role:admin,gestor')
        ->name('management.dashboard');
});

Route::prefix('gestion')->name('commercial.')->middleware(['auth', 'role:admin,gestor'])->group(function () {
    Route::post('/solicitudes/{serviceRequest}/cotizacion', [QuotationController::class, 'store'])->name('quotations.store');
    Route::get('/cotizaciones/{quotation}', [QuotationController::class, 'show'])->name('quotations.show');
    Route::get('/cotizaciones/{quotation}/pdf', [QuotationController::class, 'download'])->name('quotations.download');
    Route::get('/solicitudes/{serviceRequest}', [App\Http\Controllers\Commercial\ServiceRequestController::class, 'show'])->name('requests.show');
    Route::patch('/solicitudes/{serviceRequest}/estado', [App\Http\Controllers\Commercial\ServiceRequestController::class, 'transition'])->name('requests.transition');
    Route::get('/solicitudes/{serviceRequest}/archivo', [App\Http\Controllers\Commercial\ServiceRequestController::class, 'attachment'])->name('requests.attachment');
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
