<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Product;
use App\Models\ScrapeTarget;
use App\Services\Extraction\GeneralProductListing;
use Illuminate\Console\Command;

class RepairProductCategories extends Command
{
    protected $signature = 'catalogix:repair-categories {--site= : Restrict to a website domain} {--dry-run : Count repairs without saving}';

    protected $description = 'Replace broad listing labels with product categories or the most specific collected section';

    public function handle(): int
    {
        $changed = 0;
        Product::query()->with(['category', 'site', 'scrapeTargets' => fn ($query) => $query->with('discoveredRoute')->withCount('products')])
            ->when($this->option('site'), fn ($query, $domain) => $query->whereHas('site', fn ($sites) => $sites->where('domain', $domain)))
            ->chunkById(100, function ($products) use (&$changed): void {
                foreach ($products as $product) {
                    if ($product->category !== null && ! GeneralProductListing::matches('https://'.$product->site->domain.'/category', $product->category->name)) {
                        continue;
                    }

                    $own = $product->raw['category'] ?? null;
                    $route = $product->scrapeTargets
                        ->reject(fn (ScrapeTarget $target) => GeneralProductListing::matches($target->url, $target->categoryName()) || $target->categoryName() === null)
                        ->sortBy(fn (ScrapeTarget $target) => [$target->products_count ?: PHP_INT_MAX, $target->categoryName(), $target->url])->first();
                    $name = is_string($own) && trim($own) !== '' && ! GeneralProductListing::matches('https://'.$product->site->domain.'/category', $own)
                        ? trim($own) : $route?->categoryName();

                    if ($product->category === null && $name === null) {
                        continue;
                    }

                    $changed++;
                    if (! $this->option('dry-run')) {
                        $category = $name !== null ? Category::resolve($product->site, $name, $route?->url) : null;
                        $product->forceFill(['category_id' => $category?->id, 'subcategory' => null])->save();
                    }
                }
            });

        $this->info(($this->option('dry-run') ? 'Would repair ' : 'Repaired ').$changed.' product categories.');

        return self::SUCCESS;
    }
}
