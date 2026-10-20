<?php

namespace App\Services\Extraction\Strategies;

use App\Enums\ExtractionStrategy;
use App\Services\Extraction\PageContext;
use App\Services\Extraction\RecipeApplier;
use App\Services\Extraction\StrategyResult;

/**
 * Ladder step 1: CSS selectors already stored for this site.
 */
final class RecipeStrategy implements Strategy
{
    public function __construct(private readonly RecipeApplier $applier) {}

    public function key(): ExtractionStrategy
    {
        return ExtractionStrategy::Recipe;
    }

    public function extract(PageContext $context): StrategyResult
    {
        if (empty($context->site->selectors['card'])) {
            return StrategyResult::empty();
        }

        $result = $this->applier->apply($context->html(), $context->url, $context->site->selectors);

        return new StrategyResult(
            products: $result['products'],
            nextUrl: $result['next'],
            usedRender: $context->site->needs_js && $context->hasRendered(),
        );
    }
}
