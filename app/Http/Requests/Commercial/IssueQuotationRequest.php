<?php

namespace App\Http\Requests\Commercial;

class IssueQuotationRequest extends CommercialRequest
{
    public function rules(): array
    {
        return ['scope' => ['required', 'string', 'min:10', 'max:20000'], 'official_amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999999999.99', 'decimal:0,2', 'regex:/^\d{1,16}(\.\d{1,2})?$/']];
    }

    public function attributes(): array
    {
        return ['scope' => 'alcance', 'official_amount' => 'importe oficial'];
    }
}
