<?php

namespace App\Models;

use App\Enums\ExtractionStrategy;
use App\Enums\Platform;
use Database\Factories\SiteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $domain
 * @property string|null $name
 * @property Platform $platform
 * @property bool $needs_js
 * @property ExtractionStrategy|null $strategy
 * @property array<string, string|null>|null $selectors
 * @property array<string, mixed>|null $pagination
 * @property bool $recipe_valid
 * @property bool $is_blocked
 * @property string|null $blocked_reason
 * @property Carbon|null $last_success_at
 */
#[Fillable([
    'domain', 'name', 'platform', 'needs_js', 'strategy', 'selectors', 'pagination',
    'recipe_valid', 'is_blocked', 'blocked_reason', 'last_success_at',
])]
class Site extends Model
{
    /** @use HasFactory<SiteFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'strategy' => ExtractionStrategy::class,
            'needs_js' => 'boolean',
            'selectors' => 'array',
            'pagination' => 'array',
            'recipe_valid' => 'boolean',
            'is_blocked' => 'boolean',
            'last_success_at' => 'datetime',
        ];
    }

    /**
     * Find or create the site for a host, stripping a leading "www.".
     */
    public static function forHost(string $host): self
    {
        $domain = preg_replace('/^www\./i', '', strtolower($host));

        return self::firstOrCreate(['domain' => $domain], ['name' => $domain]);
    }

    public function baseUrl(): string
    {
        return 'https://'.$this->domain;
    }

    public function hasRecipe(): bool
    {
        return $this->recipe_valid && ! empty($this->selectors['card']);
    }

    /** @return HasMany<Discovery, $this> */
    public function discoveries(): HasMany
    {
        return $this->hasMany(Discovery::class);
    }

    /** @return HasMany<Product, $this> */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /** @return HasMany<Category, $this> */
    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }
}
