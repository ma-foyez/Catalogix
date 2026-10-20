<?php

namespace App\Services\Exports;

use App\Enums\ImageStatus;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Packs downloaded product images into a ZIP with one folder per site and category.
 */
final class ImagesZipBuilder
{
    /**
     * @param  Builder<Product>  $query
     * @return int number of images added
     */
    public function build(Builder $query, string $zipPath): int
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the ZIP file.');
        }

        $disk = Storage::disk((string) config('catalogix.images.disk', 'public'));
        $added = [];

        foreach ((clone $query)->cursor() as $product) {
            $image = $product->image;

            if ($image === null || $image->status !== ImageStatus::Downloaded || $image->local_path === null || ! $disk->exists($image->local_path)) {
                continue;
            }

            $entry = $product->site->domain.'/'.($product->category?->slug ?: 'uncategorized').'/'.basename($image->local_path);

            if (isset($added[$entry])) {
                continue;
            }

            $zip->addFile($disk->path($image->local_path), $entry);
            $added[$entry] = true;
        }

        if ($added === []) {
            $zip->addFromString('README.txt', "No downloaded images matched this export yet.\nRetry failed images or wait for downloads to finish, then export again.\n");
        }

        $zip->close();

        return count($added);
    }
}
