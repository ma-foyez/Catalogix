<?php

namespace App\Services\Extraction\Strategies;

use App\Enums\ExtractionStrategy;
use App\Services\Extraction\CardDetector;
use App\Services\Extraction\JsonProductFinder;
use App\Services\Extraction\PageContext;
use App\Services\Extraction\StrategyResult;

/**
 * Ladder step 5: the Python helper loads the page in a real browser and returns the product JSON
 * the site fetched for itself (XHR/fetch responses).
 *
 * Launching a browser takes seconds, so a page whose static HTML already shows product cards
 * is left to step 6 instead of being rendered.
 */
final class NetworkJsonStrategy implements Strategy
{
    public function __construct(
        private readonly JsonProductFinder $finder,
        private readonly EmbeddedStateStrategy $embeddedState,
        private readonly CardDetector $cards,
    ) {}

    public function key(): ExtractionStrategy
    {
        return ExtractionStrategy::NetworkJson;
    }

    public function extract(PageContext $context): StrategyResult
    {
        if (! $context->site->needs_js && $this->staticPageHasCards($context)) {
            return StrategyResult::empty();
        }

        $rendered = $context->rendered();

        if ($rendered === null) {
            return StrategyResult::empty();
        }

        $best = [];

        foreach ($rendered->jsonResponses as $response) {
            $products = $this->finder->find($response['body']);

            if (count($products) > count($best)) {
                $best = $products;
            }
        }

        // State that only appears after hydration counts too.
        foreach ($this->embeddedState->stateBlobs($rendered->html) as $blob) {
            $products = $this->finder->find($blob);

            if (count($products) > count($best)) {
                $best = $products;
            }
        }

        return new StrategyResult(products: $best, usedRender: true);
    }

    private function staticPageHasCards(PageContext $context): bool
    {
        $detected = $this->cards->detect($context->html, $context->url);

        return $detected !== null && $detected['count'] >= (int) config('catalogix.min_products', 3);
    }
}
