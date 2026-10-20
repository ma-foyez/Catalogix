<?php

namespace App\Http\Resources;

use App\Models\DiscoveredRoute;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin DiscoveredRoute
 */
class DiscoveredRouteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => $this->url,
            'path' => $this->path,
            'category_name' => $this->category_name,
            'source' => $this->source->value,
            'score' => $this->score,
            'score_label' => $this->scoreLabel(),
            'est_products' => $this->est_products,
            'sample_images' => $this->sample_images ?? [],
            'is_selected' => $this->is_selected,
        ];
    }
}
