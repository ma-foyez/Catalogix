<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ScrapeJob;
use Illuminate\Database\Eloquent\Builder;

/**
 * Products of one scrape job with the listing filters: route, category, search.
 */
final class JobProductsQuery
{
    /**
     * @param  array{route?: int|string|null, category?: int|string|null, search?: string|null, image?: string|null}  $filters
     * @return Builder<Product>
     */
    public function for(ScrapeJob $job, array $filters = []): Builder
    {
        $route = $filters['route'] ?? null;

        $query = Product::query()
            ->with(['category', 'image', 'site', 'scrapeTargets' => fn ($query) => $query
                ->where('scrape_job_id', $job->id)->with('discoveredRoute')])
            ->whereHas('scrapeTargets', function (Builder $targets) use ($job, $route): void {
                $targets->where('scrape_targets.scrape_job_id', $job->id);

                if ($route !== null && $route !== '') {
                    $targets->where('scrape_targets.id', (int) $route);
                }
            });

        if (($filters['category'] ?? null) === 'uncategorized') {
            $query->whereNull('products.category_id');
        } elseif (! empty($filters['category'])) {
            $query->where('products.category_id', (int) $filters['category']);
        }

        if (! empty($filters['search'])) {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], trim((string) $filters['search'])).'%';
            $query->where(fn (Builder $q) => $q->where('products.name', 'like', $term)->orWhere('products.sku', 'like', $term)->orWhere('products.brand', 'like', $term));
        }

        if (in_array($filters['image'] ?? null, ['pending', 'downloaded', 'failed'], true)) {
            $query->whereHas('image', fn (Builder $images) => $images->where('status', $filters['image']));
        } elseif (($filters['image'] ?? null) === 'none') {
            $query->whereDoesntHave('image');
        }

        return $query->orderBy('products.category_id')->orderBy('products.id');
    }
}
