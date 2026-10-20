<?php

namespace App\Services\Extraction;

use App\Services\Crawling\UrlTools;

/**
 * Turns the loose product arrays every strategy produces into one clean shape:
 * name, price, sale_price, currency, product_url, image_url, sku, brand, in_stock, category, description, hash, raw.
 *
 * Raw input keys understood: name, price, regular_price, sale_price, prices (list), image, images,
 * url, sku, brand, in_stock, availability, category, description, currency.
 */
final class ProductNormalizer
{
    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>|null
     */
    public function normalize(array $raw, string $pageUrl, ?string $category = null): ?array
    {
        $name = $this->cleanText($raw['name'] ?? null);

        if ($name === null || mb_strlen($name) < 2 || PriceParser::looksLikePrice($name) && mb_strlen($name) < 15) {
            return null;
        }

        [$price, $salePrice] = $this->prices($raw);
        $productUrl = $this->url($raw['url'] ?? null, $pageUrl);
        $imageUrl = $this->image($raw['image'] ?? ($raw['images'] ?? null), $pageUrl);

        $currency = is_string($raw['currency'] ?? null) && preg_match('/^[A-Z]{3}$/', strtoupper($raw['currency']))
            ? strtoupper($raw['currency'])
            : 'BDT';

        return [
            'name' => mb_substr($name, 0, 500),
            'price' => $price,
            'sale_price' => $salePrice,
            'currency' => $currency,
            'product_url' => $productUrl,
            'image_url' => $imageUrl,
            'sku' => $this->cleanText($raw['sku'] ?? null, 190),
            'brand' => $this->cleanText(is_array($raw['brand'] ?? null) ? ($raw['brand']['name'] ?? null) : ($raw['brand'] ?? null), 190),
            'in_stock' => $this->stock($raw['in_stock'] ?? ($raw['availability'] ?? null)),
            'category' => $category ?? $this->cleanText($raw['category'] ?? null, 190),
            'description' => $this->cleanText($raw['description'] ?? null, 2000),
            'hash' => $productUrl !== null
                ? sha1($productUrl)
                : sha1(mb_strtolower($name).'|'.($price ?? '').'|'.($imageUrl ?? '')),
            'raw' => $this->compactRaw($raw),
        ];
    }

    /**
     * The higher of two prices is the regular price and the lower one the sale price.
     *
     * @param  array<string, mixed>  $raw
     * @return array{0: float|null, 1: float|null}
     */
    private function prices(array $raw): array
    {
        $regular = PriceParser::parse($raw['regular_price'] ?? null);
        $sale = PriceParser::parse($raw['sale_price'] ?? null);

        $candidates = [];

        foreach ((array) ($raw['prices'] ?? []) as $value) {
            $candidates[] = PriceParser::parse($value);
        }

        $price = $raw['price'] ?? null;

        if (is_string($price) && count(PriceParser::allMarked($price)) > 1) {
            array_push($candidates, ...PriceParser::allMarked($price));
        } else {
            $candidates[] = PriceParser::parse($price);
        }

        $candidates = array_values(array_unique(array_filter([...$candidates, $regular, $sale], fn ($value) => $value !== null)));

        if ($candidates === []) {
            return [null, null];
        }

        if ($regular !== null && $sale !== null) {
            return $sale < $regular ? [$regular, $sale] : [$regular, null];
        }

        $highest = max($candidates);
        $lowest = min($candidates);

        return [$highest, $lowest < $highest ? $lowest : null];
    }

    private function url(mixed $value, string $pageUrl): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $absolute = UrlTools::absolute($value, $pageUrl);

        return $absolute !== null ? UrlTools::clean($absolute) : null;
    }

    /**
     * First usable image: a string, the first entry of a list, an object with url/src, or the widest srcset entry.
     */
    private function image(mixed $value, string $pageUrl): ?string
    {
        if (is_array($value)) {
            if (array_is_list($value)) {
                foreach ($value as $item) {
                    if ($url = $this->image($item, $pageUrl)) {
                        return $url;
                    }
                }

                return null;
            }

            return $this->image($value['url'] ?? $value['src'] ?? $value['contentUrl'] ?? $value['original'] ?? $value['large'] ?? null, $pageUrl);
        }

        if (! is_string($value) || trim($value) === '' || str_starts_with(trim($value), 'data:')) {
            return null;
        }

        $value = trim($value);

        if (preg_match('/\s\d+[wx]\s*(,|$)/', $value)) {
            $value = self::largestFromSrcset($value) ?? $value;
        }

        $absolute = UrlTools::absolute($value, $pageUrl);

        return $absolute !== null ? mb_substr($absolute, 0, 2000) : null;
    }

    public static function largestFromSrcset(string $srcset): ?string
    {
        $best = null;
        $bestSize = -1.0;

        foreach (explode(',', $srcset) as $candidate) {
            $parts = preg_split('/\s+/', trim($candidate)) ?: [];

            if (($parts[0] ?? '') === '') {
                continue;
            }

            $size = isset($parts[1]) ? (float) rtrim($parts[1], 'wx') : 1.0;

            if ($size > $bestSize) {
                $best = $parts[0];
                $bestSize = $size;
            }
        }

        return $best;
    }

    private function stock(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value > 0;
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        $value = strtolower($value);

        return match (true) {
            str_contains($value, 'outofstock'), str_contains($value, 'out of stock'), str_contains($value, 'sold out'),
            str_contains($value, 'stock out'), str_contains($value, 'soldout'), str_contains($value, 'unavailable') => false,
            str_contains($value, 'instock'), str_contains($value, 'in stock'), str_contains($value, 'available'),
            str_contains($value, 'add to cart'), str_contains($value, 'add to bag'), $value === 'true', $value === '1' => true,
            default => null,
        };
    }

    private function cleanText(mixed $value, int $limit = 500): ?string
    {
        if (is_int($value) || is_float($value)) {
            $value = (string) $value;
        }

        if (! is_string($value)) {
            return null;
        }

        $text = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5);
        $text = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        return $text === '' ? null : mb_substr($text, 0, $limit);
    }

    /**
     * Keep scalar source fields for reference, dropping large blobs.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function compactRaw(array $raw): array
    {
        $compact = [];

        foreach ($raw as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $compact[$key] = is_string($value) ? mb_substr($value, 0, 1000) : $value;
            } elseif (is_array($value) && strlen((string) json_encode($value)) <= 2000) {
                $compact[$key] = $value;
            }
        }

        return $compact;
    }
}
