<?php

namespace App\Services;

use App\Models\ServiceRequest;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ServiceRequestService
{
    public function __construct(private QuotationCalculatorService $calculator) {}

    public function register(array $data, ?UploadedFile $file): array
    {
        if ($existing = ServiceRequest::where('submission_token', $data['submission_token'])->first()) {
            return [$existing, false];
        }
        $path = null;
        try {
            $result = $this->calculator->calculate((string) $data['area_m2'], $data['lod_selected']);
            if ($file) {
                $path = 'attachments/'.Str::uuid().'.'.strtolower($file->getClientOriginalExtension());
                if (! Storage::disk('commercial')->putFileAs('attachments', $file, basename($path))) {
                    throw new \RuntimeException('No se pudo guardar el adjunto.');
                }
            }
            $record = DB::transaction(function () use ($data, $result, $path, $file) {
                $record = ServiceRequest::create([
                    ...Arr::only($data, ['submission_token', 'client_name', 'client_email', 'client_phone', 'city', 'service', 'project_type', 'description', 'area_m2', 'lod_selected', 'external_url']),
                    'code' => 'SOL-'.Str::upper((string) Str::ulid()),
                    'estimated_cost' => $result['estimated_cost_decimal'],
                    'currency' => 'PEN', 'custom_evaluation' => $result['out_of_scale'],
                    'base_price_m2' => $result['base_price_m2'], 'lod_factor' => $result['lod_factor'], 'max_area_m2' => $result['max_area_m2'],
                    'attachment_path' => $path,
                    'attachment_name' => $file ? mb_substr(basename($file->getClientOriginalName()), 0, 240) : null,
                    'attachment_mime' => $file?->getMimeType(),
                    'status' => 'pendiente', 'consented_at' => now(),
                ]);
                $record->histories()->create(['action' => 'creacion', 'new_status' => 'pendiente', 'observation' => 'Solicitud pública con consentimiento.']);

                return $record;
            });

            return [$record, true];
        } catch (\Throwable $e) {
            if ($path) {
                try {
                    Storage::disk('commercial')->delete($path);
                } catch (\Throwable $cleanup) {
                    report($cleanup);
                }
            }
            if ($e instanceof UniqueConstraintViolationException && ($existing = ServiceRequest::where('submission_token', $data['submission_token'])->first())) {
                return [$existing, false];
            }
            throw $e;
        }
    }
}
