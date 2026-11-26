<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\Client\DeliverableResponseRequest;
use App\Models\Deliverable;
use App\Services\DeliverableWorkflowService;

class DeliverableController extends Controller
{
    public function respond(DeliverableResponseRequest $request, Deliverable $deliverable, int $number, DeliverableWorkflowService $workflow)
    {
        try {
            $workflow->respond($deliverable, $request->attributes->get('clientGrant'), $number, $request->validated());
        } catch (\Illuminate\Validation\ValidationException $error) { throw $error->redirectTo(route('client.dossier').'#entregables');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $error) { throw $error;
        } catch (\Throwable $error) {
            report($error);
            return redirect()->to(route('client.dossier').'#entregables')->withInput()->withErrors(['comment' => 'No se confirmó la respuesta. El documento se conserva; revisa el estado antes de reintentarlo.']);
        }

        return redirect()->to(route('client.dossier').'#entregables')->with('status', 'Respuesta del entregable registrada. El cierre comercial y sus cotizaciones se conservan.');
    }
}
