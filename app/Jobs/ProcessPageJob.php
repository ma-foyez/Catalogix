<?php

namespace App\Jobs;

use App\Enums\ExtractionStrategy;
use App\Enums\JobStatus;
use App\Enums\TargetStatus;
use App\Models\ScrapeTarget;
use App\Services\Crawling\CrawlDelay;
use App\Services\Crawling\UrlTools;
use App\Services\Extraction\PageOutcome;
use App\Services\Extraction\PageProcessor;
use App\Services\Extraction\ScrapeBroadcaster;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

/**
 * Extracts one page of one route, then queues itself for the next page after a 1-3 s pause.
 * WithoutOverlapping keeps it to one page at a time per domain.
 */
class ProcessPageJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 180;

    public int $maxExceptions = 2;

    /** @var list<int> */
    public array $backoff = [15, 60];

    public function __construct(
        public int $targetId,
        public string $url,
        public int $page = 1,
        public ?string $strategy = null,
    ) {
        $this->onQueue('pages');
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addHours(2);
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('domain:'.(UrlTools::host($this->url) ?? 'unknown')))
                ->releaseAfter(5)
                ->expireAfter(240),
        ];
    }

    public function handle(PageProcessor $processor, ScrapeBroadcaster $broadcaster, CrawlDelay $delay): void
    {
        $target = ScrapeTarget::with(['scrapeJob.discovery.site', 'discoveredRoute'])->find($this->targetId);

        if ($target === null || $target->status->isFinished()) {
            return;
        }

        $job = $target->scrapeJob;

        if ($job->status === JobStatus::Queued) {
            $job->forceFill(['status' => JobStatus::Running, 'started_at' => now()])->save();
        }

        if ($target->status === TargetStatus::Queued) {
            $target->forceFill(['status' => TargetStatus::Crawling])->save();
            $broadcaster->progress($job);
        }

        $outcome = $processor->process(
            $target,
            $this->page,
            $this->url,
            $this->strategy !== null ? ExtractionStrategy::tryFrom($this->strategy) : null,
        );

        if ($outcome->saved !== null) {
            $target->pages_crawled++;
            $target->products_found += $outcome->saved->newForTarget;
        }

        $target->strategy_used ??= $outcome->strategy;
        $target->status = match ($outcome->status) {
            PageOutcome::NEXT => TargetStatus::Crawling,
            PageOutcome::BLOCKED => TargetStatus::Blocked,
            PageOutcome::FAILED => TargetStatus::Failed,
            default => TargetStatus::Done,
        };
        $target->error = $outcome->message;
        $target->save();

        if ($outcome->saved !== null) {
            $domain = $target->site()->domain;

            foreach ($outcome->saved->pendingImageIds as $imageId) {
                DownloadImageJob::dispatch($imageId, $domain);
            }

            $broadcaster->products($target, $outcome->saved->productIds);
        }

        if ($outcome->hasNext()) {
            self::dispatch($target->id, (string) $outcome->nextUrl, $this->page + 1, $outcome->strategy?->value)
                ->delay(now()->addSeconds($delay->seconds()));
        }

        $job->refreshTotals();
        $broadcaster->progress($job);
    }

    public function failed(?Throwable $exception): void
    {
        $target = ScrapeTarget::with('scrapeJob')->find($this->targetId);

        if ($target === null) {
            return;
        }

        $target->forceFill([
            'status' => TargetStatus::Failed,
            'error' => mb_substr('Page '.$this->page.' failed: '.($exception?->getMessage() ?? 'unknown error'), 0, 2000),
        ])->save();

        $target->scrapeJob->refreshTotals();
        app(ScrapeBroadcaster::class)->progress($target->scrapeJob);
    }
}
