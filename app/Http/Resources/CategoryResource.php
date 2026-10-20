<?php

namespace App\Http\Resources;

use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Category */
class CategoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'site_id' => $this->site_id, 'name' => $this->name,
            'domain' => $this->site->domain, 'products_count' => $this->products_count ?? 0,
        ];
    }
}
