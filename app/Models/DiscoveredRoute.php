<?php

namespace App\Models;

use App\Enums\RouteSource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $discovery_id
 * @property string $url
 * @property string $url_hash
 * @property string $path
 * @property string|null $category_name
 * @property RouteSource $source
 * @property int $score
 * @property int $est_products
 * @property list<string>|null $sample_images
 * @property bool $is_selected
 * @property-read Discovery $discovery
 */
#[Fillable([
    'discovery_id', 'url', 'url_hash', 'path', 'category_name', 'source',
    'score', 'est_products', 'sample_images', 'is_selected',
])]
class DiscoveredRoute extends Model
{
    /** Routes at or above this score are ticked by default. */
    public const int HIGH_SCORE = 60;

    protected function casts(): array
    {
        return [
            'source' => RouteSource::class,
            'score' => 'integer',
            'est_products' => 'integer',
            'sample_images' => 'array',
            'is_selected' => 'boolean',
        ];
    }

    /** @return BelongsTo<Discovery, $this> */
    public function discovery(): BelongsTo
    {
        return $this->belongsTo(Discovery::class);
    }

    public function scoreLabel(): string
    {
        return match (true) {
            $this->score >= self::HIGH_SCORE => 'high',
            $this->score >= 30 => 'medium',
            $this->score > 0 => 'low',
            default => 'none',
        };
    }
}
