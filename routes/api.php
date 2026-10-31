<?php

use App\Http\Controllers\PublicSite\QuotationCalculatorController;
use Illuminate\Support\Facades\Route;

Route::post(
    '/calcular-presupuesto',
    [QuotationCalculatorController::class, 'calculate']
)
    ->middleware('throttle:60,1')
    ->name('quotation.calculate');
