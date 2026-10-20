<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class CatalogProductsQuery
{
    /**
     * @param array{search?: string|null, site?: int|string|null, category?: int|string|null, image?: string|null} $filters
     * @return Builder<Product>
     */
    public function for(User $user, array $filters = []): Builder
    {
        $query = Product::query()->whereHas('scrapeTargets.scrapeJob', fn ($jobs) => $jobs->where('user_id', $user->id))
            ->with(['site', 'category', 'image', 'scrapeTargets' => fn ($targets) => $targets
                ->whereHas('scrapeJob', fn ($jobs) => $jobs->where('user_id', $user->id))->with('discoveredRoute')]);

        if (! empty($filters['site'])) {
            $query->where('site_id', (int) $filters['site']);
        }
        if (($filters['category'] ?? null) === 'uncategorized') {
            $query->whereNull('category_id');
        } elseif (! empty($filters['category'])) {
            $query->where('category_id', (int) $filters['category']);
        }
        if (! empty($filters['search'])) {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($filters['search'])).'%';
            $query->where(fn ($products) => $products->where('name', 'like', $term)->orWhere('sku', 'like', $term)->orWhere('brand', 'like', $term));
        }
        if (in_array($filters['image'] ?? null, ['downloaded', 'pending', 'failed'], true)) {
            $query->whereHas('image', fn ($images) => $images->where('status', $filters['image']));
        }

        return $query->orderByDesc('products.updated_at')->orderByDesc('products.id');
    }
}
