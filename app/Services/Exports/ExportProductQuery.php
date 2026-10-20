<?php

namespace App\Services\Exports;

use App\Enums\ExportScope;
use App\Models\Export;
use App\Models\Product;
use App\Services\JobProductsQuery;
use Illuminate\Database\Eloquent\Builder;

/**
 * The products an export covers: one route, one scrape job (site), or everything the user has extracted.
 * Always ordered by site, category, id so sheet rows and embedded pictures line up.
 */
final class ExportProductQuery
{
    /**
     * @return Builder<Product>
     */
    public function for(Export $export): Builder
    {
        $scope = $export->scope;
        $type = ExportScope::tryFrom($scope['type']) ?? ExportScope::Site;

        $query = Product::query()->with(['site', 'category', 'image']);

        match ($type) {
            ExportScope::Route => $query->whereHas(
                'scrapeTargets',
                fn (Builder $targets) => $targets->where('scrape_targets.id', (int) ($scope['scrape_target_id'] ?? 0)),
            ),
            ExportScope::Site => $query->whereHas(
                'scrapeTargets',
                fn (Builder $targets) => $targets->where('scrape_targets.scrape_job_id', (int) ($scope['scrape_job_id'] ?? $export->scrape_job_id)),
            ),
            ExportScope::All => $query->whereHas(
                'scrapeTargets.scrapeJob',
                fn (Builder $jobs) => $jobs->where('scrape_jobs.user_id', $export->user_id),
            ),
        };

        if (! empty($scope['category_id'])) {
            $query->where('products.category_id', (int) $scope['category_id']);
        }

        if (isset($scope['product_ids'])) {
            $query->whereIn('products.id', $scope['product_ids']);
        }

        if (! empty($scope['excluded_product_ids'])) {
            $query->whereNotIn('products.id', $scope['excluded_product_ids']);
        }

        if (! empty($scope['filters']) && $export->scrapeJob !== null) {
            $filtered = app(JobProductsQuery::class)->for($export->scrapeJob, $scope['filters']);
            $query->whereIn('products.id', $filtered->reorder()->select('products.id'));
        }

        return $query->orderBy('products.site_id')->orderBy('products.category_id')->orderBy('products.id');
    }
}
