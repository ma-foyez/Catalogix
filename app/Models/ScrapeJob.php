<?php

namespace App\Models;

use App\Enums\JobStatus;
use App\Enums\TargetStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ScrapeJobFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $discovery_id
 * @property JobStatus $status
 * @property int|null $max_products
 * @property int $min_products
 * @property int $max_pages
 * @property int $max_routes
 * @property CarbonImmutable|null $paused_at
 * @property int $total_routes
 * @property int $total_products
 * @property int $total_images
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $finished_at
 * @property-read Discovery|null $discovery
 * @property-read int|null $targets_count
 * @property-read int|null $finished_targets_count
 */
#[Fillable([
    'user_id', 'discovery_id', 'status', 'total_routes', 'total_products',
    'total_images', 'started_at', 'finished_at', 'max_products', 'min_products', 'max_pages', 'max_routes', 'paused_at',
])]
class ScrapeJob extends Model
{
    /** @use HasFactory<ScrapeJobFactory> */
    use HasFactory;

    protected $attributes = ['min_products' => 1, 'max_pages' => 30, 'max_routes' => 25];

    protected function casts(): array
    {
        return [
            'status' => JobStatus::class,
            'max_products' => 'integer',
            'paused_at' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Discovery, $this> */
    public function discovery(): BelongsTo
    {
        return $this->belongsTo(Discovery::class);
    }

    /** @return HasMany<ScrapeTarget, $this> */
    public function targets(): HasMany
    {
        return $this->hasMany(ScrapeTarget::class);
    }

    /** @return HasMany<Export, $this> */
    public function exports(): HasMany
    {
        return $this->hasMany(Export::class);
    }

    public function site(): ?Site
    {
        return $this->discovery?->site;
    }

    public function isPaused(): bool
    {
        return $this->paused_at !== null || $this->discovery?->paused_at !== null;
    }

    public function limitReached(): bool
    {
        return $this->max_products !== null && $this->total_products >= $this->max_products;
    }

    /**
     * Image downloads of this job's products by status.
     *
     * @return array{pending: int, downloaded: int, failed: int}
     */
    public function imageStats(): array
    {
        $counts = ProductImage::query()
            ->whereHas('product.scrapeTargets', fn ($query) => $query->where('scrape_targets.scrape_job_id', $this->id))
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            'pending' => (int) ($counts['pending'] ?? 0),
            'downloaded' => (int) ($counts['downloaded'] ?? 0),
            'failed' => (int) ($counts['failed'] ?? 0),
        ];
    }

    /**
     * Recount products and images, and close the job once every target has finished.
     */
    public function refreshTotals(): void
    {
        $this->total_products = Product::query()
            ->whereHas('scrapeTargets', fn ($query) => $query->where('scrape_job_id', $this->id))
            ->count();
        $this->total_images = ProductImage::query()
            ->whereHas('product.scrapeTargets', fn ($query) => $query->where('scrape_job_id', $this->id))
            ->where('status', 'downloaded')
            ->count();

        $statuses = $this->targets()->select('status')->distinct()->pluck('status')->map(fn ($status) => $status instanceof TargetStatus ? $status : TargetStatus::from($status));

        if ($statuses->isNotEmpty() && $statuses->every(fn (TargetStatus $status) => $status->isFinished())) {
            $this->status = $statuses->contains(TargetStatus::Done) ? JobStatus::Done : JobStatus::Failed;
            $this->finished_at ??= now();
        }

        $this->save();
    }
}
