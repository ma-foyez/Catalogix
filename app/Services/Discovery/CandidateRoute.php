<?php

namespace App\Services\Discovery;

use App\Enums\RouteSource;

final class CandidateRoute
{
    public function __construct(
        public string $url,
        public RouteSource $source,
        public ?string $name = null,
        public int $estProducts = 0,
        public bool $matchesListingPattern = false,
    ) {}

    /**
     * Higher ranks are probed first: platform categories with products, listing-looking paths, then menus and sitemaps.
     */
    public function rank(): int
    {
        return match (true) {
            $this->source === RouteSource::PlatformApi && $this->estProducts > 0 => 400,
            $this->matchesListingPattern => 300,
            $this->source === RouteSource::Menu => 200,
            $this->source === RouteSource::PlatformApi => 150,
            default => 100,
        };
    }
}
