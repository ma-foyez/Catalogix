<?php

namespace App\Http\Resources;

use App\Enums\DiscoveryStage;
use App\Enums\JobStatus;
use App\Models\Discovery;
use App\Models\ScrapeJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One website as a non-technical user sees it: a single phase from "waiting" to "ready",
 * a plain-language line about what is happening, and an overall progress percentage.
 * Expects the discovery with `site` and `latestJob` (with targets_count and finished_targets_count).
 *
 * @mixin Discovery
 */
class SiteRunResource extends JsonResource
{
    /** Progress at the start of each discovery stage; checking pages fills the gap up to 35%. */
    private const array STAGE_PROGRESS = [
        'waiting' => 2, 'home' => 5, 'sitemaps' => 9, 'catalogue' => 13, 'menu' => 17, 'probing' => 20, 'rendering' => 35, 'finished' => 38,
    ];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $job = $this->latestJob;
        $images = $job !== null ? $job->imageStats() : ['pending' => 0, 'downloaded' => 0, 'failed' => 0];
        [$phase, $detail, $progress] = $this->phase($job, $images);

        return [
            'id' => $this->id,
            'domain' => $this->site->domain,
            'phase' => $phase,
            'paused' => $this->paused_at !== null || ($job?->paused_at !== null),
            'max_products' => $job->max_products ?? $this->max_products,
            'limit_reached' => $job?->limitReached() ?? false,
            'detail' => $detail,
            'progress' => $progress,
            'stage' => $this->stage->value,
            'stage_label' => $this->stage->label(),
            'probed' => $this->probed,
            'to_probe' => $this->to_probe,
            'job_id' => $job?->id,
            'products' => $job->total_products ?? 0,
            'images' => $images['downloaded'],
            'images_pending' => $images['pending'],
            'images_failed' => $images['failed'],
            'categories' => $job !== null ? (int) ($job->targets_count ?? 0) : 0,
            'categories_done' => $job !== null ? (int) ($job->finished_targets_count ?? 0) : 0,
            'is_blocked' => $this->site->is_blocked,
            'auto' => $this->auto_extract,
            'created_at' => $this->created_at?->toIso8601String(),
            'finished_at' => ($job->finished_at ?? $this->finished_at)?->toIso8601String(),
        ];
    }

    /**
     * @param  array{pending: int, downloaded: int, failed: int}  $images
     * @return array{0: string, 1: string, 2: int}
     */
    private function phase(?ScrapeJob $job, array $images): array
    {
        if ($this->paused_at !== null || $job?->paused_at !== null) {
            return ['paused', 'Paused. Collected products are saved. Resume when you are ready; work continues within 30 seconds.', 0];
        }
        if ($this->status === JobStatus::Failed) {
            return $this->site->is_blocked
                ? ['blocked', 'This website blocks automatic visitors (a login, CAPTCHA or bot check), so it can\'t be read.', 100]
                : ['failed', $this->error ?? 'Something went wrong while reading this website.', 100];
        }

        if ($this->status === JobStatus::Queued) {
            return ['waiting', 'In line — starts in a moment.', 2];
        }

        if ($this->status === JobStatus::Running) {
            $base = self::STAGE_PROGRESS[$this->stage->value];
            $detail = $this->stage->label();

            if ($this->stage === DiscoveryStage::Probing && $this->to_probe > 0) {
                $base += (int) round(15 * $this->probed / $this->to_probe);
                $detail .= " · {$this->probed} of {$this->to_probe}";
            }

            return ['finding', $detail, $base];
        }

        // Discovery reports "done" a moment before it queues the extraction; don't flash "nothing found" in between.
        if ($job === null && $this->auto_extract && $this->routes_found > 0 && $this->finished_at?->isAfter(now()->subMinute())) {
            return ['collecting', 'Starting to collect products…', 40];
        }

        if ($job === null) {
            return $this->auto_extract || $this->routes_found === 0
                ? ['empty', 'No product listings were found on this website.', 100]
                : ['review', "{$this->routes_found} product pages found — choose which to collect.", 40];
        }

        $categories = (int) ($job->targets_count ?? 0);
        $done = (int) ($job->finished_targets_count ?? 0);

        if (! $job->status->isFinished()) {
            $share = $categories > 0 ? $done / $categories : 0;
            $products = number_format($job->total_products);

            return ['collecting', "{$done} of {$categories} sections done · {$products} products so far", 40 + (int) round(40 * $share)];
        }

        if ($job->total_products === 0) {
            return ['empty', 'The product pages were checked but no products could be read.', 100];
        }

        $total = $images['pending'] + $images['downloaded'] + $images['failed'];

        if ($images['pending'] > 0) {
            $saved = $images['downloaded'] + $images['failed'];

            return ['images', "Saving pictures · {$saved} of {$total}", 80 + (int) round(20 * ($total > 0 ? $saved / $total : 0))];
        }

        $detail = $job->limitReached() ? "Product limit reached ({$job->max_products}). Your products are ready." : 'All done — view the products or download the Excel file.';
        if ($job->total_products < $job->min_products) {
            $detail = "Found {$job->total_products} products, below your target of {$job->min_products}. All available results are saved.";
        }

        return ['ready', $detail, 100];
    }
}
