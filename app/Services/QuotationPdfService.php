<?php

namespace App\Services;

use App\Models\Quotation;
use Barryvdh\DomPDF\Facade\Pdf;

class QuotationPdfService
{
    public function render(Quotation $quotation): string
    {
        return Pdf::loadView('commercial.quotation-pdf', compact('quotation'))
            ->setOptions(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false, 'chroot' => resource_path(), 'defaultFont' => 'DejaVu Sans'])
            ->setPaper('a4')->output();
    }
}
