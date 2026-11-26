<?php

namespace App\Http\Controllers;

use App\Models\Deliverable;
use App\Policies\DeliverablePolicy;
use App\Services\DeliverableWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DeliverableFileController extends Controller
{
    public function __invoke(Request $request, Deliverable $deliverable, int $number, DeliverableWorkflowService $workflow)
    {
        $version = $deliverable->versions()->where('number', $number)->firstOrFail();
        if ($grant = $request->attributes->get('clientGrant')) {
            abort_unless(app(DeliverablePolicy::class)->clientView($grant, $version), 403);
        } else {
            Gate::authorize('view', $deliverable);
        }
        try { $workflow->verifyFile($version); }
        catch (ValidationException $error) { abort(409, 'El documento no está disponible. Contacta al estudio para comprobar el archivo.'); }
        catch (\Throwable $error) { report($error); abort(409, 'No se pudo leer el documento. Contacta al estudio para reintentar la descarga.'); }

        return Storage::disk('commercial')->download($version->path, $version->original_name,
            ['Content-Type' => $version->mime, 'X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, no-store']);
    }
}
