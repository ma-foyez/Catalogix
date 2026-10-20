<?php

namespace App\Http\Resources;

use App\Models\Discovery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Discovery
 */
class DiscoveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'domain' => $this->whenLoaded('site', fn () => $this->site->domain),
            'site' => $this->whenLoaded('site', fn () => [
                'id' => $this->site->id,
                'domain' => $this->site->domain,
                'platform' => $this->site->platform->value,
                'needs_js' => $this->site->needs_js,
                'is_blocked' => $this->site->is_blocked,
                'blocked_reason' => $this->site->blocked_reason,
            ]),
            'auto_extract' => $this->auto_extract,
            'status' => $this->status->value,
            'stage' => $this->stage->value,
            'stage_label' => $this->stage->label(),
            'candidates' => $this->candidates,
            'probed' => $this->probed,
            'to_probe' => $this->to_probe,
            'routes_found' => $this->routes_found,
            'error' => $this->error,
            'started_at' => $this->started_at?->toIso8601String(),
            'finished_at' => $this->finished_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'routes' => $this->whenLoaded('routes', fn () => DiscoveredRouteResource::collection($this->routes)->resolve($request)),
        ];
    }
}
