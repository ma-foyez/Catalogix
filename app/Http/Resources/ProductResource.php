<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price !== null ? (float) $this->price : null,
            'sale_price' => $this->sale_price !== null ? (float) $this->sale_price : null,
            'currency' => $this->currency,
            'category' => $this->whenLoaded('category', fn () => $this->category?->name),
            'subcategory' => $this->subcategory,
            'description' => $this->description,
            'discount_percent' => $this->discountPercent(),
            'site' => $this->whenLoaded('site', fn () => $this->site->domain),
            'website_url' => $this->whenLoaded('site', fn () => 'https://'.$this->site->domain),
            'sources' => $this->whenLoaded('scrapeTargets', fn () => $this->scrapeTargets->map(fn ($target) => [
                'id' => $target->id,
                'url' => $target->url,
                'category' => $target->categoryName(),
                'source' => $target->discoveredRoute?->source->value,
                'strategy' => $target->strategy_used?->label(),
            ])->values()->all()),
            'in_stock' => $this->in_stock,
            'product_url' => $this->product_url,
            'sku' => $this->sku,
            'brand' => $this->brand,
            'image' => $this->whenLoaded('image', fn () => $this->image ? (new ProductImageResource($this->image))->resolve($request) : null),
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
        ];
    }
}
