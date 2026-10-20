<?php

namespace App\Services\Extraction\Strategies;

use App\Enums\ExtractionStrategy;
use App\Enums\Platform;
use App\Services\Crawling\PageFetcher;
use App\Services\Extraction\PageContext;
use App\Services\Extraction\StrategyResult;
use Illuminate\Support\Facades\Cache;

/**
 * Ladder step 3: WooCommerce Store API and Shopify products.json. Pagination uses the API's page parameter.
 */
final class PlatformApiStrategy implements Strategy
{
    public const int WOO_PER_PAGE = 50;

    public const int SHOPIFY_LIMIT = 250;

    public function __construct(private readonly PageFetcher $fetcher) {}

    public function key(): ExtractionStrategy
    {
        return ExtractionStrategy::PlatformApi;
    }

    public function extract(PageContext $context): StrategyResult
    {
        return match ($context->site->platform) {
            Platform::WooCommerce => $this->woocommerce($context),
            Platform::Shopify => $this->shopify($context),
            default => StrategyResult::empty(),
        };
    }

    private function woocommerce(PageContext $context): StrategyResult
    {
        $base = $this->origin($context->url);
        $query = ['per_page' => self::WOO_PER_PAGE, 'page' => $context->page];

        $slug = $this->wooCategorySlug($context->url);

        if ($slug !== null) {
            $categoryId = $this->wooCategoryId($base, $slug);

            if ($categoryId === null) {
                return StrategyResult::empty();
            }

            $query['category'] = $categoryId;
        }

        $data = $this->fetcher->json($base.'/wp-json/wc/store/v1/products?'.http_build_query($query));

        if (! is_array($data) || ! array_is_list($data)) {
            return StrategyResult::empty();
        }

        $products = [];

        foreach ($data as $item) {
            if (! is_array($item) || empty($item['name'])) {
                continue;
            }

            $prices = (array) ($item['prices'] ?? []);
            $minor = (int) ($prices['currency_minor_unit'] ?? 0);
            $toMajor = fn ($value) => is_numeric($value) && (float) $value > 0 ? (float) $value / (10 ** $minor) : null;

            $products[] = [
                'name' => $item['name'],
                'price' => $toMajor($prices['price'] ?? null),
                'regular_price' => $toMajor($prices['regular_price'] ?? null),
                'sale_price' => $toMajor($prices['sale_price'] ?? null),
                'currency' => $prices['currency_code'] ?? null,
                'image' => $item['images'][0]['src'] ?? null,
                'url' => $item['permalink'] ?? null,
                'sku' => $item['sku'] ?? null,
                'in_stock' => $item['is_in_stock'] ?? null,
                'category' => $item['categories'][0]['name'] ?? null,
                'description' => ($item['short_description'] ?? '') !== '' ? $item['short_description'] : ($item['description'] ?? null),
            ];
        }

        return new StrategyResult(products: $products, hasMorePages: count($data) >= self::WOO_PER_PAGE);
    }

    private function shopify(PageContext $context): StrategyResult
    {
        $base = $this->origin($context->url);
        $handle = $this->shopifyCollection($context->url);
        $path = $handle !== null ? "/collections/{$handle}/products.json" : '/products.json';

        $data = $this->fetcher->json($base.$path.'?'.http_build_query(['limit' => self::SHOPIFY_LIMIT, 'page' => $context->page]));
        $items = is_array($data) ? ($data['products'] ?? null) : null;

        if (! is_array($items)) {
            return StrategyResult::empty();
        }

        $products = [];

        foreach ($items as $item) {
            if (! is_array($item) || empty($item['title'])) {
                continue;
            }

            $variant = $item['variants'][0] ?? [];

            $products[] = [
                'name' => $item['title'],
                'price' => $variant['price'] ?? null,
                'regular_price' => $variant['compare_at_price'] ?? null,
                'image' => $item['images'][0]['src'] ?? null,
                'url' => isset($item['handle']) ? $base.'/products/'.$item['handle'] : null,
                'sku' => $variant['sku'] ?? null,
                'brand' => $item['vendor'] ?? null,
                'in_stock' => $variant['available'] ?? null,
                'category' => $item['product_type'] ?? null,
                'description' => $item['body_html'] ?? null,
            ];
        }

        return new StrategyResult(products: $products, hasMorePages: count($items) >= self::SHOPIFY_LIMIT);
    }

    /**
     * "/product-category/vegetables/leafy/" gives "leafy"; "?product_cat=leafy" also works.
     */
    public function wooCategorySlug(string $url): ?string
    {
        $queryString = (string) parse_url($url, PHP_URL_QUERY);
        parse_str($queryString, $query);

        if (! empty($query['product_cat']) && is_string($query['product_cat'])) {
            return $query['product_cat'];
        }

        $path = (string) parse_url($url, PHP_URL_PATH);

        if (preg_match('#/product-category/(.+?)/?(?:page/\d+/?)?$#', $path, $match)) {
            $segments = explode('/', trim($match[1], '/'));

            return urldecode((string) end($segments));
        }

        return null;
    }

    private function wooCategoryId(string $base, string $slug): ?int
    {
        return Cache::remember("woo-cat:{$base}:{$slug}", now()->addHour(), function () use ($base, $slug): ?int {
            for ($page = 1; $page <= 5; $page++) {
                $categories = $this->fetcher->json($base.'/wp-json/wc/store/v1/products/categories?'.http_build_query(['per_page' => 100, 'page' => $page]));

                if (! is_array($categories) || $categories === []) {
                    return null;
                }

                foreach ($categories as $category) {
                    if (is_array($category) && ($category['slug'] ?? null) === $slug) {
                        return (int) $category['id'];
                    }
                }

                if (count($categories) < 100) {
                    return null;
                }
            }

            return null;
        });
    }

    public function shopifyCollection(string $url): ?string
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        if (preg_match('#/collections/([^/]+)#', $path, $match) && $match[1] !== 'all') {
            return $match[1];
        }

        return null;
    }

    private function origin(string $url): string
    {
        $parts = parse_url($url);

        return ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');
    }
}
