<?php

namespace App\Services\Extraction;

use App\Enums\ExtractionStrategy;

final readonly class LadderOutcome
{
    /**
     * @param  list<array<string, mixed>>  $products  normalized products
     */
    public function __construct(
        public ExtractionStrategy $strategy,
        public array $products,
        public StrategyResult $result,
    ) {}
}
