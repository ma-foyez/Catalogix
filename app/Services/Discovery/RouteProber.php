<?php

namespace App\Services\Discovery;

use App\Services\Crawling\FetchResult;
use App\Services\Extraction\CardDetector;
use App\Services\Extraction\Dom;
use App\Services\Extraction\JsonProductFinder;
use App\Services\Extraction\PriceParser;
use App\Services\Extraction\ProductNormalizer;
use App\Services\Extraction\Strategies\EmbeddedStateStrategy;
use App\Services\Extraction\Strategies\JsonLdStrategy;
use DOMElement;

/**
 * Counts product-like content on a candidate page (JSON-LD, embedded state, captured JSON, repeated cards)
 * and turns it into a confidence score, an estimated product count and three sample images.
 */
final class RouteProber
{
    public function __construct(
        private readonly JsonLdStrategy $jsonLd,
        private readonly EmbeddedStateStrategy $embeddedState,
        private readonly JsonProductFinder $finder,
        private readonly CardDetector $cards,
        private readonly ProductNormalizer $normalizer,
    ) {}

    public function probe(FetchResult $fetch, bool $matchesListingPattern = false): ProbeResult
    {
        if ($fetch->isBlocked()) {
            return ProbeResult::blocked((string) $fetch->blockedReason);
        }

        if (! $fetch->ok() || ! $fetch->isHtml()) {
            return new ProbeResult;
        }

        return $this->evaluate($fetch->body, $fetch->url, [], $matchesListingPattern);
    }

    /**
     * @param  list<array{url: string, body: mixed}>  $jsonResponses  captured by the Python helper
     */
    public function evaluate(string $html, string $url, array $jsonResponses = [], bool $matchesListingPattern = false): ProbeResult
    {
        $lists = [$this->jsonLd->fromJsonLd($html)];

        foreach ($this->embeddedState->stateBlobs($html) as $blob) {
            $lists[] = $this->finder->find($blob);
        }

        foreach ($jsonResponses as $response) {
            $lists[] = $this->finder->find($response['body']);
        }

        $detected = $this->cards->detect($html, $url);

        if ($detected !== null) {
            $lists[] = $detected['products'];
        }

        $best = [];

        foreach ($lists as $list) {
            $products = $this->normalized($list, $url);

            if (count($products) > count($best)) {
                $best = $products;
            }
        }

        $count = count($best);
        $images = array_values(array_slice(array_filter(array_column($best, 'image_url')), 0, 3));
        $estimate = max($count, $this->advertisedCount($html));

        return new ProbeResult(
            score: $this->score($count, $matchesListingPattern),
            productCount: $count,
            estProducts: $estimate,
            sampleImages: $images,
            heading: $this->heading($html, $url),
        );
    }

    public function score(int $count, bool $matchesListingPattern): int
    {
        return match (true) {
            $count >= 3 => min(100, 60 + min($count, 40)),
            $count >= 1 => 30 + $count * 5,
            $matchesListingPattern => 10,
            default => 0,
        };
    }

    /**
     * @param  list<array<string, mixed>>  $raw
     * @return list<array<string, mixed>>
     */
    private function normalized(array $raw, string $url): array
    {
        $products = [];

        foreach ($raw as $item) {
            $product = $this->normalizer->normalize($item, $url);

            if ($product !== null && $product['price'] !== null) {
                $products[$product['hash']] = $product;
            }
        }

        return array_values($products);
    }

    /**
     * "Showing 1-24 of 132 results", "120 products", "১২০টি পণ্য" style counts.
     */
    private function advertisedCount(string $html): int
    {
        $text = strip_tags(preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $html) ?? '');

        if (preg_match_all('/(?:of\s+)?([0-9০-৯][0-9০-৯,]*)\s+(?:products|items|results|পণ্য)/iu', $text, $matches)) {
            $numbers = array_map(fn (string $n) => (int) str_replace(',', '', PriceParser::toAsciiDigits($n)), $matches[1]);

            return min(100000, max($numbers));
        }

        return 0;
    }

    private function heading(string $html, string $url): ?string
    {
        $root = Dom::crawler($html, $url)->getNode(0);

        if (! $root instanceof DOMElement) {
            return null;
        }

        $h1 = Dom::first($root, 'h1');
        $text = $h1 !== null ? Dom::text($h1) : '';

        return $text !== '' && mb_strlen($text) <= 120 ? $text : null;
    }
}
