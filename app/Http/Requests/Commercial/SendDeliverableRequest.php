<?php

namespace App\Http\Requests\Commercial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SendDeliverableRequest extends FormRequest
{
    public function authorize(): bool { return Gate::allows('view', $this->route('deliverable')); }
    public function rules(): array { return ['lock_version' => 'required|integer|min:0']; }
    protected function getRedirectUrl(): string { return route('commercial.deliverables.show', $this->route('deliverable')); }
}
