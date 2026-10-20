<?php

namespace App\Services\Extraction\Strategies;

use App\Enums\ExtractionStrategy;
use App\Services\Extraction\CardDetector;
use App\Services\Extraction\PageContext;
use App\Services\Extraction\StrategyResult;

/**
 * Ladder step 6: find the largest group of repeated elements with an image, a link and a price,
 * on the static HTML first and then on the rendered HTML if the Python helper already ran.
 * The detected selectors are returned as a recipe so the next run starts at step 1.
 */
final class CardDetectionStrategy implements Strategy
{
    public function __construct(private readonly CardDetector $detector) {}

    public function key(): ExtractionStrategy
    {
        return ExtractionStrategy::CardDetection;
    }

    public function extract(PageContext $context): StrategyResult
    {
        $static = $this->detector->detect($context->html, $context->url);

        if ($static !== null && $static['count'] >= (int) config('catalogix.min_products', 3)) {
            return new StrategyResult(products: $static['products'], recipe: $static['recipe']);
        }

        if ($context->hasRendered() || $context->site->needs_js) {
            $rendered = $context->rendered();
            $detected = $rendered !== null ? $this->detector->detect($rendered->html, $context->url) : null;

            if ($detected !== null) {
                return new StrategyResult(products: $detected['products'], recipe: $detected['recipe'], usedRender: true);
            }
        }

        return $static !== null
            ? new StrategyResult(products: $static['products'], recipe: $static['recipe'])
            : StrategyResult::empty();
    }
}
