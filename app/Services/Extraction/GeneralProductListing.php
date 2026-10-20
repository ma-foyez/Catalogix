<?php

namespace App\Services\Extraction;

final class GeneralProductListing
{
    public static function matches(string $url, ?string $name): bool
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $segment = basename($path);
        $normalize = fn (string $value) => strtolower(trim((string) preg_replace('/[\s_-]+/', ' ', $value)));
        $general = ['all', 'all products', 'all product', 'products', 'shop', 'catalog', 'catalogue', 'home'];

        return $path === '' || in_array($normalize($segment), $general, true) || in_array($normalize($name ?? ''), $general, true);
    }
}
