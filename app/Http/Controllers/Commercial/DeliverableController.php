<?php

namespace App\Http\Controllers\Commercial;

use App\Http\Controllers\Controller;
use App\Http\Requests\Commercial\DeliverableDraftRequest;
use App\Http\Requests\Commercial\SendDeliverableRequest;
use App\Models\Deliverable;
use App\Models\ServiceRequest;
use App\Services\DeliverableWorkflowService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class DeliverableController extends Controller
{
    public function create(ServiceRequest $serviceRequest)
    {
        Gate::authorize('create', [Deliverable::class, $serviceRequest]);
        $accepted = $serviceRequest->quotations()->where('status', 'aceptada')->get();
        abort_if($accepted->isEmpty(), 409, 'Primero se necesita una cotización aceptada de este expediente.');

        return view('deliverables.form', compact('serviceRequest', 'accepted'));
    }

    public function store(DeliverableDraftRequest $request, ServiceRequest $serviceRequest, DeliverableWorkflowService $workflow)
    {
        try {
            $deliverable = $workflow->create($serviceRequest, $request->user(), $request->validated(), $request->file('attachment'));
        } catch (ValidationException $error) { throw $error->redirectTo(route('commercial.deliverables.create', $serviceRequest));
        } catch (HttpExceptionInterface|\Illuminate\Auth\Access\AuthorizationException $error) { throw $error;
        } catch (\Throwable $error) {
            report($error);
            return redirect()->route('commercial.deliverables.create', $serviceRequest)->withInput($request->except('attachment'))->withErrors(['attachment' => 'No se confirmó el guardado. Los archivos enviados anteriormente se conservan. Vuelve a intentarlo.']);
        }

        return redirect()->route('commercial.deliverables.show', $deliverable)->with('status', 'Borrador guardado. Todavía no es visible para el cliente.');
    }

    public function show(Deliverable $deliverable)
    {
        Gate::authorize('view', $deliverable);
        $deliverable->load(['quotation.serviceRequest.assignee', 'latestVersion', 'versions.author', 'versions.sender']);
        $serviceRequest = $deliverable->quotation->serviceRequest;

        return view('deliverables.show', compact('deliverable', 'serviceRequest'));
    }

    public function save(DeliverableDraftRequest $request, Deliverable $deliverable, DeliverableWorkflowService $workflow)
    {
        try {
            $workflow->saveDraft($deliverable, $request->user(), $request->validated(), $request->file('attachment'));
        } catch (ValidationException $error) { throw $error->redirectTo(route('commercial.deliverables.show', $deliverable));
        } catch (HttpExceptionInterface|\Illuminate\Auth\Access\AuthorizationException $error) { throw $error;
        } catch (\Throwable $error) {
            report($error);
            return redirect()->route('commercial.deliverables.show', $deliverable)->withInput($request->except('attachment'))->withErrors(['attachment' => 'No se confirmó el guardado. Las versiones anteriores se conservan. Vuelve a adjuntar el archivo.']);
        }

        return redirect()->route('commercial.deliverables.show', $deliverable)->with('status', 'Borrador guardado; las versiones enviadas se conservan.');
    }

    public function send(SendDeliverableRequest $request, Deliverable $deliverable, int $number, DeliverableWorkflowService $workflow)
    {
        try {
            $workflow->send($deliverable, $request->user(), $number, (int) $request->validated('lock_version'));
        } catch (ValidationException $error) { throw $error->redirectTo(route('commercial.deliverables.show', $deliverable));
        } catch (HttpExceptionInterface|\Illuminate\Auth\Access\AuthorizationException $error) { throw $error;
        } catch (\Throwable $error) {
            report($error);
            return redirect()->route('commercial.deliverables.show', $deliverable)->withErrors(['attachment' => 'No se confirmó el envío. El borrador y sus archivos se conservan. Vuelve a intentarlo.']);
        }

        return redirect()->route('commercial.deliverables.show', $deliverable)->with('status', 'Versión disponible para revisión en el expediente del cliente.');
    }
}
