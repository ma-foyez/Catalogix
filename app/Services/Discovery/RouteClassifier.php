<?php

namespace App\Services\Discovery;

use App\Services\Crawling\UrlTools;

/**
 * URL-pattern rules from the discovery spec: keep listing paths, group product-detail links
 * under their parent category, drop blog, account and checkout pages.
 */
final class RouteClassifier
{
    public function isIgnored(string $url): bool
    {
        if (UrlTools::isAsset($url)) {
            return true;
        }

        $path = strtolower(UrlTools::pathWithQuery($url));

        foreach ((array) config('catalogix.discovery.ignore_patterns') as $pattern) {
            if (str_contains($path, strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }

    public function isListing(string $url): bool
    {
        $path = strtolower(UrlTools::pathWithQuery($url));

        foreach ((array) config('catalogix.discovery.listing_patterns') as $pattern) {
            if (str_contains($path, strtolower($pattern))) {
                return true;
            }
        }

        return false;
    }

    /**
     * Product detail pages: /product/x, /products/x, /p/x, /item/x (one segment after the marker).
     */
    public function isProduct(string $url): bool
    {
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        return (bool) preg_match('#/(product|products|p|item)/[^/]+/?$#', $path);
    }

    /**
     * The listing a product link belongs to, e.g. /collections/fruits/products/mango gives /collections/fruits.
     */
    public function parentListing(string $url): ?string
    {
        $parts = parse_url($url);
        $path = (string) ($parts['path'] ?? '');

        if (! isset($parts['host']) || ! preg_match('#^(.*?/(?:collections|category|categories|product-category|c)/[^/]+)/(?:products?|p)/#i', $path, $match)) {
            return null;
        }

        return ($parts['scheme'] ?? 'https').'://'.$parts['host'].$match[1];
    }
}
