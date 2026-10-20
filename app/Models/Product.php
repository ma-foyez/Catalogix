<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property int $site_id
 * @property int|null $category_id
 * @property string|null $subcategory
 * @property string|null $description
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
 * @property CarbonImmutable|null $first_seen_at
 * @property CarbonImmutable|null $last_seen_at
 * @property-read Site $site
 * @property-read Category|null $category
 * @property-read ProductImage|null $image
 */
#[Fillable([
    'site_id', 'category_id', 'subcategory', 'hash', 'name', 'price', 'sale_price', 'currency',
    'product_url', 'sku', 'brand', 'description', 'in_stock', 'raw', 'first_seen_at', 'last_seen_at',
])]
class Product extends Model
{
    /**
     * Whole-number saving of the sale price against the regular price, e.g. 35 for ৳230 → ৳149.
     */
    public function discountPercent(): ?int
    {
        if ($this->price === null || $this->sale_price === null || (float) $this->price <= 0) {
            return null;
        }

        return (int) round((1 - (float) $this->sale_price / (float) $this->price) * 100);
    }

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
