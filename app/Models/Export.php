<?php

namespace App\Models;

use App\Enums\ExportFormat;
use App\Enums\ExportLayout;
use App\Enums\ExportStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $scrape_job_id
 * @property array{type: string, scrape_job_id?: int, scrape_target_id?: int, category_id?: int, product_ids?: list<int>, excluded_product_ids?: list<int>, filters?: array{route?: int|string|null, category?: int|string|null, search?: string|null, image?: string|null}} $scope
 * @property ExportFormat $format
 * @property ExportLayout $layout
 * @property string|null $file_path
 * @property int|null $bytes
 * @property int $row_count
 * @property ExportStatus $status
 * @property string|null $error
 * @property CarbonImmutable|null $expires_at
 */
#[Fillable([
    'user_id', 'scrape_job_id', 'scope', 'format', 'layout', 'file_path', 'bytes',
    'row_count', 'status', 'error', 'expires_at',
])]
class Export extends Model
{
    protected function casts(): array
    {
        return [
            'scope' => 'array',
            'format' => ExportFormat::class,
            'layout' => ExportLayout::class,
            'status' => ExportStatus::class,
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ScrapeJob, $this> */
    public function scrapeJob(): BelongsTo
    {
        return $this->belongsTo(ScrapeJob::class);
    }

    public function isDownloadable(): bool
    {
        return $this->status === ExportStatus::Ready
            && $this->file_path !== null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }

    /**
     * A signed download link that stops working when the export expires.
     */
    public function downloadUrl(string $route = 'exports.download'): ?string
    {
        if (! $this->isDownloadable()) {
            return null;
        }

        return URL::temporarySignedRoute($route, $this->expires_at ?? now()->addDay(), ['export' => $this->id]);
    }

    public function fileName(): string
    {
        return basename((string) $this->file_path);
    }
}
