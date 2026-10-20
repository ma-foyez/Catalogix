<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $site_id
 * @property string $name
 * @property string $slug
 * @property string|null $source_url
 * @property-read int|null $products_count
 * @property-read Site $site
 */
#[Fillable(['site_id', 'name', 'slug', 'source_url'])]
class Category extends Model
{
    public static function resolve(Site $site, string $name, ?string $sourceUrl = null): self
    {
        $slug = Str::slug($name) ?: 'uncategorized';

        return self::firstOrCreate(
            ['site_id' => $site->id, 'slug' => $slug],
            ['name' => $name, 'source_url' => $sourceUrl],
        );
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
