<?php

namespace App\Services\Extraction;

use App\Enums\ImageStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ScrapeTarget;
use App\Models\Site;
use Illuminate\Support\Facades\DB;

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
        if ($products === []) {
            return new UpsertResult([], 0, []);
        }

        $knownHashes = Product::query()
            ->join('product_scrape_target', 'product_scrape_target.product_id', '=', 'products.id')
            ->where('product_scrape_target.scrape_target_id', $target->id)
            ->pluck('products.hash')
            ->flip();

        $routeCategory = $target->categoryName();
        $now = now();
        $productIds = [];
        $newForTarget = 0;

        foreach (array_chunk($products, self::BATCH) as $batch) {
            $rows = [];

            foreach ($batch as $product) {
                $category = $routeCategory ?? $product['category'] ?? null;

                $rows[] = [
                    'site_id' => $site->id,
                    'category_id' => $category !== null ? $this->categoryId($site, $category, $target->url) : null,
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
                ['category_id', 'name', 'price', 'sale_price', 'currency', 'product_url', 'sku', 'brand', 'in_stock', 'raw', 'last_seen_at', 'updated_at'],
            );

            $ids = Product::query()
                ->where('site_id', $site->id)
                ->whereIn('hash', array_column($batch, 'hash'))
                ->pluck('id', 'hash');

            DB::table('product_scrape_target')->insertOrIgnore(
                $ids->values()->map(fn (int $id) => ['product_id' => $id, 'scrape_target_id' => $target->id])->all(),
            );

            $this->queueImages($batch, $ids->all());

            array_push($productIds, ...$ids->values()->all());
        }

        $pendingImageIds = ProductImage::query()
            ->whereIn('product_id', $productIds)
            ->where('status', ImageStatus::Pending)
            ->pluck('id')
            ->all();

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

    private function categoryId(Site $site, string $name, string $sourceUrl): int
    {
        return $this->categoryIds[$site->id.'|'.$name] ??= Category::resolve($site, $name, $sourceUrl)->id;
    }
}
