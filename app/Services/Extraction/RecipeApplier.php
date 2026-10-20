<?php

namespace App\Services\Extraction;

use App\Services\Crawling\UrlTools;
use DOMElement;

/**
 * Applies a saved recipe (CSS selectors for card, name, price, sale price, image, link, next) to a page.
 */
final class RecipeApplier
{
    public function __construct(private readonly CardFieldMapper $mapper) {}

    /**
     * @param  array<string, string|null>  $selectors
     * @return array{products: list<array<string, mixed>>, next: string|null, cards: int}
     */
    public function apply(string $html, string $baseUrl, array $selectors): array
    {
        $root = Dom::crawler($html, $baseUrl)->getNode(0);

        if (! $root instanceof DOMElement || empty($selectors['card'])) {
            return ['products' => [], 'next' => null, 'cards' => 0];
        }

        $cards = Dom::all($root, $selectors['card']);
        $products = [];

        foreach ($cards as $card) {
            $mapped = $this->mapper->map($card, $baseUrl, $selectors)['raw'];

            if (! empty($mapped['name'])) {
                $products[] = $mapped;
            }
        }

        return [
            'products' => $products,
            'next' => $this->next($root, $baseUrl, $selectors['next'] ?? null),
            'cards' => count($cards),
        ];
    }

    private function next(DOMElement $root, string $baseUrl, ?string $selector): ?string
    {
        $element = Dom::first($root, $selector);

        if ($element === null) {
            return null;
        }

        $anchor = strtolower($element->tagName) === 'a' ? $element : (Dom::first($element, 'a[href]') ?? $element);
        $href = $anchor->getAttribute('href') ?: $anchor->getAttribute('data-href');

        return UrlTools::absolute($href, $baseUrl);
    }
}
