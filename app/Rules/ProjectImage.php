<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class ProjectImage implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('No se pudo recibir la portada.');

            return;
        }
        $info = @getimagesize($value->getRealPath());
        $extension = strtolower($value->getClientOriginalExtension());
        $type = match ($extension) {
            'jpg','jpeg' => IMAGETYPE_JPEG,'png' => IMAGETYPE_PNG,'webp' => IMAGETYPE_WEBP,default => null
        };
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($value->getRealPath());
        if (! $info || $type === null || $info[2] !== $type || ! in_array($mime, ['image/jpeg', 'image/png', 'image/webp'])) {
            $fail('La portada debe contener una imagen JPG, PNG o WebP válida.');
        }
    }
}
