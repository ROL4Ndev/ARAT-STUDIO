<?php

namespace App\Http\Controllers\PublicSite;

use App\Exceptions\EstimateChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\PublicSite\StoreServiceRequest;
use App\Models\OfferedService;
use App\Services\ClientAccessService;
use App\Services\ServiceRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ServiceRequestController extends Controller
{
    private function data(Request $request, bool $new = true): array
    {
        $token = $new ? (string) Str::uuid() : $request->input('submission_token');
        if ($new) {
            $request->session()->put('project_submission_tokens', array_slice([...$request->session()->get('project_submission_tokens', []), $token], -20));
        }

        return ['token' => $token, 'services' => OfferedService::where('is_active', true)->orderBy('display_order')->get(), 'types' => StoreServiceRequest::PROJECT_TYPES];
    }

    public function create(Request $request)
    {
        return view('service-requests.create', $this->data($request));
    }

    public function store(StoreServiceRequest $request, ServiceRequestService $service)
    {
        try {
            $related = null;
            if ($request->boolean('related') && $request->session()->has('client_grant')) {
                $related = app(ClientAccessService::class)->grant($request)->service_request_id;
            }
            [$record] = $service->register($request->validated(), $request->file('attachment'), $related);
        } catch (EstimateChanged $e) {
            $request->session()->flashInput($request->except(['attachment', 'estimate_confirmed']));

            return response()->view('service-requests.create', $this->data($request, false) + ['estimate' => $e->estimate, 'estimateConflict' => $e->getMessage()], 409);
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput($request->except('attachment'))->withErrors(['attachment' => 'No se confirmó el registro. Revisa los datos o el archivo y vuelve a intentar.']);
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
