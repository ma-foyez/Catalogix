<?php

namespace App\Services\Extraction;

final readonly class UpsertResult
{
    /**
     * @param  list<int>  $productIds  every product saved from this page
     * @param  int  $newForTarget  products this route had not seen before (0 stops pagination)
     * @param  list<int>  $pendingImageIds  images waiting for download
     */
    public function __construct(
        public array $productIds,
        public int $newForTarget,
        public array $pendingImageIds,
    ) {}
}
