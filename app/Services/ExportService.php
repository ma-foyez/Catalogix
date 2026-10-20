<?php

namespace App\Services;

use App\Enums\ExportFormat;
use App\Enums\ExportLayout;
use App\Enums\ExportScope;
use App\Enums\ExportStatus;
use App\Jobs\ExportProductsJob;
use App\Models\Export;
use App\Models\ScrapeTarget;
use App\Models\User;

final class ExportService
{
    /**
     * @param  array{scope: string, job_id?: int|null, route_id?: int|null, format: string, layout?: string|null, embed_images?: bool|null, category_id?: int|null}  $data
     */
    public function create(User $user, array $data): Export
    {
        $scope = ExportScope::from($data['scope']);
        $format = ExportFormat::from($data['format']);

        // The REST API may send format "xlsx" with embed_images=true.
        if ($format === ExportFormat::Xlsx && ($data['embed_images'] ?? false)) {
            $format = ExportFormat::XlsxImages;
        }

        $jobId = $data['job_id'] ?? null;

        if ($scope === ExportScope::Route && ! empty($data['route_id'])) {
            $jobId = ScrapeTarget::whereKey($data['route_id'])->value('scrape_job_id');
        }

        $export = Export::create([
            'user_id' => $user->id,
            'scrape_job_id' => $scope === ExportScope::All ? null : $jobId,
            'scope' => array_filter([
                'type' => $scope->value,
                'scrape_job_id' => $scope === ExportScope::All ? null : $jobId,
                'scrape_target_id' => $scope === ExportScope::Route ? (int) $data['route_id'] : null,
                'category_id' => $data['category_id'] ?? null,
            ], fn ($value) => $value !== null),
            'format' => $format,
            'layout' => ExportLayout::tryFrom((string) ($data['layout'] ?? '')) ?? ExportLayout::PerSite,
            'status' => ExportStatus::Pending,
        ]);

        ExportProductsJob::dispatch($export->id);

        return $export;
    }
}
