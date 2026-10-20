<?php

namespace App\Models;

use App\Enums\ExtractionStrategy;
use App\Enums\TargetStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $scrape_job_id
 * @property int|null $discovered_route_id
 * @property string $url
 * @property TargetStatus $status
 * @property ExtractionStrategy|null $strategy_used
 * @property int $pages_crawled
 * @property int $products_found
 * @property-read int|null $products_count
 * @property string|null $error
 * @property-read ScrapeJob $scrapeJob
 * @property-read DiscoveredRoute|null $discoveredRoute
 */
#[Fillable([
    'scrape_job_id', 'discovered_route_id', 'url', 'status', 'strategy_used',
    'pages_crawled', 'products_found', 'error',
])]
class ScrapeTarget extends Model
{
    protected function casts(): array
    {
        return [
            'status' => TargetStatus::class,
            'strategy_used' => ExtractionStrategy::class,
        ];
    }

    /** @return BelongsTo<ScrapeJob, $this> */
    public function scrapeJob(): BelongsTo
    {
        return $this->belongsTo(ScrapeJob::class);
    }

    /** @return BelongsTo<DiscoveredRoute, $this> */
    public function discoveredRoute(): BelongsTo
    {
        return $this->belongsTo(DiscoveredRoute::class);
    }

    /** @return BelongsToMany<Product, $this> */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    public function site(): Site
    {
        return $this->scrapeJob->discovery->site
            ?? Site::forHost((string) parse_url($this->url, PHP_URL_HOST));
    }

    /**
     * The category name products found on this route are filed under.
     */
    public function categoryName(): ?string
    {
        return $this->discoveredRoute?->category_name;
    }
}
