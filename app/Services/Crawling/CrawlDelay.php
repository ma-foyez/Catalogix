<?php

namespace App\Services\Crawling;

/**
 * The polite pause between two requests to the same site (1-3 s by default).
 */
final class CrawlDelay
{
    public function seconds(): int
    {
        $min = max(0, (int) config('catalogix.page_delay.min', 1));
        $max = max($min, (int) config('catalogix.page_delay.max', 3));

        return random_int($min, $max);
    }

    public function pause(): void
    {
        $seconds = $this->seconds();

        if ($seconds > 0) {
            sleep($seconds);
        }
    }
}
