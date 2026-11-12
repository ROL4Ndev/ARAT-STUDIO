<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicSite\CalculateQuotationRequest;
use App\Models\OfferedService;
use App\Models\TariffParam;
use App\Services\QuotationCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class QuotationCalculatorController extends Controller
{
    public function show(): View
    {
        $tariffs = TariffParam::query()->where('status', 'activa')
            ->orderBy('lod_level')
            ->get();

        $services = OfferedService::where('is_active', true)->orderBy('display_order')->get();

        return view('quotations.calculator', compact('tariffs', 'services'));
    }

    public function calculate(
        CalculateQuotationRequest $request,
        QuotationCalculatorService $calculator
    ): JsonResponse {
        $data = $request->validated();

        return response()->json(
            $calculator->calculate(
                isset($data['area_m2']) ? (string) $data['area_m2'] : null,
                $data['lod_selected'] ?? null,
                isset($data['service_id']) ? OfferedService::where('is_active', true)->findOrFail($data['service_id']) : null
            )
        );
    }
}
