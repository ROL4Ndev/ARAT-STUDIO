<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Http\Requests\PublicSite\StoreServiceRequest;
use App\Mail\ServiceRequestReceived;
use App\Services\ServiceRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ServiceRequestController extends Controller
{
    public function create(Request $request)
    {
        $token = (string) Str::uuid();
        $tokens = $request->session()->get('project_submission_tokens', []);
        $request->session()->put('project_submission_tokens', array_slice([...$tokens, $token], -20));

        return view('service-requests.create', ['token' => $token, 'services' => StoreServiceRequest::SERVICES, 'types' => StoreServiceRequest::PROJECT_TYPES]);
    }

    public function store(StoreServiceRequest $request, ServiceRequestService $service)
    {
        try {
            [$record, $created] = $service->register($request->validated(), $request->file('attachment'));
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput($request->except('attachment'))->withErrors(['attachment' => 'No pudimos guardar la solicitud o su archivo. Inténtalo nuevamente o utiliza un enlace externo.']);
        }
        if ($created) {
            try {
                Mail::to($record->client_email)->send(new ServiceRequestReceived($record));
            } catch (\Throwable $e) {
                report($e); // La solicitud ya está confirmada; no pedir que se vuelva a registrar.
            }
        }
        $request->session()->put('project_confirmation', $record->code);

        return redirect()->route('service-requests.confirmation');
    }

    public function confirmation(Request $request)
    {
        abort_unless($request->session()->has('project_confirmation'), 404);

        return response()->view('service-requests.confirmation', ['code' => $request->session()->get('project_confirmation')])->header('Cache-Control', 'private, no-store');
    }
}
