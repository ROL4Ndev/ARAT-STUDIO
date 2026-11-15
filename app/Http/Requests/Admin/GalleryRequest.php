<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\Rule;

class GalleryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('project')) === true;
    }

    public function rules(): array
    {
        $rules = ['lock_version' => ['required', 'integer', 'min:0'], 'reason' => ['required', 'string', 'min:5', 'max:2000']];
        if ($this->routeIs('admin.gallery.add')) {
            $folders = array_map('basename', File::directories(public_path('img')));

            return $rules + [
                'source_folder' => ['required_without:images', 'nullable', Rule::in($folders)],
                'images' => ['required_without:source_folder', 'array', 'max:20'],
                'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
            ];
        }
        if ($this->routeIs('admin.gallery.order')) {
            return $rules + ['positions' => ['required', 'array', 'max:200'], 'positions.*' => ['required', 'integer', 'min:0', 'max:100000', 'distinct']];
        }

        return $rules + ($this->routeIs('admin.gallery.status') ? ['is_active' => ['required', 'boolean']] : []);
    }

    public function attributes(): array
    {
        return ['source_folder' => 'carpeta de imágenes', 'images' => 'imágenes', 'reason' => 'motivo', 'positions' => 'orden de imágenes'];
    }
}
