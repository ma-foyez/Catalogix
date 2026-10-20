<?php

namespace App\Jobs;

use App\Enums\ExportStatus;
use App\Events\ExportReady;
use App\Http\Resources\ExportResource;
use App\Models\Export;
use App\Services\Exports\ExportBuilder;
use App\Support\Live;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ExportProductsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 2;

    public int $backoff = 30;

    public function __construct(public int $exportId)
    {
        $this->onQueue('exports');
    }

    public function handle(ExportBuilder $builder): void
    {
        $export = Export::with('scrapeJob.discovery.site')->find($this->exportId);

        if ($export === null || $export->status === ExportStatus::Ready) {
            return;
        }

        $builder->build($export);

        Live::send(new ExportReady($export->user_id, (new ExportResource($export))->resolve()));
    }

    public function failed(?Throwable $exception): void
    {
        $export = Export::find($this->exportId);

        if ($export === null) {
            return;
        }

        $export->forceFill([
            'status' => ExportStatus::Failed,
            'error' => mb_substr($exception?->getMessage() ?? 'Export failed.', 0, 2000),
        ])->save();

        Live::send(new ExportReady($export->user_id, (new ExportResource($export))->resolve()));
    }
}
