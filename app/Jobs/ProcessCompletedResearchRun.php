<?php

namespace App\Jobs;

use App\Models\OfficeResearchRun;
use App\Services\OfficeAnalystReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessCompletedResearchRun implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public OfficeResearchRun $run)
    {
    }

    public function handle(OfficeAnalystReportService $analyst): void
    {
        $analyst->generatePlanFromResearch($this->run);
    }
}
