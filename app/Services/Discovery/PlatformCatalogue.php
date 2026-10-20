<?php

namespace App\Services\Discovery;

use App\Enums\Platform;
use App\Services\Crawling\PageFetcher;

/**
 * The platform's own category list with product counts:
 * WooCommerce /wp-json/wc/store/v1/products/categories and Shopify /collections.json.
 */
final class PlatformCatalogue
{
    public function __construct(private readonly PageFetcher $fetcher) {}

    /**
     * @return array{platform: Platform|null, routes: list<array{url: string, name: string, count: int}>}
     */
    public function read(Platform $platform, string $base, string $homeHtml): array
    {
        if ($platform === Platform::Shopify) {
            return ['platform' => Platform::Shopify, 'routes' => $this->shopify($base)];
        }

        if ($platform === Platform::WooCommerce || str_contains($homeHtml, 'wp-content')) {
            $routes = $this->woocommerce($base);

            return ['platform' => $routes !== [] ? Platform::WooCommerce : null, 'routes' => $routes];
        }

        return ['platform' => null, 'routes' => []];
    }

    /**
     * @return list<array{url: string, name: string, count: int}>
     */
    private function woocommerce(string $base): array
    {
        $routes = [];

        for ($page = 1; $page <= 3; $page++) {
            $categories = $this->fetcher->json($base.'/wp-json/wc/store/v1/products/categories?'.http_build_query(['per_page' => 100, 'page' => $page]));

            if (! is_array($categories) || ! array_is_list($categories) || $categories === []) {
                break;
            }

            foreach ($categories as $category) {
                if (! is_array($category) || empty($category['name'])) {
                    continue;
                }

                $routes[] = [
                    'url' => (string) ($category['permalink'] ?? $base.'/product-category/'.($category['slug'] ?? '').'/'),
                    'name' => html_entity_decode((string) $category['name'], ENT_QUOTES | ENT_HTML5),
                    'count' => (int) ($category['count'] ?? 0),
                ];
            }

            if (count($categories) < 100) {
                break;
            }
        }

        return $routes;
    }

    /**
     * @return list<array{url: string, name: string, count: int}>
     */
    private function shopify(string $base): array
    {
        $data = $this->fetcher->json($base.'/collections.json?limit=250');
        $routes = [];

        foreach ((array) (is_array($data) ? ($data['collections'] ?? []) : []) as $collection) {
            if (! is_array($collection) || empty($collection['handle'])) {
                continue;
            }

            $routes[] = [
                'url' => $base.'/collections/'.$collection['handle'],
                'name' => (string) ($collection['title'] ?? $collection['handle']),
                'count' => (int) ($collection['products_count'] ?? 0),
            ];
        }

        return $routes;
    }
}
