<?php

namespace App\Jobs;

use App\Enums\ImageStatus;
use App\Events\ImageDownloaded;
use App\Exceptions\ImageDownloadException;
use App\Http\Resources\ProductImageResource;
use App\Jobs\Middleware\ConcurrencySlots;
use App\Models\ProductImage;
use App\Models\ScrapeTarget;
use App\Services\Images\ImageDownloader;
use App\Support\Live;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * One job per image, at most 3-5 running per site at a time (ConcurrencySlots), with a short pause after each.
 */
class DownloadImageJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $timeout = 60;

    public int $maxExceptions = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public int $uniqueFor = 1800;

    public function __construct(public int $imageId, public string $domain)
    {
        $this->onQueue('images');
    }

    public function uniqueId(): string
    {
        return (string) $this->imageId;
    }

    public function retryUntil(): DateTimeInterface
    {
        return now()->addMinutes(45);
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [new ConcurrencySlots('images:'.$this->domain, (int) config('catalogix.images.concurrency_per_site', 4))];
    }

    public function handle(ImageDownloader $downloader): void
    {
        $image = ProductImage::find($this->imageId);

        if ($image === null || $image->status === ImageStatus::Downloaded) {
            return;
        }

        try {
            $downloader->download($image);
        } catch (ImageDownloadException $e) {
            if ($e->retryable && $this->attempts() < (int) config('catalogix.images.max_attempts', 3)) {
                throw $e;
            }

            $downloader->markFailed($image, $e->getMessage());
        }

        $this->announce($image->refresh());

        $pause = (int) config('catalogix.images.delay_ms', 300);

        if ($pause > 0) {
            usleep($pause * 1000);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $image = ProductImage::find($this->imageId);

        if ($image !== null && $image->status !== ImageStatus::Downloaded) {
            app(ImageDownloader::class)->markFailed($image, $exception?->getMessage() ?? 'Download failed.');
            $this->announce($image->refresh());
        }
    }

    private function announce(ProductImage $image): void
    {
        $jobIds = ScrapeTarget::query()
            ->whereHas('products', fn ($query) => $query->whereKey($image->product_id))
            ->distinct()
            ->pluck('scrape_job_id')
            ->all();

        if ($jobIds !== []) {
            Live::send(new ImageDownloaded($jobIds, $image->product_id, (new ProductImageResource($image))->resolve()));
        }
    }
}
