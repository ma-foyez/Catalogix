<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $site_id
 * @property int|null $category_id
 * @property string $hash
 * @property string $name
 * @property string|null $price
 * @property string|null $sale_price
 * @property string $currency
 * @property string|null $product_url
 * @property string|null $sku
 * @property string|null $brand
 * @property bool|null $in_stock
 * @property array<string, mixed>|null $raw
 * @property Carbon|null $first_seen_at
 * @property Carbon|null $last_seen_at
 * @property-read Site $site
 * @property-read Category|null $category
 * @property-read ProductImage|null $image
 */
#[Fillable([
    'site_id', 'category_id', 'hash', 'name', 'price', 'sale_price', 'currency',
    'product_url', 'sku', 'brand', 'in_stock', 'raw', 'first_seen_at', 'last_seen_at',
])]
class Product extends Model
{
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'in_stock' => 'boolean',
            'raw' => 'array',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return HasOne<ProductImage, $this> */
    public function image(): HasOne
    {
        return $this->hasOne(ProductImage::class)->latestOfMany();
    }

    /** @return BelongsToMany<ScrapeTarget, $this> */
    public function scrapeTargets(): BelongsToMany
    {
        return $this->belongsToMany(ScrapeTarget::class);
    }
}
