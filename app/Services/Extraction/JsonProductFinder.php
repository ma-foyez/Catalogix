<?php

namespace App\Services\Extraction;

/**
 * Walks any decoded JSON (embedded state, captured XHR, platform APIs) and returns the largest
 * array of objects that look like products: a name-like key and a price-like key, ideally an image.
 */
final class JsonProductFinder
{
    private const array NAME_KEYS = ['name', 'title', 'product_name', 'productName', 'displayName', 'nameEn', 'name_en', 'label'];

    private const array PRICE_KEYS = [
        'price', 'salePrice', 'sale_price', 'regularPrice', 'regular_price', 'currentPrice', 'current_price',
        'finalPrice', 'final_price', 'discountedPrice', 'discounted_price', 'sellingPrice', 'selling_price',
        'offerPrice', 'offer_price', 'specialPrice', 'special_price', 'unitPrice', 'unit_price', 'mrp', 'MRP',
        'Price', 'prices', 'pricing', 'priceRange', 'price_range', 'amount',
    ];

    private const array REGULAR_KEYS = ['regularPrice', 'regular_price', 'mrp', 'MRP', 'originalPrice', 'original_price', 'compareAtPrice', 'compare_at_price', 'oldPrice', 'old_price', 'listPrice'];

    private const array SALE_KEYS = ['salePrice', 'sale_price', 'discountedPrice', 'discounted_price', 'specialPrice', 'special_price', 'offerPrice', 'offer_price', 'finalPrice', 'final_price', 'sellingPrice', 'selling_price'];

    private const array IMAGE_KEYS = [
        'image', 'imageUrl', 'image_url', 'img', 'thumbnail', 'thumb', 'picture', 'pictureUrl', 'PictureUrls',
        'photo', 'images', 'media', 'featured_image', 'featuredImage', 'primaryImage', 'mainImage', 'cover',
    ];

    private const array URL_KEYS = ['url', 'link', 'permalink', 'href', 'productUrl', 'product_url', 'canonicalUrl', 'canonical_url', 'webUrl'];

    private const array STOCK_KEYS = ['in_stock', 'inStock', 'is_in_stock', 'isInStock', 'available', 'availability', 'stock_status', 'stockStatus'];

    private const int MAX_DEPTH = 14;

    /**
     * @return list<array<string, mixed>>
     */
    public function find(mixed $data): array
    {
        $best = [];

        $this->walk($data, 0, $best);

        return $best;
    }

    /**
     * @param  list<array<string, mixed>>  $best
     */
    private function walk(mixed $node, int $depth, array &$best): void
    {
        if (! is_array($node) || $depth > self::MAX_DEPTH) {
            return;
        }

        if (array_is_list($node) && count($node) >= 2) {
            $mapped = [];

            foreach ($node as $item) {
                if (is_array($item) && ! array_is_list($item) && ($product = $this->map($item)) !== null) {
                    $mapped[] = $product;
                }
            }

            if (count($mapped) >= 2 && count($mapped) >= count($node) * 0.5 && $this->scoreOf($mapped) > $this->scoreOf($best)) {
                $best = $mapped;
            }
        }

        foreach ($node as $child) {
            if (is_array($child)) {
                $this->walk($child, $depth + 1, $best);
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $products
     */
    private function scoreOf(array $products): float
    {
        $withImage = count(array_filter($products, fn ($product) => ! empty($product['image'])));

        return count($products) + $withImage * 0.1;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    public function map(array $item): ?array
    {
        $name = $this->firstString($item, self::NAME_KEYS);
        $price = $this->priceFrom($item);

        if ($name === null || $price === null) {
            return null;
        }

        $regular = $this->firstPrice($item, self::REGULAR_KEYS);
        $sale = $this->firstPrice($item, self::SALE_KEYS);

        return array_filter([
            'name' => $name,
            'price' => $price,
            'regular_price' => $regular,
            'sale_price' => $sale !== null && $sale !== $regular ? $sale : null,
            'image' => $this->imageFrom($item),
            'url' => $this->firstString($item, self::URL_KEYS),
            'sku' => $this->firstString($item, ['sku', 'SKU', 'code', 'productCode']),
            'brand' => $this->firstString($item, ['brand', 'brandName', 'brand_name', 'vendor', 'manufacturer']),
            'in_stock' => $this->stockFrom($item),
            'category' => $this->firstString($item, ['category', 'categoryName', 'category_name', 'product_type']),
            'description' => $this->firstString($item, ['short_description', 'shortDescription', 'description', 'summary']),
            'currency' => $this->firstString($item, ['currency', 'currencyCode', 'currency_code', 'priceCurrency']),
        ], fn ($value) => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<string>  $keys
     */
    private function firstString(array $item, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $item[$key] ?? null;

            if (is_array($value) && ! array_is_list($value)) {
                $value = $value['name'] ?? $value['en'] ?? $value['title'] ?? $value['value'] ?? null;
            }

            if ((is_string($value) && trim($value) !== '') || is_int($value)) {
                return trim((string) $value);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function priceFrom(array $item): ?float
    {
        foreach (self::PRICE_KEYS as $key) {
            if (array_key_exists($key, $item) && ($price = $this->priceValue($item[$key])) !== null) {
                return $price;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  list<string>  $keys
     */
    private function firstPrice(array $item, array $keys): ?float
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $item) && ($price = $this->priceValue($item[$key])) !== null) {
                return $price;
            }
        }

        return null;
    }

    /**
     * A price can be a number, a string, or a nested object such as {amount: 120} or {price: "12000", currency_minor_unit: 2}.
     */
    private function priceValue(mixed $value, int $depth = 0): ?float
    {
        if (is_int($value) || is_float($value) || is_string($value)) {
            return PriceParser::parse(is_string($value) ? $value : (float) $value);
        }

        if (! is_array($value) || $depth > 2) {
            return null;
        }

        if (array_is_list($value)) {
            return isset($value[0]) ? $this->priceValue($value[0], $depth + 1) : null;
        }

        if (isset($value['currency_minor_unit'], $value['price']) && is_numeric($value['price'])) {
            return PriceParser::parse((float) $value['price'] / (10 ** (int) $value['currency_minor_unit']));
        }

        foreach (['amount', 'value', 'price', 'current', 'final', 'sale', 'min', 'minimum', 'regular_price', 'formatted'] as $key) {
            if (isset($value[$key]) && ($price = $this->priceValue($value[$key], $depth + 1)) !== null) {
                return $price;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return string|array<mixed>|null
     */
    private function imageFrom(array $item): string|array|null
    {
        foreach (self::IMAGE_KEYS as $key) {
            $value = $item[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return $value;
            }

            if (is_array($value) && $value !== []) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function stockFrom(array $item): bool|string|null
    {
        foreach (self::STOCK_KEYS as $key) {
            if (array_key_exists($key, $item) && (is_bool($item[$key]) || is_string($item[$key]))) {
                return $item[$key];
            }
        }

        return null;
    }
}
