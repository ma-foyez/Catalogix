<?php

namespace App\Http\Resources;

use App\Models\ScrapeTarget;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ScrapeTarget
 */
class ScrapeTargetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'category' => $this->discoveredRoute?->category_name,
            'status' => $this->status->value,
            'strategy' => $this->strategy_used?->value,
            'strategy_label' => $this->strategy_used?->label(),
            'pages_crawled' => $this->pages_crawled,
            'products_found' => $this->products_found,
            'error' => $this->error,
        ];
    }
}
