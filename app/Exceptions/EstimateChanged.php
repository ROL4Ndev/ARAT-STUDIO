<?php

namespace App\Exceptions;

class EstimateChanged extends \RuntimeException
{
    public function __construct(public array $estimate)
    {
        parent::__construct('La estimación cambió. Revisa los nuevos valores y confirma antes de registrar.');
    }
}
