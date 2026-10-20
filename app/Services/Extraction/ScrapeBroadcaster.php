<?php

namespace App\Services\Extraction;

use App\Events\ProductsFound;
use App\Events\ScrapeProgress;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ScrapeJobResource;
use App\Models\Product;
use App\Models\ScrapeJob;
use App\Models\ScrapeTarget;
use App\Support\Live;

/**
 * Builds the payloads the live listing screen consumes.
 */
final class ScrapeBroadcaster
{
    /**
     * @param  list<int>  $productIds
     */
    public function products(ScrapeTarget $target, array $productIds): void
    {
        if ($productIds === []) {
            return;
        }

        $products = Product::query()
            ->with(['category', 'image', 'site'])
            ->whereIn('id', $productIds)
            ->get();

        Live::send(new ProductsFound(
            $target->scrape_job_id,
            $target->id,
            ProductResource::collection($products)->resolve(),
        ));
    }

    public function progress(ScrapeJob $job): void
    {
        $job->loadMissing(['discovery.site', 'targets.discoveredRoute']);

        Live::send(new ScrapeProgress($job->id, (new ScrapeJobResource($job))->resolve()));
    }
}
