<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class CommercialNotice extends Mailable
{
    public function __construct(public string $heading, public string $bodyText, public ?string $link = null) {}

    public function build()
    {
        return $this->subject($this->heading)->view('mail.commercial-notice');
    }
}
