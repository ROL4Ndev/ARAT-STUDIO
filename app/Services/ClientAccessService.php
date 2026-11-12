<?php

namespace App\Services;

use App\Models\ClientAccessToken;
use App\Models\ClientGrant;
use App\Models\ServiceRequest;
use App\Policies\ServiceRequestPolicy;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class ClientAccessService
{
    public function __construct(private NotificationService $notifications, private AuditService $audit) {}

    public function request(string $code, string $email): void
    {
        $r = ServiceRequest::where('code', $code)->where('client_email', $email)->first();
        if (! $r) {
            return;
        }
        DB::transaction(function () use ($r) {
            $token = ClientAccessToken::create(['service_request_id' => $r->id, 'email' => $r->client_email, 'purpose' => 'access', 'token_hash' => hash('sha256', random_bytes(32)), 'expires_at' => now('UTC')->addMinutes(15)]);
            $this->notifications->queue('access', $token->id, $r->client_email);
        });
    }

    public function grant(Request $request): ClientGrant
    {
        abort_if($request->user() !== null, 403, 'Cierra la sesión interna antes de utilizar un acceso de cliente.');
        $raw = $request->session()->get('client_grant');
        abort_unless(is_string($raw), 403, 'Necesitas un acceso verificado.');
        $grant = ClientGrant::where('session_hash', hash('sha256', $raw))->first();
        abort_unless($grant && ! $grant->revoked_at && $grant->expires_at->isFuture(), 403, 'El acceso temporal venció o fue revocado.');
        abort_unless(app(ServiceRequestPolicy::class)->clientView($grant, $grant->serviceRequest), 403, 'El acceso ya no corresponde a este expediente.');

        return $grant;
    }

    public function consume(Request $request, string $raw): void
    {
        abort_if($request->user() !== null, 403, 'Cierra la sesión interna para acceder como cliente.');
        $candidate = ClientAccessToken::where('token_hash', hash('sha256', $raw))->first();
        abort_unless($candidate, 403, 'El enlace venció, ya se utilizó o no es válido.');
        $existing = $candidate->purpose === 'email_change' ? $this->grant($request) : null;
        $secret = bin2hex(random_bytes(32));
        DB::transaction(function () use ($candidate, $existing, $secret) {
            $r = ServiceRequest::whereKey($candidate->service_request_id)->lockForUpdate()->firstOrFail();
            $token = ClientAccessToken::whereKey($candidate->id)->lockForUpdate()->firstOrFail();
            abort_unless(! $token->consumed_at && $token->expires_at->isFuture(), 403, 'El enlace venció o ya se utilizó.');
            if ($token->purpose === 'email_change') {
                abort_unless($existing && app(ServiceRequestPolicy::class)->clientView($existing, $r), 403);
                abort_if(in_array($r->status, ['rechazado', 'cancelado', 'cerrada']), 409, 'El expediente finalizado es de solo consulta.');
                $before = Arr::except($r->toArray(), ['submission_token', 'request_hash']);
                $r->update(['client_email' => $token->email, 'lock_version' => $r->lock_version + 1]);
                ClientGrant::where('service_request_id', $r->id)->whereNull('revoked_at')->update(['revoked_at' => now('UTC')]);
                $revision = $r->revisions()->create(['version' => (int) $r->revisions()->max('version') + 1, 'author_type' => 'client', 'client_grant_id' => $existing->id, 'kind' => 'verified_email', 'before' => $before, 'after' => Arr::except($r->toArray(), ['submission_token', 'request_hash']), 'created_at' => now('UTC')]);
                $r->update(['current_revision_id' => $revision->id]);
                $this->audit->record($r, 'verified_email_change', 'Nuevo correo verificado', $before, ['client_email' => $r->client_email], $existing);
            } else {
                abort_unless(hash_equals($r->client_email, $token->email), 403, 'El correo del expediente cambió.');
            }
            $token->update(['consumed_at' => now('UTC')]);
            ClientGrant::create(['service_request_id' => $r->id, 'email' => $r->client_email, 'session_hash' => hash('sha256', $secret), 'expires_at' => now('UTC')->addMinutes(30)]);
        }, 3);
        $request->session()->regenerate();
        $request->session()->put('client_grant', $secret);
    }

    public function emailChange(ClientGrant $grant, string $email): void
    {
        $r = $grant->serviceRequest;
        abort_unless(app(ServiceRequestPolicy::class)->clientView($grant, $r), 403);
        abort_if(strcasecmp($email, $r->client_email) === 0, 422, 'Indica un correo distinto.');
        DB::transaction(function () use ($r, $email) {
            $r = ServiceRequest::whereKey($r->id)->lockForUpdate()->firstOrFail();
            abort_if(in_array($r->status, ['rechazado', 'cancelado', 'cerrada']), 409, 'El expediente finalizado es de solo consulta.');
            $token = ClientAccessToken::create(['service_request_id' => $r->id, 'email' => $email, 'purpose' => 'email_change', 'token_hash' => hash('sha256', random_bytes(32)), 'expires_at' => now('UTC')->addMinutes(15)]);
            $this->notifications->queue('email_change', $token->id, $email);
        });
    }

    public function revoke(Request $request): void
    {
        $grant = $this->grant($request);
        $grant->update(['revoked_at' => now('UTC')]);
        $request->session()->forget('client_grant');
        $request->session()->regenerate();
    }
}
