<?php

namespace App\Http\Resources;

use App\Enums\ImageStatus;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductImage
 */
class ProductImageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'url' => $this->url(),
            'download_url' => $this->status === ImageStatus::Downloaded ? route('images.download', $this->id, false) : null,
            'thumb_url' => $this->thumbUrl(),
            'source_url' => $this->source_url,
            'local_path' => $this->local_path,
            'width' => $this->width,
            'height' => $this->height,
            'bytes' => $this->bytes,
            'error' => $this->error,
        ];
    }
}
