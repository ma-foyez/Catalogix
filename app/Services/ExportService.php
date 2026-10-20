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
     * @param  array<string, mixed>  $data  validated StoreExportRequest input: scope, job_id, route_id, format, layout, embed_images, category_id
     */
    public function create(User $user, array $data): Export
    {
        $scope = ExportScope::from((string) $data['scope']);
        $format = ExportFormat::from((string) $data['format']);
        $routeId = isset($data['route_id']) ? (int) $data['route_id'] : null;
        $jobId = isset($data['job_id']) ? (int) $data['job_id'] : null;
        $categoryId = isset($data['category_id']) ? (int) $data['category_id'] : null;

        // The REST API may send format "xlsx" with embed_images=true.
        if ($format === ExportFormat::Xlsx && filter_var($data['embed_images'] ?? false, FILTER_VALIDATE_BOOL)) {
            $format = ExportFormat::XlsxImages;
        }

        if ($scope === ExportScope::Route && $routeId !== null) {
            $jobId = (int) ScrapeTarget::whereKey($routeId)->value('scrape_job_id');
        }

        if ($scope === ExportScope::All) {
            $jobId = null;
        }

        $export = Export::create([
            'user_id' => $user->id,
            'scrape_job_id' => $jobId,
            'scope' => array_filter([
                'type' => $scope->value,
                'scrape_job_id' => $jobId,
                'scrape_target_id' => $scope === ExportScope::Route ? $routeId : null,
                'category_id' => $categoryId,
                'product_ids' => isset($data['product_ids']) ? array_values(array_map('intval', $data['product_ids'])) : null,
                'excluded_product_ids' => isset($data['excluded_product_ids']) ? array_values(array_map('intval', $data['excluded_product_ids'])) : null,
                'filters' => $data['filters'] ?? null,
            ], fn ($value) => $value !== null),
            'format' => $format,
            'layout' => ExportLayout::tryFrom((string) ($data['layout'] ?? '')) ?? ExportLayout::PerSite,
            'status' => ExportStatus::Pending,
        ]);

        ExportProductsJob::dispatch($export->id);

        return $export;
    }
}
