<?php

namespace App\Mail;

use App\Models\ServiceRequest;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class ServiceRequestReceived extends Mailable
{
    public function __construct(public ServiceRequest $serviceRequest) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Recibimos tu solicitud '.$this->serviceRequest->code);
    }

    public function content(): Content
    {
        return new Content(view: 'mail.service-request-received');
    }
}
