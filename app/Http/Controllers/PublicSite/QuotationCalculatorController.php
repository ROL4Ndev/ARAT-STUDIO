<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicSite\CalculateQuotationRequest;
use App\Models\TariffParam;
use App\Services\QuotationCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class QuotationCalculatorController extends Controller
{
    public function show(): View
    {
        $tariffs = TariffParam::query()
            ->orderBy('lod_level')
            ->get();

        return view('quotations.calculator', compact('tariffs'));
    }

    public function calculate(
        CalculateQuotationRequest $request,
        QuotationCalculatorService $calculator
    ): JsonResponse {
        $data = $request->validated();

        return response()->json(
            $calculator->calculate(
                (string) $data['area_m2'],
                $data['lod_selected']
            )
        );
    }
}
