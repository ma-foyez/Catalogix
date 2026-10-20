<?php

namespace App\Services\Extraction;

use DOMElement;
use DOMXPath;

/**
 * Automatic card detection: the largest group of repeated sibling elements that each contain an
 * image and a price (a link counts in their favour; some shops, such as Chaldal, open products
 * in a modal and have no link). Returns the mapped products and a recipe that reproduces them.
 */
final class CardDetector
{
    private const int MIN_CARDS = 3;

    public function __construct(private readonly CardFieldMapper $mapper) {}

    /**
     * @return array{products: list<array<string, mixed>>, recipe: array<string, string|null>, count: int}|null
     */
    public function detect(string $html, string $baseUrl): ?array
    {
        if (trim($html) === '') {
            return null;
        }

        $root = Dom::crawler($html, $baseUrl)->getNode(0);

        if (! $root instanceof DOMElement || $root->ownerDocument === null) {
            return null;
        }

        $xpath = new DOMXPath($root->ownerDocument);
        $best = $this->bestGroup($xpath);

        if ($best === null) {
            return null;
        }

        $cards = $best['cards'];
        $cardSelector = $this->selectorFor($root, $best['parent'], $best['signature'], count($cards));

        // A bare <div> wrapper around each product: use the classed card inside it for a sturdier recipe.
        if (! str_contains($best['signature'], '.') && ($inner = $this->tighten($xpath, $cards)) !== null) {
            $cards = $inner['cards'];
            $cardSelector = count(Dom::all($root, $inner['signature'])) === count($cards)
                ? $inner['signature']
                : $cardSelector.' '.$inner['signature'];
        }

        $products = [];
        $firstElements = null;

        foreach ($cards as $card) {
            $mapped = $this->mapper->map($card, $baseUrl);

            if (! empty($mapped['raw']['name']) && $mapped['raw']['prices'] !== []) {
                $products[] = $mapped['raw'];
                $firstElements ??= ['card' => $card, ...$mapped['elements']];
            }
        }

        if (count($products) < self::MIN_CARDS || $firstElements === null) {
            return null;
        }

        return [
            'products' => $products,
            'recipe' => $this->recipe($cardSelector, $firstElements),
            'count' => count($products),
        ];
    }

    /**
     * @return array{parent: DOMElement, signature: string, cards: list<DOMElement>}|null
     */
    private function bestGroup(DOMXPath $xpath): ?array
    {
        $best = null;
        $bestScore = [0, 0.0, 0.0];

        foreach ($xpath->query('//body//*[count(*) >= '.self::MIN_CARDS.']') ?: [] as $parent) {
            if (! $parent instanceof DOMElement) {
                continue;
            }

            foreach ($this->groups($parent) as $signature => $children) {
                if (count($children) < self::MIN_CARDS) {
                    continue;
                }

                $cards = array_values(array_filter($children, fn (DOMElement $child) => $this->isCard($xpath, $child)));

                if (count($cards) < self::MIN_CARDS || count($cards) < count($children) * 0.5) {
                    continue;
                }

                $linked = count(array_filter($cards, fn (DOMElement $card) => $this->hasLink($xpath, $card)));
                $averageText = array_sum(array_map(fn (DOMElement $card) => mb_strlen(Dom::text($card)), $cards)) / count($cards);

                // More cards wins; then groups whose cards mostly have links; then tighter (shorter) cards.
                $score = [count($cards), $linked / count($cards) >= 0.5 ? 1.0 : 0.0, -$averageText];

                if ($score > $bestScore) {
                    $best = ['parent' => $parent, 'signature' => $signature, 'cards' => $cards];
                    $bestScore = $score;
                }
            }
        }

        return $best;
    }

    /**
     * Group element children by tag + stable classes.
     *
     * @return array<string, list<DOMElement>>
     */
    private function groups(DOMElement $parent): array
    {
        $groups = [];

        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && ! in_array(strtolower($child->tagName), ['script', 'style', 'br', 'hr', 'input', 'option'], true)) {
                $groups[CssPath::segment($child)][] = $child;
            }
        }

        return $groups;
    }

    private function isCard(DOMXPath $xpath, DOMElement $element): bool
    {
        $hasImage = strtolower($element->tagName) === 'img' || ($xpath->query('.//img', $element)?->length ?? 0) > 0;

        if (! $hasImage) {
            return false;
        }

        $text = Dom::text($element);

        if (mb_strlen($text) > 1500) {
            return false;
        }

        if (PriceParser::looksLikePrice($text)) {
            return true;
        }

        return ($xpath->query('.//*[contains(translate(@class, "PRICE", "price"), "price")]', $element)?->length ?? 0) > 0
            && preg_match('/[0-9০-৯]/u', $text) === 1;
    }

    private function hasLink(DOMXPath $xpath, DOMElement $element): bool
    {
        return strtolower($element->tagName) === 'a'
            || ($xpath->query('.//a[@href]', $element)?->length ?? 0) > 0
            || ($xpath->query('ancestor::a[@href]', $element)?->length ?? 0) > 0;
    }

    /**
     * The first classed descendant that appears exactly once in every card and still holds the image and price.
     *
     * @param  list<DOMElement>  $cards
     * @return array{cards: list<DOMElement>, signature: string}|null
     */
    private function tighten(DOMXPath $xpath, array $cards): ?array
    {
        foreach ($xpath->query('.//*[@class]', $cards[0]) ?: [] as $candidate) {
            if (! $candidate instanceof DOMElement || CssPath::stableClasses($candidate) === []) {
                continue;
            }

            $signature = CssPath::segment($candidate);
            $inner = [];

            foreach ($cards as $card) {
                $matches = Dom::all($card, $signature);

                if (count($matches) !== 1 || ! $this->isCard($xpath, $matches[0])) {
                    continue 2;
                }

                $inner[] = $matches[0];
            }

            return ['cards' => $inner, 'signature' => $signature];
        }

        return null;
    }

    private function selectorFor(DOMElement $root, DOMElement $parent, string $signature, int $expected): string
    {
        $parentPath = CssPath::absolute($parent);

        // The bare card signature is shorter and survives wrapper changes; use it when it matches only the cards.
        return $parentPath === '' || count(Dom::all($root, $signature)) === $expected
            ? $signature
            : $parentPath.' > '.$signature;
    }

    /**
     * @param  array<string, DOMElement|null>  $elements
     * @return array<string, string|null>
     */
    private function recipe(string $cardSelector, array $elements): array
    {
        $card = $elements['card'];
        $relative = fn (?DOMElement $element) => $element !== null && $card instanceof DOMElement ? CssPath::relative($element, $card) : null;

        return [
            'card' => $cardSelector,
            'name' => $relative($elements['name'] ?? null),
            'price' => $relative($elements['price'] ?? null),
            'sale_price' => null,
            'image' => $relative($elements['image'] ?? null),
            'link' => $relative($elements['link'] ?? null),
            'next' => null,
        ];
    }
}
