<?php

namespace App\Jobs;

use App\Models\Disease;
use App\Services\OutbreakDetectionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Queued after a disease case is saved, so the person recording the case
 * does not wait for the threshold check. The daily sweep repeats the check
 * in case the queue worker was down.
 */
class DetectOutbreaks implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $diseaseId,
        public readonly ?int $purokId = null,
    ) {}

    public function handle(OutbreakDetectionService $detector): void
    {
        $disease = Disease::query()->find($this->diseaseId);

        if ($disease !== null) {
            $detector->checkDisease($disease, $this->purokId);
        }
    }
}
