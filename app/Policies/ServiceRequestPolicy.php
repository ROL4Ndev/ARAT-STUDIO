<?php

namespace App\Policies;

use App\Models\ClientGrant;
use App\Models\ServiceRequest;
use App\Models\User;

class ServiceRequestPolicy
{
    public function viewAny(User $u): bool
    {
        return $u->is_active && in_array($u->role, ['admin', 'gestor'], true);
    }

    public function view(User $u, ServiceRequest $r): bool
    {
        return $this->viewAny($u) && ($u->role === 'admin' || $r->assigned_to === $u->id);
    }

    public function operate(User $u, ServiceRequest $r): bool
    {
        return $this->view($u, $r);
    }

    public function assign(User $u, ServiceRequest $r): bool
    {
        return $this->viewAny($u) && $u->role === 'admin';
    }

    public function claim(User $u, ServiceRequest $r): bool
    {
        return $this->viewAny($u) && $u->role === 'gestor' && $r->assigned_to === null && ! in_array($r->status, ['rechazado', 'cancelado', 'cerrada']);
    }

    public function clientView(ClientGrant $g, ServiceRequest $r): bool
    {
        return $g->service_request_id === $r->id && ClientGrant::whereKey($g->id)->where('service_request_id', $r->id)->where('email', $r->client_email)->whereNull('revoked_at')->where('expires_at', '>', now('UTC'))->exists();
    }
}
