<?php

namespace App\Http\Requests\Commercial;

class QuotationActionRequest extends CommercialRequest
{
    public function rules(): array
    {
        return ['lock_version' => ['required', 'integer', 'min:0']];
    }
}
