<?php

namespace App\Policies;

use App\Models\ClientGrant;
use App\Models\Quotation;
use App\Models\User;

class QuotationPolicy
{
    public function view(User $u, Quotation $q): bool
    {
        return app(ServiceRequestPolicy::class)->view($u, $q->serviceRequest);
    }

    public function update(User $u, Quotation $q): bool
    {
        return $this->view($u, $q) && $q->status === 'borrador';
    }

    public function clientView(ClientGrant $g, Quotation $q): bool
    {
        return app(ServiceRequestPolicy::class)->clientView($g, $q->serviceRequest) && $q->pdf_status === 'ready' && $q->issued_at !== null;
    }
}
