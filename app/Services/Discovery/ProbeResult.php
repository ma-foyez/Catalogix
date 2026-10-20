<?php

namespace App\Services\Discovery;

final readonly class ProbeResult
{
    /**
     * @param  list<string>  $sampleImages
     */
    public function __construct(
        public int $score = 0,
        public int $productCount = 0,
        public int $estProducts = 0,
        public array $sampleImages = [],
        public ?string $heading = null,
        public ?string $blockedReason = null,
    ) {}

    public static function blocked(string $reason): self
    {
        return new self(blockedReason: $reason);
    }
}
