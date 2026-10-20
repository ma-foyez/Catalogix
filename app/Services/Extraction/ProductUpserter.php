<?php

namespace App\Services\Extraction;

use App\Enums\ImageStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ScrapeJob;
use App\Models\ScrapeTarget;
use App\Models\Site;
use App\Services\Crawling\UrlTools;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Saves normalized products in batches of 50 with upsert() on (site_id, hash), links them to the
 * route that found them, and queues one image row per product.
 */
final class ProductUpserter
{
    private const int BATCH = 50;

    /** @var array<string, int> */
    private array $categoryIds = [];

    /**
     * @param  list<array<string, mixed>>  $products
     */
    public function save(ScrapeTarget $target, Site $site, array $products): UpsertResult
    {
        // Serialize the budget check and product links, including workers on different routes.
        return DB::transaction(function () use ($target, $site, $products): UpsertResult {
            $job = ScrapeJob::query()->lockForUpdate()->findOrFail($target->scrape_job_id);

            if ($job->max_products !== null) {
                $known = Product::query()
                    ->whereHas('scrapeTargets', fn ($query) => $query->where('scrape_job_id', $job->id))
                    ->pluck('hash')->flip();
                $remaining = max(0, $job->max_products - $known->count());
                $accepted = [];

                foreach ($products as $product) {
                    $hash = $product['hash'];

                    if (isset($accepted[$hash])) {
                        continue;
                    }

                    if (! $known->has($hash)) {
                        if ($remaining === 0) {
                            continue;
                        }

                        $remaining--;
                    }

                    $accepted[$hash] = $product;
                }

                $products = array_values($accepted);
            }

            return $this->persist($target, $site, $products);
        });
    }

    /** @param list<array<string, mixed>> $products */
    private function persist(ScrapeTarget $target, Site $site, array $products): UpsertResult
    {
        if ($products === []) {
            return new UpsertResult([], 0, []);
        }

        $knownHashes = Product::query()
            ->join('product_scrape_target', 'product_scrape_target.product_id', '=', 'products.id')
            ->where('product_scrape_target.scrape_target_id', $target->id)
            ->pluck('products.hash')
            ->flip();

        // The home page lists products from everywhere: it never names their category.
        $isHome = UrlTools::isHomePage($target->url);
        [$routeCategory, $routeSubcategory] = $isHome ? [null, null] : $this->routeCategories($target);
        $now = now();
        $productIds = [];
        $newForTarget = 0;

        foreach (array_chunk($products, self::BATCH) as $batch) {
            $rows = [];
            $placement = [];

            foreach ($batch as $product) {
                $own = $product['category'] ?? null;
                $category = $routeCategory ?? $own;
                $subcategory = $routeSubcategory
                    ?? ($routeCategory !== null && $own !== null && mb_strtolower($own) !== mb_strtolower($routeCategory) ? $own : null);
                $categoryId = $category !== null ? $this->categoryId($site, $category, $target->url) : null;
                $placement[$product['hash']] = [$categoryId, $subcategory];

                $rows[] = [
                    'site_id' => $site->id,
                    'category_id' => $categoryId,
                    'subcategory' => $subcategory,
                    'description' => $product['description'] ?? null,
                    'hash' => $product['hash'],
                    'name' => $product['name'],
                    'price' => $product['price'],
                    'sale_price' => $product['sale_price'],
                    'currency' => $product['currency'],
                    'product_url' => $product['product_url'],
                    'sku' => $product['sku'],
                    'brand' => $product['brand'],
                    'in_stock' => $product['in_stock'],
                    'raw' => json_encode($product['raw'], JSON_UNESCAPED_UNICODE),
                    'first_seen_at' => $now,
                    'last_seen_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];

                if (! $knownHashes->has($product['hash'])) {
                    $newForTarget++;
                }
            }

            Product::upsert(
                $rows,
                ['site_id', 'hash'],
                ['name', 'price', 'sale_price', 'currency', 'product_url', 'sku', 'brand', 'description', 'in_stock', 'raw', 'last_seen_at', 'updated_at'],
            );

            $ids = Product::query()
                ->where('site_id', $site->id)
                ->whereIn('hash', array_column($batch, 'hash'))
                ->pluck('id', 'hash');

            if ($isHome) {
                // Earlier versions labelled home-page products "Home"; that is not a category.
                Product::query()
                    ->whereIn('id', $ids->values())
                    ->whereIn('category_id', Category::query()->where('site_id', $site->id)->where('slug', 'home')->select('id'))
                    ->update(['category_id' => null]);
            }

            $this->place($ids->all(), $placement, $isHome);

            DB::table('product_scrape_target')->insertOrIgnore(
                $ids->values()->map(fn (int $id) => ['product_id' => $id, 'scrape_target_id' => $target->id])->all(),
            );

            $this->queueImages($batch, $ids->all());

            array_push($productIds, ...$ids->values()->all());
        }

        $pendingImageIds = array_values(ProductImage::query()
            ->whereIn('product_id', $productIds)
            ->where('status', ImageStatus::Pending)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all());

        return new UpsertResult($productIds, $newForTarget, $pendingImageIds);
    }

    /**
     * @param  list<array<string, mixed>>  $batch
     * @param  array<string, int>  $idsByHash
     */
    private function queueImages(array $batch, array $idsByHash): void
    {
        $now = now();
        $rows = [];

        foreach ($batch as $product) {
            $productId = $idsByHash[$product['hash']] ?? null;

            if ($productId === null || empty($product['image_url'])) {
                continue;
            }

            $rows[] = [
                'product_id' => $productId,
                'source_url' => $product['image_url'],
                'url_hash' => sha1($product['image_url']),
                'status' => ImageStatus::Pending->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows !== []) {
            DB::table('product_images')->insertOrIgnore($rows);
        }
    }

    /**
     * Category pages set a product's category and subcategory; the home page only fills in
     * products that have none yet.
     *
     * @param  array<string, int>  $idsByHash
     * @param  array<string, array{0: int|null, 1: string|null}>  $placement
     */
    private function place(array $idsByHash, array $placement, bool $isHome): void
    {
        $groups = [];

        foreach ($placement as $hash => [$categoryId, $subcategory]) {
            if ($categoryId !== null && isset($idsByHash[$hash])) {
                $groups[$categoryId.'|'.$subcategory][] = $idsByHash[$hash];
            }
        }

        foreach ($groups as $key => $productIds) {
            [$categoryId, $subcategory] = explode('|', (string) $key, 2);

            Product::query()
                ->whereIn('id', $productIds)
                ->when($isHome, fn ($query) => $query->whereNull('category_id'))
                ->update(['category_id' => (int) $categoryId, 'subcategory' => $subcategory !== '' ? $subcategory : null]);
        }
    }

    /**
     * "/product-category/furniture/chairs/" → ["Furniture", "Chairs"]; a one-level route keeps
     * its own name and no subcategory.
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function routeCategories(ScrapeTarget $target): array
    {
        $name = $target->categoryName();
        $path = (string) parse_url($target->url, PHP_URL_PATH);
        $generic = ['category', 'categories', 'product-category', 'collections', 'collection', 'shop', 'c', 'catalog', 'department', 'products', 'page'];

        $segments = array_values(array_filter(
            explode('/', trim($path, '/')),
            fn (string $segment) => $segment !== '' && ! ctype_digit($segment) && ! in_array(strtolower($segment), $generic, true),
        ));

        if (count($segments) >= 2) {
            return [Str::headline(urldecode($segments[count($segments) - 2])), $name ?? Str::headline(urldecode(end($segments)))];
        }

        return [$name, null];
    }

    private function categoryId(Site $site, string $name, string $sourceUrl): int
    {
        return $this->categoryIds[$site->id.'|'.$name] ??= Category::resolve($site, $name, $sourceUrl)->id;
    }
}
