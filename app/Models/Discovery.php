<?php

namespace App\Models;

use App\Enums\DiscoveryStage;
use App\Enums\JobStatus;
use Carbon\CarbonImmutable;
use Database\Factories\DiscoveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property int $site_id
 * @property JobStatus $status
 * @property DiscoveryStage $stage
 * @property int $candidates
 * @property int $probed
 * @property int $to_probe
 * @property int $routes_found
 * @property string|null $error
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $finished_at
 * @property-read Site $site
 */
#[Fillable(['user_id', 'site_id', 'status', 'stage', 'candidates', 'probed', 'to_probe', 'routes_found', 'error', 'started_at', 'finished_at'])]
class Discovery extends Model
{
    /** @use HasFactory<DiscoveryFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = [
        'stage' => 'waiting',
        'candidates' => 0,
        'probed' => 0,
        'to_probe' => 0,
        'routes_found' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => JobStatus::class,
            'stage' => DiscoveryStage::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return HasMany<DiscoveredRoute, $this> */
    public function routes(): HasMany
    {
        return $this->hasMany(DiscoveredRoute::class)->orderByDesc('score')->orderByDesc('est_products');
    }

    /** @return HasMany<ScrapeJob, $this> */
    public function scrapeJobs(): HasMany
    {
        return $this->hasMany(ScrapeJob::class);
    }
}
