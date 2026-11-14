<?php

namespace App\Services;

use App\Models\Quotation;
use Barryvdh\DomPDF\Facade\Pdf;

class QuotationPdfService
{
    public function render(Quotation $quotation): string
    {
        $logo = 'data:image/png;base64,'.base64_encode(file_get_contents(public_path('img/LOGO1.png')));

        return Pdf::loadView('commercial.quotation-pdf', compact('quotation', 'logo'))
            ->setOptions(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false, 'chroot' => resource_path(), 'defaultFont' => 'DejaVu Sans'])
            ->setPaper('a4')->output();
    }
}
