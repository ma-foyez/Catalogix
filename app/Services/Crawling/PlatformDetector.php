<?php

namespace App\Services\Crawling;

use App\Enums\Platform;

final class PlatformDetector
{
    public function detect(string $html): Platform
    {
        $lower = strtolower($html);

        if (str_contains($lower, 'cdn.shopify.com') || str_contains($lower, 'shopify.theme') || str_contains($lower, '.myshopify.com')) {
            return Platform::Shopify;
        }

        if (str_contains($lower, 'woocommerce') || preg_match('/<meta[^>]+name=["\']generator["\'][^>]+content=["\']woocommerce/i', $html)) {
            return Platform::WooCommerce;
        }

        if (str_contains($lower, 'wp-content')) {
            // WordPress without WooCommerce markers: may still be a shop, the Store API probe decides.
            return Platform::Custom;
        }

        return trim($html) === '' ? Platform::Unknown : Platform::Custom;
    }
}
