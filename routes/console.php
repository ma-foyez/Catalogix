<?php

use App\Enums\ExportStatus;
use App\Models\Export;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('exports:prune', function () {
    $disk = Storage::disk((string) config('catalogix.exports.disk', 'local'));
    $count = 0;

    Export::query()
        ->where('status', ExportStatus::Ready)
        ->where('expires_at', '<', now())
        ->whereNotNull('file_path')
        ->each(function (Export $export) use ($disk, &$count): void {
            $disk->delete((string) $export->file_path);
            $export->forceFill(['file_path' => null])->save();
            $count++;
        });

    $this->info("Removed {$count} expired export files.");
})->purpose('Delete export files whose 24-hour download link has expired');

Schedule::command('exports:prune')->hourly();
Schedule::command('queue:prune-failed --hours=168')->daily();
