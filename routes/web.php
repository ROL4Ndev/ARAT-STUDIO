<?php

use App\Http\Controllers\Admin\CatalogController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Client\AccessController;
use App\Http\Controllers\Client\DossierController;
use App\Http\Controllers\Commercial\QuotationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PublicSite\HomeController;
use App\Http\Controllers\PublicSite\ProjectController;
use App\Http\Controllers\PublicSite\QuotationCalculatorController;
use App\Http\Controllers\PublicSite\ServiceRequestController;
use App\Http\Middleware\EnsureClientGrant;
use App\Services\CatalogRegistry;
use Illuminate\Support\Facades\Route;

Route::get('/acceder-expediente', [AccessController::class, 'create'])->name('client.access');
Route::post('/acceder-expediente', [AccessController::class, 'request'])->middleware('throttle:5,1,client-access:')->name('client.access.request');
Route::get('/acceso-expediente/{token}', [AccessController::class, 'form'])->where('token', '[a-f0-9]{64}')->name('client.access.form');
Route::post('/acceso-expediente/{token}', [AccessController::class, 'consume'])->where('token', '[a-f0-9]{64}')->middleware('throttle:6,1,client-consume:')->name('client.access.consume');
Route::prefix('mi-solicitud')->name('client.')->middleware(EnsureClientGrant::class)->group(function () {
    Route::get('/', [DossierController::class, 'show'])->name('dossier');
    Route::post('/corregir', [DossierController::class, 'correct'])->middleware('throttle:6,1,client-correction:')->name('correct');
    Route::post('/cancelar', [DossierController::class, 'cancel'])->name('cancel');
    Route::post('/correo', [DossierController::class, 'email'])->middleware('throttle:3,1,client-email:')->name('email');
    Route::post('/salir', [AccessController::class, 'revoke'])->name('logout');
    Route::post('/cotizaciones/{quotation}/responder', [DossierController::class, 'respond'])->name('respond');
    Route::get('/cotizaciones/{quotation}/pdf', [DossierController::class, 'quotation'])->name('quotations.download');
    Route::get('/archivos/{file}', [DossierController::class, 'file'])->name('files.download');
});

Route::get('/solicitar-proyecto', [ServiceRequestController::class, 'create'])->name('service-requests.create');
Route::post('/solicitar-proyecto', [ServiceRequestController::class, 'store'])->middleware('throttle:5,1,project-submit:')->name('service-requests.store');
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
    Route::post('/solicitudes/{serviceRequest}/corregir', [App\Http\Controllers\Commercial\ServiceRequestController::class, 'correct'])->name('requests.correct');
    Route::put('/cotizaciones/{quotation}', [QuotationController::class, 'update'])->name('quotations.update');
    Route::post('/cotizaciones/{quotation}/emitir', [QuotationController::class, 'prepare'])->name('quotations.prepare');
    Route::post('/notificaciones/{notice}/reintentar', [QuotationController::class, 'retryNotice'])->name('notices.retry');
    Route::get('/archivos/{file}', [App\Http\Controllers\Commercial\ServiceRequestController::class, 'file'])->name('files.download');
    Route::post('/solicitudes/{serviceRequest}/cotizacion', [QuotationController::class, 'store'])->name('quotations.store');
    Route::get('/cotizaciones/{quotation}', [QuotationController::class, 'show'])->name('quotations.show');
    Route::get('/cotizaciones/{quotation}/pdf', [QuotationController::class, 'download'])->name('quotations.download');
    Route::get('/solicitudes/{serviceRequest}', [App\Http\Controllers\Commercial\ServiceRequestController::class, 'show'])->name('requests.show');
    Route::patch('/solicitudes/{serviceRequest}/estado', [App\Http\Controllers\Commercial\ServiceRequestController::class, 'transition'])->name('requests.transition');
    Route::get('/solicitudes/{serviceRequest}/archivo', [App\Http\Controllers\Commercial\ServiceRequestController::class, 'attachment'])->name('requests.attachment');
});

Route::prefix('gestion')->name('commercial.')->middleware(['auth', 'role:admin,gestor'])->group(function () {
    Route::post('/solicitudes/{serviceRequest}/acceso-demo', [App\Http\Controllers\Commercial\ServiceRequestController::class, 'prepareDemoAccess'])->name('requests.demo-access.prepare');
    Route::get('/solicitudes/{serviceRequest}/acceso-demo', [App\Http\Controllers\Commercial\ServiceRequestController::class, 'showDemoAccess'])->name('requests.demo-access.show');
});

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'role:admin'])
    ->group(function () {
        Route::get('portadas/{history}', [CatalogController::class, 'cover'])->name('covers.download');
        foreach (CatalogRegistry::all() as $kind => [$label,$model,$path]) {
            foreach ([['GET', $path, 'index'], ['GET', $path.'/create', 'create'], ['POST', $path, 'store'], ['GET', $path.'/{record}/edit', 'edit'], ['GET', $path.'/{record}', 'show'], ['PUT', $path.'/{record}', 'update'], ['POST', $path.'/{record}/estado', 'status']] as [$method,$uri,$action]) {
                Route::match([$method], $uri, [CatalogController::class, $action])->defaults('catalog_kind', $kind)->name($kind.'.'.$action);
            }
        }
    });

Route::get('/', [HomeController::class, 'index'])
    ->name('home');

Route::get('/nosotros', [HomeController::class, 'about'])
    ->name('about');

Route::get('/servicios', [HomeController::class, 'services'])
    ->name('services');

Route::get('/contacto', [HomeController::class, 'contact'])
    ->name('contact');

Route::get('/proyectos', [ProjectController::class, 'index'])
    ->name('projects.index');

Route::get('/proyectos/{project:slug}', [ProjectController::class, 'show'])
    ->name('projects.show');

Route::get('/cotizador', [QuotationCalculatorController::class, 'show'])
    ->name('quotation.show');
