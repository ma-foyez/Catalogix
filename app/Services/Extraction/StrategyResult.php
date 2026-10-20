<?php

namespace App\Services\Extraction;

final readonly class StrategyResult
{
    /**
     * @param  list<array<string, mixed>>  $products  raw product arrays for ProductNormalizer
     * @param  string|null  $nextUrl  next listing page found by the strategy itself
     * @param  bool  $hasMorePages  platform APIs: another API page exists
     * @param  array<string, string|null>|null  $recipe  selectors worth saving on the site
     * @param  bool  $usedRender  the products came from the Python-rendered page
     */
    public function __construct(
        public array $products = [],
        public ?string $nextUrl = null,
        public bool $hasMorePages = false,
        public ?array $recipe = null,
        public bool $usedRender = false,
    ) {}

    public static function empty(): self
    {
        return new self;
    }
}
