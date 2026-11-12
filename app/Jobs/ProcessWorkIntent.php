<?php

namespace App\Jobs;

use App\Services\WorkProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessWorkIntent implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public int $intentId) {}

    public function handle(WorkProcessor $processor): void
    {
        $processor->run($this->intentId);
    }
}
