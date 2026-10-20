<?php

namespace App\Services\Extraction;

use App\Enums\ExtractionStrategy;
use App\Services\Extraction\Strategies\CardDetectionStrategy;
use App\Services\Extraction\Strategies\EmbeddedStateStrategy;
use App\Services\Extraction\Strategies\JsonLdStrategy;
use App\Services\Extraction\Strategies\NetworkJsonStrategy;
use App\Services\Extraction\Strategies\PlatformApiStrategy;
use App\Services\Extraction\Strategies\RecipeStrategy;
use App\Services\Extraction\Strategies\Strategy;
use Throwable;

/**
 * Runs the no-AI strategies in order and stops at the first one that finds at least
 * three products with a name and a price. The site's last winning strategy is tried first.
 */
final class ExtractionLadder
{
    /** @var list<Strategy> */
    private array $strategies;

    public function __construct(
        RecipeStrategy $recipe,
        JsonLdStrategy $jsonLd,
        PlatformApiStrategy $platformApi,
        EmbeddedStateStrategy $embeddedState,
        NetworkJsonStrategy $networkJson,
        CardDetectionStrategy $cardDetection,
        private readonly ProductNormalizer $normalizer,
    ) {
        $this->strategies = [$recipe, $jsonLd, $platformApi, $embeddedState, $networkJson, $cardDetection];
    }

    /**
     * @param  ExtractionStrategy|null  $only  later pages reuse the strategy that won page 1
     */
    public function run(PageContext $context, ?ExtractionStrategy $only = null): ?LadderOutcome
    {
        $minimum = $only !== null ? 1 : max(1, (int) config('catalogix.min_products', 3));

        foreach ($this->ordered($context, $only) as $strategy) {
            try {
                $result = $strategy->extract($context);
            } catch (Throwable $e) {
                report($e);

                continue;
            }

            $products = $this->normalize($result, $context);

            if ($strategy->key() === ExtractionStrategy::Recipe && $only === null && $products === [] && ! empty($context->site->selectors['card'])) {
                // The layout changed: flag the recipe so the admin re-picks it.
                $context->site->forceFill(['recipe_valid' => false])->save();
            }

            if (count($products) >= $minimum) {
                return new LadderOutcome($strategy->key(), $products, $result);
            }
        }

        return null;
    }

    /**
     * @return list<Strategy>
     */
    private function ordered(PageContext $context, ?ExtractionStrategy $only): array
    {
        if ($only !== null) {
            return array_values(array_filter($this->strategies, fn (Strategy $strategy) => $strategy->key() === $only));
        }

        $saved = $context->site->strategy;

        if ($saved === null) {
            return $this->strategies;
        }

        $strategies = $this->strategies;
        usort($strategies, fn (Strategy $a, Strategy $b) => ($b->key() === $saved) <=> ($a->key() === $saved));

        return $strategies;
    }

    /**
     * Normalized products that have both a name and a price, deduplicated by hash.
     *
     * @return list<array<string, mixed>>
     */
    private function normalize(StrategyResult $result, PageContext $context): array
    {
        $products = [];

        foreach ($result->products as $raw) {
            $product = $this->normalizer->normalize($raw, $context->url, $context->category);

            if ($product !== null && $product['price'] !== null) {
                $products[$product['hash']] ??= $product;
            }
        }

        return array_values($products);
    }
}
