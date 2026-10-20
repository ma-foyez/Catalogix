<?php

namespace App\Http\Resources;

use App\Models\ScrapeJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ScrapeJob
 */
class ScrapeJobResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'discovery_id' => $this->discovery_id,
            'domain' => $this->discovery?->site?->domain,
            'site_id' => $this->discovery?->site_id,
            'status' => $this->status->value,
            'max_products' => $this->max_products,
            'min_products' => $this->min_products,
            'below_minimum' => $this->status->isFinished() && $this->total_products < $this->min_products,
            'limit_reached' => $this->limitReached(),
            'paused' => $this->isPaused(),
            'total_routes' => $this->total_routes,
            'total_products' => $this->total_products,
            'total_images' => $this->total_images,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'routes' => $this->whenLoaded('targets', fn () => ScrapeTargetResource::collection($this->targets)->resolve($request)),
        ];
    }
}
