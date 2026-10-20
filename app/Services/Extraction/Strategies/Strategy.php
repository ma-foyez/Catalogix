<?php

namespace App\Services\Extraction\Strategies;

use App\Enums\ExtractionStrategy;
use App\Services\Extraction\PageContext;
use App\Services\Extraction\StrategyResult;

interface Strategy
{
    public function key(): ExtractionStrategy;

    public function extract(PageContext $context): StrategyResult;
}
