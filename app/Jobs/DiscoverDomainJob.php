<?php

namespace App\Jobs;

use App\Enums\DiscoveryStage;
use App\Enums\JobStatus;
use App\Events\RoutesDiscovered;
use App\Exceptions\CollectionPaused;
use App\Http\Resources\DiscoveryResource;
use App\Models\Discovery;
use App\Services\Discovery\DomainDiscoverer;
use App\Services\ExtractionService;
use App\Support\Live;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

class DiscoverDomainJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $maxExceptions = 1;

    public int $tries = 0;

    public function __construct(public int $discoveryId, public string $domain)
    {
        $this->onQueue('discovery');
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping('domain:'.$this->domain))->shared()->releaseAfter(10)->expireAfter(960)];
    }

    public function handle(DomainDiscoverer $discoverer, ExtractionService $extraction): void
    {
        $discovery = Discovery::with('site')->find($this->discoveryId);

        if ($discovery === null || $discovery->status->isFinished()) {
            return;
        }

        if ($discovery->paused_at !== null) {
            $this->release(30);

            return;
        }

        try {
            $discoverer->run($discovery);
        } catch (CollectionPaused) {
            $this->release(30);

            return;
        }

        // Sites added from the web app go straight on to extracting the recommended pages.
        if ($discovery->auto_extract && $discovery->refresh()->status === JobStatus::Done && ! $discovery->scrapeJobs()->where('created_at', '>=', $discovery->started_at)->exists()) {
            $extraction->startRecommended($discovery);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $discovery = Discovery::with('site')->find($this->discoveryId);

        if ($discovery === null) {
            return;
        }

        $discovery->forceFill([
            'status' => JobStatus::Failed,
            'stage' => DiscoveryStage::Finished,
            'error' => mb_substr('Discovery failed: '.($exception?->getMessage() ?? 'unknown error'), 0, 2000),
            'finished_at' => now(),
        ])->save();

        $discovery->load('routes');
        Live::send(new RoutesDiscovered($discovery->id, (new DiscoveryResource($discovery))->resolve()));
    }
}
