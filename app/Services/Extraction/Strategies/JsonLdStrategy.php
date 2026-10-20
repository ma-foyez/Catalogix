<?php

namespace App\Services\Extraction\Strategies;

use App\Enums\ExtractionStrategy;
use App\Services\Extraction\Dom;
use App\Services\Extraction\PageContext;
use App\Services\Extraction\StrategyResult;
use DOMElement;

/**
 * Ladder step 2: schema.org Product / ItemList in JSON-LD blocks, then microdata.
 */
final class JsonLdStrategy implements Strategy
{
    public function key(): ExtractionStrategy
    {
        return ExtractionStrategy::JsonLd;
    }

    public function extract(PageContext $context): StrategyResult
    {
        $products = $this->fromJsonLd($context->html());

        if (count($products) < 3) {
            $microdata = $this->fromMicrodata($context->html(), $context->url);
            $products = count($microdata) > count($products) ? $microdata : $products;
        }

        return new StrategyResult(products: $products);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fromJsonLd(string $html): array
    {
        preg_match_all('#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $matches);

        $products = [];

        foreach ($matches[1] as $block) {
            $data = json_decode(trim(html_entity_decode($block, ENT_QUOTES | ENT_HTML5)), true);

            if ($data === null) {
                $data = json_decode((string) preg_replace('/[\x00-\x1F]/', ' ', $block), true);
            }

            if (is_array($data)) {
                $this->collect($data, $products);
            }
        }

        return $this->unique($products);
    }

    /**
     * @param  list<array<string, mixed>>  $products
     */
    private function collect(mixed $node, array &$products, int $depth = 0): void
    {
        if (! is_array($node) || $depth > 8) {
            return;
        }

        if (array_is_list($node)) {
            foreach ($node as $item) {
                $this->collect($item, $products, $depth + 1);
            }

            return;
        }

        $types = array_map('strtolower', (array) ($node['@type'] ?? []));

        if (in_array('product', $types, true) || in_array('productgroup', $types, true)) {
            if (($product = $this->mapProduct($node)) !== null) {
                $products[] = $product;
            }

            return;
        }

        foreach (['@graph', 'itemListElement', 'item', 'mainEntity', 'hasPart'] as $key) {
            if (isset($node[$key])) {
                $this->collect($node[$key], $products, $depth + 1);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>|null
     */
    private function mapProduct(array $node): ?array
    {
        $offers = $node['offers'] ?? null;

        if (is_array($offers) && array_is_list($offers)) {
            $offers = $offers[0] ?? null;
        }

        $prices = [];
        $availability = null;
        $currency = null;

        if (is_array($offers)) {
            foreach (['price', 'lowPrice', 'highPrice'] as $key) {
                if (isset($offers[$key])) {
                    $prices[] = (string) $offers[$key];
                }
            }

            if (isset($offers['priceSpecification']['price'])) {
                $prices[] = (string) $offers['priceSpecification']['price'];
            }

            $availability = is_string($offers['availability'] ?? null) ? $offers['availability'] : null;
            $currency = $offers['priceCurrency'] ?? null;
        }

        if (! isset($node['name']) || $prices === []) {
            return null;
        }

        return [
            'name' => $node['name'],
            'prices' => array_slice($prices, 0, 2),
            'image' => $node['image'] ?? null,
            'url' => $node['url'] ?? ($offers['url'] ?? null),
            'sku' => $node['sku'] ?? null,
            'brand' => $node['brand'] ?? null,
            'availability' => $availability,
            'category' => is_string($node['category'] ?? null) ? $node['category'] : null,
            'currency' => is_string($currency) ? $currency : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fromMicrodata(string $html, string $url): array
    {
        $root = Dom::crawler($html, $url)->getNode(0);

        if (! $root instanceof DOMElement) {
            return [];
        }

        $products = [];

        foreach (Dom::all($root, '[itemtype*="schema.org/Product"]') as $item) {
            $name = Dom::first($item, '[itemprop="name"]');
            $price = Dom::first($item, '[itemprop="price"], [itemprop="lowPrice"]');

            if ($name === null || $price === null) {
                continue;
            }

            $image = Dom::first($item, '[itemprop="image"]');
            $link = Dom::first($item, '[itemprop="url"]') ?? Dom::first($item, 'a[href]');

            $products[] = [
                'name' => $name->getAttribute('content') ?: Dom::text($name),
                'price' => $price->getAttribute('content') ?: Dom::text($price),
                'image' => $image ? ($image->getAttribute('content') ?: Dom::imageSource($image) ?: $image->getAttribute('href')) : null,
                'url' => $link ? ($link->getAttribute('href') ?: $link->getAttribute('content')) : null,
                'availability' => Dom::first($item, '[itemprop="availability"]')?->getAttribute('href'),
            ];
        }

        return $products;
    }

    /**
     * @param  list<array<string, mixed>>  $products
     * @return list<array<string, mixed>>
     */
    private function unique(array $products): array
    {
        $seen = [];

        return array_values(array_filter($products, function (array $product) use (&$seen): bool {
            $key = md5(json_encode([$product['name'], $product['url'] ?? null]) ?: '');

            if (isset($seen[$key])) {
                return false;
            }

            return $seen[$key] = true;
        }));
    }
}
