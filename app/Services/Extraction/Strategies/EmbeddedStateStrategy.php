<?php

namespace App\Services\Extraction\Strategies;

use App\Enums\ExtractionStrategy;
use App\Services\Extraction\JsonProductFinder;
use App\Services\Extraction\PageContext;
use App\Services\Extraction\StrategyResult;

/**
 * Ladder step 4: state JSON frameworks embed in the page (__NEXT_DATA__, window.__NUXT__, window.__INITIAL_STATE__).
 */
final class EmbeddedStateStrategy implements Strategy
{
    public function __construct(private readonly JsonProductFinder $finder) {}

    public function key(): ExtractionStrategy
    {
        return ExtractionStrategy::EmbeddedState;
    }

    public function extract(PageContext $context): StrategyResult
    {
        $best = [];

        foreach ($this->stateBlobs($context->html()) as $blob) {
            $products = $this->finder->find($blob);

            if (count($products) > count($best)) {
                $best = $products;
            }
        }

        return new StrategyResult(products: $best);
    }

    /**
     * @return list<mixed>
     */
    public function stateBlobs(string $html): array
    {
        $blobs = [];

        if (preg_match_all('#<script[^>]*type=["\']application/json["\'][^>]*>(.*?)</script>#is', $html, $matches)) {
            foreach ($matches[1] as $json) {
                $decoded = json_decode(trim($json), true);

                if (is_array($decoded)) {
                    $blobs[] = $decoded;
                }
            }
        }

        foreach (['window.__INITIAL_STATE__', 'window.__NUXT__', 'window.__PRELOADED_STATE__', 'window.__APOLLO_STATE__', 'window.__DATA__'] as $variable) {
            $offset = 0;

            while (($position = strpos($html, $variable, $offset)) !== false) {
                $offset = $position + strlen($variable);

                if (! preg_match('/\G\s*=\s*/', $html, $match, 0, $offset)) {
                    continue;
                }

                $json = $this->balancedJson($html, $offset + strlen($match[0]));
                $decoded = $json !== null ? json_decode($json, true) : null;

                if (is_array($decoded)) {
                    $blobs[] = $decoded;
                }
            }
        }

        return $blobs;
    }

    /**
     * Read one JSON object or array starting at $start, honouring strings and escapes.
     */
    private function balancedJson(string $html, int $start): ?string
    {
        $open = $html[$start] ?? '';

        if ($open === 'J' && preg_match('/\GJSON\.parse\(\s*(["\'])/', $html, $match, 0, $start)) {
            return $this->jsonParseArgument($html, $start + strlen($match[0]), $match[1]);
        }

        if ($open !== '{' && $open !== '[') {
            return null;
        }

        $depth = 0;
        $inString = false;
        $length = strlen($html);

        for ($i = $start; $i < $length; $i++) {
            $char = $html[$i];

            if ($inString) {
                if ($char === '\\') {
                    $i++;
                } elseif ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;
            } elseif ($char === '{' || $char === '[') {
                $depth++;
            } elseif ($char === '}' || $char === ']') {
                $depth--;

                if ($depth === 0) {
                    return substr($html, $start, $i - $start + 1);
                }
            }
        }

        return null;
    }

    /**
     * window.__STATE__ = JSON.parse("{\"products\": ...}")
     */
    private function jsonParseArgument(string $html, int $start, string $quote): ?string
    {
        $end = $start;
        $length = strlen($html);

        while ($end < $length && $html[$end] !== $quote) {
            $end += $html[$end] === '\\' ? 2 : 1;
        }

        $literal = substr($html, $start, $end - $start);

        // A double-quoted JS string uses JSON escapes; a single-quoted one needs its quotes swapped first.
        if ($quote === "'") {
            $literal = str_replace(["\\'", '"'], ["'", '\\"'], $literal);
        }

        $decoded = json_decode('"'.$literal.'"');

        return is_string($decoded) ? $decoded : null;
    }
}
