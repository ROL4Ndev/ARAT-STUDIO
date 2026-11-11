<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

class ProjectAttachment implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            $fail('No se pudo recibir el archivo. Revisa el límite de carga o utiliza un enlace externo.');

            return;
        }
        $extension = strtolower($value->getClientOriginalExtension());
        $head = file_get_contents($value->getRealPath(), false, null, 0, 1024);
        $mime = $value->getMimeType();
        $valid = match ($extension) {
            'pdf' => str_starts_with($head, '%PDF-') && $mime === 'application/pdf',
            'jpg', 'jpeg' => @getimagesize($value->getRealPath())[2] === IMAGETYPE_JPEG && $mime === 'image/jpeg',
            'png' => @getimagesize($value->getRealPath())[2] === IMAGETYPE_PNG && $mime === 'image/png',
            'dwg' => preg_match('/^AC10[0-9]{2}/', $head) === 1 && in_array($mime, ['image/vnd.dwg', 'image/x-dwg', 'application/acad', 'application/x-acad', 'application/octet-stream', 'text/plain']),
            'zip' => in_array(substr($head, 0, 4), ["PK\x03\x04", "PK\x05\x06", "PK\x07\x08"]) && in_array($mime, ['application/zip', 'application/x-zip', 'application/x-zip-compressed', 'application/octet-stream']),
            default => false,
        };
        if (! $valid) {
            $fail('El contenido y la extensión deben corresponder a PDF, JPG, JPEG, PNG, DWG o ZIP.');
        }
    }
}
