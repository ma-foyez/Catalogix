<?php

namespace App\Services\Extraction;

use DOMElement;
use DOMXPath;

/**
 * Reads name, prices, image, link and stock from one product card element.
 * Recipe selectors are used when given; otherwise sensible heuristics fill each field.
 */
final class CardFieldMapper
{
    private const array BUTTON_WORDS = ['add to cart', 'add to bag', 'buy now', 'quick view', 'view details', 'select options', 'read more', 'out of stock', 'sold out'];

    /**
     * @param  array<string, string|null>  $selectors
     * @return array{raw: array<string, mixed>, elements: array<string, DOMElement|null>}
     */
    public function map(DOMElement $card, string $baseUrl, array $selectors = []): array
    {
        $nameElement = $this->nameElement($card, $selectors['name'] ?? null);
        $priceElements = $this->priceElements($card, $selectors);
        $imageElement = $this->imageElement($card, $selectors['image'] ?? null);
        $linkElement = $this->linkElement($card, $nameElement, $selectors['link'] ?? null);

        $prices = [];

        foreach ($priceElements as $element) {
            $text = Dom::text($element);
            $found = PriceParser::allMarked($text);

            if ($found === [] && ($price = PriceParser::parse($text)) !== null) {
                $found = [$price];
            }

            array_push($prices, ...$found);
        }

        if ($prices === []) {
            $prices = PriceParser::allMarked(Dom::text($card));
        }

        $raw = [
            'name' => $nameElement !== null ? $this->nameText($nameElement) : null,
            'prices' => array_values(array_unique(array_slice($prices, 0, 3))),
            'image' => $imageElement !== null ? $this->imageUrl($imageElement) : null,
            'url' => $linkElement?->getAttribute('href') ?: null,
            'in_stock' => $this->stock($card, $selectors['stock'] ?? null),
        ];

        return [
            'raw' => $raw,
            'elements' => [
                'name' => $nameElement,
                'price' => $priceElements[0] ?? null,
                'image' => $imageElement,
                'link' => $linkElement,
            ],
        ];
    }

    private function nameElement(DOMElement $card, ?string $selector): ?DOMElement
    {
        if ($selector) {
            return Dom::first($card, $selector);
        }

        $xpath = new DOMXPath($card->ownerDocument);

        $queries = [
            './/*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6]',
            './/*[contains(translate(@class, "NAMETILE", "nametile"), "name") or contains(translate(@class, "NAMETILE", "nametile"), "title")]',
            './/*[@itemprop="name"]',
        ];

        foreach ($queries as $query) {
            foreach ($xpath->query($query, $card) ?: [] as $element) {
                if ($element instanceof DOMElement && $this->isNameLike($this->nameText($element))) {
                    return $element;
                }
            }
        }

        $best = null;
        $bestLength = 0;

        foreach ($xpath->query('.//a', $card) ?: [] as $anchor) {
            if (! $anchor instanceof DOMElement) {
                continue;
            }

            $text = $this->nameText($anchor);

            if ($this->isNameLike($text) && mb_strlen($text) > $bestLength) {
                $best = $anchor;
                $bestLength = mb_strlen($text);
            }
        }

        if ($best !== null) {
            return $best;
        }

        foreach ($xpath->query('.//img[@alt]', $card) ?: [] as $img) {
            if ($img instanceof DOMElement && $this->isNameLike(trim($img->getAttribute('alt')))) {
                return $img;
            }
        }

        return null;
    }

    private function nameText(DOMElement $element): string
    {
        if (strtolower($element->tagName) === 'img') {
            return trim($element->getAttribute('alt'));
        }

        $text = Dom::text($element);

        if ($text === '' && $element->hasAttribute('title')) {
            $text = trim($element->getAttribute('title'));
        }

        return $text;
    }

    private function isNameLike(string $text): bool
    {
        $length = mb_strlen($text);

        if ($length < 2 || $length > 300) {
            return false;
        }

        if (PriceParser::looksLikePrice($text) && $length < 25) {
            return false;
        }

        return ! in_array(mb_strtolower($text), self::BUTTON_WORDS, true) && preg_match('/\p{L}/u', $text);
    }

    /**
     * @param  array<string, string|null>  $selectors
     * @return list<DOMElement>
     */
    private function priceElements(DOMElement $card, array $selectors): array
    {
        if (! empty($selectors['price'])) {
            return array_values(array_filter([
                Dom::first($card, $selectors['price']),
                ! empty($selectors['sale_price']) ? Dom::first($card, $selectors['sale_price']) : null,
            ]));
        }

        $xpath = new DOMXPath($card->ownerDocument);
        $elements = [];

        // Outermost price/amount elements: a wrapper such as <span class="price"><del>৳230</del><ins>৳149</ins></span>
        // holds both the regular and the sale price, while its inner parts hold only one each.
        $query = './/*[contains(translate(@class, "PRICEAMOUNT", "priceamount"), "price") or contains(translate(@class, "PRICEAMOUNT", "priceamount"), "amount") or @itemprop="price"]';

        foreach ($xpath->query($query, $card) ?: [] as $element) {
            if ($element instanceof DOMElement && ! $this->insidePriceElement($element, $card) && preg_match('/[0-9০-৯]/u', Dom::text($element))) {
                $elements[] = $element;
            }
        }

        return $elements;
    }

    private function insidePriceElement(DOMElement $element, DOMElement $card): bool
    {
        $node = $element->parentNode;

        while ($node instanceof DOMElement && $node !== $card) {
            $class = strtolower($node->getAttribute('class'));

            if (str_contains($class, 'price') || str_contains($class, 'amount') || $node->getAttribute('itemprop') === 'price') {
                return true;
            }

            $node = $node->parentNode;
        }

        return false;
    }

    private function imageElement(DOMElement $card, ?string $selector): ?DOMElement
    {
        if ($selector) {
            $element = Dom::first($card, $selector);

            if ($element !== null && strtolower($element->tagName) !== 'img') {
                return Dom::first($element, 'img') ?? $element;
            }

            return $element;
        }

        foreach (Dom::all($card, 'img') as $img) {
            $width = (int) $img->getAttribute('width');

            if (Dom::imageSource($img) !== null && ($width === 0 || $width >= 40)) {
                return $img;
            }
        }

        return null;
    }

    private function imageUrl(DOMElement $element): ?string
    {
        if (strtolower($element->tagName) === 'img' || strtolower($element->tagName) === 'source') {
            return Dom::imageSource($element);
        }

        if (preg_match('/url\([\'"]?([^\'")]+)[\'"]?\)/', $element->getAttribute('style'), $match)) {
            return $match[1];
        }

        return $element->getAttribute('data-src') ?: $element->getAttribute('data-bg') ?: null;
    }

    private function linkElement(DOMElement $card, ?DOMElement $nameElement, ?string $selector): ?DOMElement
    {
        if ($selector) {
            $element = Dom::first($card, $selector);

            return $element !== null ? $this->closestAnchor($element, $card) : null;
        }

        if ($nameElement !== null && ($anchor = $this->closestAnchor($nameElement, $card)) !== null) {
            return $anchor;
        }

        foreach (Dom::all($card, 'a[href]') as $anchor) {
            $href = trim($anchor->getAttribute('href'));

            if ($href !== '' && ! str_starts_with($href, '#') && ! str_starts_with(strtolower($href), 'javascript:')) {
                return $anchor;
            }
        }

        return strtolower($card->tagName) === 'a' ? $card : null;
    }

    private function closestAnchor(DOMElement $element, DOMElement $card): ?DOMElement
    {
        $node = $element;

        while ($node instanceof DOMElement) {
            if (strtolower($node->tagName) === 'a' && $node->hasAttribute('href')) {
                return $node;
            }

            if ($node === $card) {
                break;
            }

            $node = $node->parentNode;
        }

        return Dom::first($element, 'a[href]');
    }

    private function stock(DOMElement $card, ?string $selector): ?bool
    {
        $text = mb_strtolower($selector ? Dom::text(Dom::first($card, $selector) ?? $card) : Dom::text($card));

        foreach (['out of stock', 'stock out', 'sold out', 'stockout', 'unavailable', 'স্টক নেই'] as $phrase) {
            if (str_contains($text, $phrase)) {
                return false;
            }
        }

        foreach (['add to cart', 'add to bag', 'buy now', 'in stock', 'add to shopping'] as $phrase) {
            if (str_contains($text, $phrase)) {
                return true;
            }
        }

        return null;
    }
}
