<?php

namespace App\Policies;

use App\Models\ClientGrant;
use App\Models\Deliverable;
use App\Models\DeliverableVersion;
use App\Models\ServiceRequest;
use App\Models\User;

class DeliverablePolicy
{
    public function view(User $user, Deliverable $deliverable): bool
    {
        // Consultar la asignación actual, también cuando el modelo recibido sea antiguo.
        $request = $deliverable->quotation()->firstOrFail()->serviceRequest()->firstOrFail();

        return app(ServiceRequestPolicy::class)->view($user->fresh(), $request);
    }

    public function create(User $user, ServiceRequest $request): bool
    {
        return app(ServiceRequestPolicy::class)->operate($user->fresh(), $request->fresh());
    }

    public function clientView(ClientGrant $grant, DeliverableVersion $version): bool
    {
        return $version->sent_at !== null && app(ServiceRequestPolicy::class)->clientView($grant,
            $version->deliverable->quotation->serviceRequest);
    }
}
