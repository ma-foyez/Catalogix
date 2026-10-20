<?php

namespace App\Models;

use App\Enums\ImageStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $product_id
 * @property string $source_url
 * @property string $url_hash
 * @property string|null $local_path
 * @property string|null $thumb_path
 * @property int|null $width
 * @property int|null $height
 * @property int|null $bytes
 * @property ImageStatus $status
 * @property int $attempts
 * @property string|null $error
 * @property-read Product $product
 */
#[Fillable([
    'product_id', 'source_url', 'url_hash', 'local_path', 'thumb_path', 'width',
    'height', 'bytes', 'status', 'attempts', 'error',
])]
class ProductImage extends Model
{
    protected function casts(): array
    {
        return [
            'status' => ImageStatus::class,
        ];
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function url(): ?string
    {
        return $this->local_path ? Storage::disk('public')->url($this->local_path) : null;
    }

    public function thumbUrl(): ?string
    {
        return $this->thumb_path ? Storage::disk('public')->url($this->thumb_path) : null;
    }
}
