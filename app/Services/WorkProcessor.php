<?php

namespace App\Services;

use App\Mail\CommercialNotice;
use App\Models\ClientAccessToken;
use App\Models\NotificationAttempt;
use App\Models\NotificationRecord;
use App\Models\Quotation;
use App\Models\RequestRevision;
use App\Models\ServiceRequest;
use App\Models\WorkIntent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class WorkProcessor
{
    public function __construct(private QuotationPdfService $pdf, private AuditService $audit, private NotificationService $notifications) {}

    public function run(int $id): void
    {
        $work = DB::transaction(function () use ($id) {
            $w = WorkIntent::whereKey($id)->lockForUpdate()->firstOrFail();
            if ($w->status !== 'pending') {
                return null;
            }$w->update(['status' => 'processing', 'attempts' => $w->attempts + 1, 'lease' => (string) Str::uuid(), 'leased_at' => now('UTC'), 'error' => null]);

            return $w;
        });
        if (! $work) {
            return;
        }
        try {
            if ($work->kind === 'pdf') {
                $this->generate($work);
            } elseif ($work->kind === 'notification') {
                $this->notify($work);
            } else {
                throw new \LogicException('Tipo de trabajo inválido.');
            }
        } catch (\Throwable $e) {
            DB::transaction(function () use ($work, $e) {
                if ($work->kind === 'pdf') {
                    $candidate = Quotation::find($work->resource_id);
                    if ($candidate) {
                        ServiceRequest::whereKey($candidate->service_request_id)->lockForUpdate()->first();
                        Quotation::whereKey($candidate->id)->lockForUpdate()->first();
                    }
                }
                $w = WorkIntent::whereKey($work->id)->lockForUpdate()->first();
                if (! $w || $w->lease !== $work->lease || $w->status !== 'processing') {
                    return;
                }
                $safe = 'Fallo técnico: '.class_basename($e).'. Puede reintentarse.';
                $w->update(['status' => 'failed', 'error' => $safe]);
                if ($w->kind === 'pdf') {
                    Quotation::whereKey($w->resource_id)->where('status', 'preparando')->update(['pdf_status' => 'failed', 'technical_error' => $safe]);
                } else {
                    $n = NotificationRecord::find($w->resource_id);
                    if ($n) {
                        $attempt = NotificationAttempt::firstOrCreate(['notification_record_id' => $n->id, 'attempt_no' => $w->attempts], ['destination' => $n->recipient, 'status' => 'processing', 'attempted_at' => now('UTC')]);
                        if ($attempt->status === 'processing') {
                            $attempt->update(['status' => 'failed', 'error' => $safe]);
                        }
                        $n->update(['status' => 'failed', 'attempts' => max($n->attempts, $w->attempts), 'error' => $safe]);
                        if ($n->kind === 'quotation') {
                            Quotation::whereKey($n->resource_id)->update(['notification_status' => 'failed']);
                        }
                    }
                }
            });
        }
    }

    private function generate(WorkIntent $work): void
    {
        // Render y escritura fuera de cualquier transacción o bloqueo.
        $q = Quotation::findOrFail($work->resource_id);
        if ($q->status !== 'preparando') {
            $this->finish($work, 'cancelled');

            return;
        }
        $issued = now('UTC')->toImmutable();
        $q->issued_at = $issued;
        $q->expires_at = $issued->addDays(15);
        $content = $this->pdf->render($q);
        $path = 'quotations/'.$q->code.'/'.Str::uuid().'.pdf';
        if (! str_starts_with($content, '%PDF-') || ! Storage::disk('commercial')->put($path, $content)) {
            throw new \RuntimeException('PDF no disponible.');
        }
        DB::transaction(function () use ($work, $q, $path, $issued) {
            $r = ServiceRequest::whereKey($q->service_request_id)->lockForUpdate()->firstOrFail();
            $locked = Quotation::whereKey($q->id)->lockForUpdate()->firstOrFail();
            $w = WorkIntent::whereKey($work->id)->lockForUpdate()->firstOrFail();
            if ($w->lease !== $work->lease) {
                return;
            }
            $w->result = ['generated_path' => $path, 'sha256' => hash_file('sha256', Storage::disk('commercial')->path($path))];
            if ($w->status !== 'processing' || $r->status !== 'aprobado' || $locked->status !== 'preparando' || $r->quotations()->where('status', 'aceptada')->exists()) {
                if ($locked->status === 'preparando') {
                    $locked->update(['status' => 'anulada', 'pdf_status' => 'discarded', 'lock_version' => $locked->lock_version + 1]);
                }
                $w->status = 'cancelled';
                $w->save();
                $this->audit->record($locked, 'emission_cancelled', 'El estado cambió durante la generación. El archivo técnico privado se conserva.');

                return;
            }
            if ($r->current_quotation_id && $r->current_quotation_id !== $locked->id) {
                $previous = Quotation::whereKey($r->current_quotation_id)->lockForUpdate()->firstOrFail();
                if ($previous->status === 'emitida') {
                    $old = $previous->toArray();
                    $previous->update(['status' => 'sustituida', 'lock_version' => $previous->lock_version + 1]);
                    $this->audit->record($previous, 'superseded', 'Nueva versión emitida correctamente', $old, $previous->toArray());
                }
            }
            $before = $locked->toArray();
            $locked->update(['status' => 'emitida', 'pdf_status' => 'ready', 'pdf_path' => $path, 'pdf_hash' => $w->result['sha256'], 'issued_at' => $issued, 'expires_at' => $issued->addDays(15), 'lock_version' => $locked->lock_version + 1, 'technical_error' => null]);
            $r->update(['current_quotation_id' => $locked->id, 'lock_version' => $r->lock_version + 1]);
            $r->histories()->create(['action' => 'emision_cotizacion', 'previous_status' => 'aprobado', 'new_status' => 'aprobado', 'is_public' => true, 'observation' => 'Cotización '.$locked->code.' versión '.$locked->version.' emitida.']);
            $this->audit->record($locked, 'quotation_issued', 'PDF privado listo', $before, $locked->toArray());
            $this->notifications->queue('quotation', $locked->id, $locked->client_snapshot['email']);
            $w->status = 'done';
            $w->save();
        }, 3);
    }

    private function notify(WorkIntent $work): void
    {
        $n = NotificationRecord::findOrFail($work->resource_id);
        $link = null;
        if (in_array($n->kind, ['access', 'email_change'])) {
            $token = ClientAccessToken::findOrFail($n->resource_id);
            if ($token->consumed_at) {
                $n->update(['status' => 'cancelled', 'error' => 'El enlace ya se utilizó; solicita un nuevo acceso.']);
                $this->finish($work, 'cancelled');

                return;
            }
            $raw = bin2hex(random_bytes(32));
            $token->update(['token_hash' => hash('sha256', $raw), 'expires_at' => now('UTC')->addMinutes(15)]);
            $link = route('client.access.form', ['token' => $raw]);
            $heading = 'Acceso temporal a tu expediente';
            $body = 'Verifica tu correo para acceder únicamente a tu solicitud.';
        } elseif ($n->kind === 'received') {
            $r = ServiceRequest::findOrFail($n->resource_id);
            $heading = 'Solicitud recibida '.$r->code;
            $body = 'Registramos tu solicitud. El equipo evaluará el proyecto. Puedes solicitar acceso desde la página Acceder a mi expediente.';
        } elseif ($n->kind === 'observed') {
            $revision = RequestRevision::findOrFail($n->resource_id);
            $r = ServiceRequest::findOrFail($revision->service_request_id);
            $heading = 'Información adicional requerida '.$r->code;
            $body = 'Hay una observación pública en tu expediente. Solicita acceso verificado para consultar y corregir.';
        } elseif ($n->kind === 'corrected') {
            $revision = RequestRevision::findOrFail($n->resource_id);
            $heading = 'Corrección recibida del expediente '.$revision->service_request_id;
            $body = 'El cliente presentó una revisión validada. La solicitud volvió a evaluación.';
        } else {
            $q = Quotation::findOrFail($n->resource_id);
            $heading = 'Cotización disponible '.$q->code;
            $body = 'La propuesta está disponible en tu expediente verificado. No es una firma digital certificada.';
        }
        $attempt = DB::transaction(function () use ($work, $n) {
            $w = WorkIntent::whereKey($work->id)->lockForUpdate()->firstOrFail();
            abort_unless($w->lease === $work->lease && $w->status === 'processing', 409);
            $attempt = $n->deliveries()->create(['attempt_no' => $w->attempts, 'destination' => $n->recipient, 'status' => 'processing', 'attempted_at' => now('UTC')]);
            $n->update(['attempts' => max($n->attempts, $w->attempts)]);

            return $attempt;
        });
        // SMTP/log fuera de transacción; un fallo no altera el expediente ni la emisión.
        $sent = Mail::to($n->recipient)->send(new CommercialNotice($heading, $body, $link));
        $simulated = in_array(config('mail.default'), ['log', 'array'], true);
        DB::transaction(function () use ($work, $n, $sent, $simulated, $attempt) {
            $w = WorkIntent::whereKey($work->id)->lockForUpdate()->firstOrFail();
            if ($w->lease !== $work->lease || $w->status !== 'processing') {
                $attempt->update(['status' => 'unknown', 'error' => 'El lease cambió durante el envío; el transporte pudo recibirlo.']);

                return;
            }
            $reference = $simulated ? null : $sent?->getMessageId();
            $attempt->update(['status' => $simulated ? 'simulated' : 'sent', 'provider_reference' => $reference]);
            $n->update(['status' => $simulated ? 'simulated' : 'sent', 'error' => null, 'provider_reference' => $reference]);
            if ($n->kind === 'quotation') {
                Quotation::whereKey($n->resource_id)->update(['notification_status' => $simulated ? 'simulated' : 'sent']);
            }
            $w->update(['status' => 'done']);
        });
    }

    private function finish(WorkIntent $work, string $status): void
    {
        WorkIntent::whereKey($work->id)->where('lease', $work->lease)->update(['status' => $status]);
    }
}
