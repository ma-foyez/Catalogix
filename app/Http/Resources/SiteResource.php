<?php

namespace App\Http\Resources;

use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Site
 */
class SiteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'domain' => $this->domain,
            'platform' => $this->platform->label(),
            'strategy' => $this->strategy?->label(),
            'needs_js' => $this->needs_js,
            'has_recipe' => ! empty($this->selectors['card']),
            'recipe_valid' => $this->recipe_valid,
            'is_blocked' => $this->is_blocked,
            'blocked_reason' => $this->blocked_reason,
            'products_count' => $this->products_count ?? 0,
            'last_success_at' => $this->last_success_at?->toIso8601String(),
        ];
    }
}
