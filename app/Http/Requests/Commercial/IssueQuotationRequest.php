<?php

namespace App\Http\Requests\Commercial;

class IssueQuotationRequest extends CommercialRequest
{
    public function rules(): array
    {
        return ['request_lock_version' => ['required', 'integer', 'min:0'], 'lock_version' => ['required', 'integer', 'min:0'], 'source_code' => ['nullable', 'string', 'max:40'], 'scope' => ['required', 'string', 'min:10', 'max:20000'], 'deliverables' => ['required', 'string', 'min:10', 'max:10000'], 'exclusions' => ['required', 'string', 'min:5', 'max:10000'], 'execution_term' => ['required', 'string', 'min:3', 'max:255'], 'official_amount' => ['required', 'numeric', 'min:0.01', 'decimal:0,2', 'regex:/^\d{1,16}(\.\d{1,2})?$/'], 'adjustment_reason' => ['nullable', 'string', 'max:5000'], 'reason' => ['required', 'string', 'min:5', 'max:5000']];
    }

    public function attributes(): array
    {
        return ['scope' => 'alcance', 'deliverables' => 'entregables', 'exclusions' => 'exclusiones', 'execution_term' => 'plazo de ejecución', 'official_amount' => 'importe oficial', 'reason' => 'motivo de versión', 'adjustment_reason' => 'motivo del ajuste'];
    }
}
