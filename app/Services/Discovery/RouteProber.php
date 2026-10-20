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
        $images = array_slice(array_values(array_filter(array_column($best, 'image_url'))), 0, 3);
        // "120 products" text only means something on a page that actually lists products.
        $estimate = $count > 0 ? max($count, $this->advertisedCount($html)) : 0;

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
        // A space at every tag boundary: "৳ 59</b><i>0 items" must not read as "590 items".
        $text = strip_tags(str_replace('<', ' <', $this->withoutScripts($html)));

        if (preg_match_all('/(?:of\s+)?([0-9০-৯][0-9০-৯,]*)\s+(?:products|items|results|পণ্য)/iu', $text, $matches)) {
            $numbers = array_map(fn (string $n) => (int) str_replace(',', '', PriceParser::toAsciiDigits($n)), $matches[1]);

            return min(100000, max($numbers));
        }

        return 0;
    }

    /**
     * Drops <script> and <style> blocks with plain string search: a regex hits PCRE limits on
     * multi-megabyte pages, and the JSON left behind is full of "590 items"-style numbers.
     */
    private function withoutScripts(string $html): string
    {
        $out = '';
        $offset = 0;
        $lower = strtolower($html);

        while (preg_match('/<(script|style)\b/', $lower, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
            $start = $match[0][1];
            $end = strpos($lower, '</'.$match[1][0], $start);
            $out .= substr($html, $offset, $start - $offset).' ';

            if ($end === false) {
                return $out;
            }

            $close = strpos($lower, '>', $end);
            $offset = $close === false ? strlen($html) : $close + 1;
        }

        return $out.substr($html, $offset);
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
