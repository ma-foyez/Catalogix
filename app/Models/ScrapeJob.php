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
 * @property int $total_routes
 * @property int $total_products
 * @property int $total_images
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $finished_at
 * @property-read Discovery|null $discovery
 */
#[Fillable([
    'user_id', 'discovery_id', 'status', 'total_routes', 'total_products',
    'total_images', 'started_at', 'finished_at',
])]
class ScrapeJob extends Model
{
    /** @use HasFactory<ScrapeJobFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => JobStatus::class,
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

    /**
     * Recount products and images, and close the job once every target has finished.
     */
    public function refreshTotals(): void
    {
        $targetIds = $this->targets()->pluck('id');

        $productIds = Product::query()
            ->whereHas('scrapeTargets', fn ($query) => $query->whereIn('scrape_targets.id', $targetIds))
            ->pluck('id');

        $this->total_products = $productIds->count();
        $this->total_images = ProductImage::query()
            ->whereIn('product_id', $productIds)
            ->where('status', 'downloaded')
            ->count();

        $statuses = $this->targets()->pluck('status')->map(fn ($status) => $status instanceof TargetStatus ? $status : TargetStatus::from($status));

        if ($statuses->isNotEmpty() && $statuses->every(fn (TargetStatus $status) => $status->isFinished())) {
            $this->status = $statuses->contains(TargetStatus::Done) ? JobStatus::Done : JobStatus::Failed;
            $this->finished_at ??= now();
        }

        $this->save();
    }
}
