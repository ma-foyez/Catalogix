<?php

namespace App\Services\Exports;

use App\Enums\ExportFormat;
use App\Enums\ExportLayout;
use App\Enums\ExportScope;
use App\Enums\ExportStatus;
use App\Exports\ProductsSheet;
use App\Exports\ProductsWorkbook;
use App\Models\Category;
use App\Models\Export;
use App\Models\Product;
use App\Models\Site;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\Export as ExcelExport;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use ZipArchive;

/**
 * Builds the export file: CSV, XLSX with or without embedded thumbnails, or an images ZIP.
 * Excel files with images are capped (3,000 rows by default); bigger exports are split into one
 * file per site, then per category, then in parts, and delivered together as a ZIP.
 */
final class ExportBuilder
{
    public function __construct(
        private readonly ExportProductQuery $products,
        private readonly ImagesZipBuilder $images,
    ) {}

    public function build(Export $export): Export
    {
        $export->forceFill(['status' => ExportStatus::Processing, 'error' => null])->save();

        $query = $this->products->for($export);
        $rows = (clone $query)->count();
        $directory = $this->directory($export);
        $name = $this->baseName($export);

        $path = match ($export->format) {
            ExportFormat::Csv => $this->store(new ProductsSheet($query, withSource: true), "{$directory}/{$name}.csv", ExcelWriter::CSV),
            ExportFormat::Xlsx => $this->store(new ProductsWorkbook($query, $export->layout, false), "{$directory}/{$name}.xlsx", ExcelWriter::XLSX),
            ExportFormat::XlsxImages => $this->excelWithImages($query, $export->layout, $rows, $directory, $name),
            ExportFormat::ZipImages => $this->imagesZip($query, "{$directory}/{$name}.zip"),
        };

        $export->forceFill([
            'file_path' => $path,
            'bytes' => $this->disk()->size($path),
            'row_count' => $rows,
            'status' => ExportStatus::Ready,
            'expires_at' => now()->addHours((int) config('catalogix.exports.expires_hours', 24)),
        ])->save();

        return $export;
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function excelWithImages(Builder $query, ExportLayout $layout, int $rows, string $directory, string $name): string
    {
        $limit = max(1, (int) config('catalogix.exports.image_row_limit', 3000));

        if ($rows <= $limit) {
            return $this->store(new ProductsWorkbook($query, $layout, true), "{$directory}/{$name}.xlsx", ExcelWriter::XLSX);
        }

        $parts = [];

        foreach ($this->splitGroups($query, $limit) as $fileName => $groupQuery) {
            $parts[$fileName] = $this->store(new ProductsWorkbook($groupQuery, ExportLayout::PerSite, true), "{$directory}/{$name}-parts/{$fileName}.xlsx", ExcelWriter::XLSX);
        }

        $zipPath = "{$directory}/{$name}.zip";
        $this->zipFiles($parts, $zipPath);
        $this->disk()->deleteDirectory("{$directory}/{$name}-parts");

        return $zipPath;
    }

    /**
     * One query per site; sites over the limit are split per category, and categories over the limit into parts.
     *
     * @param  Builder<Product>  $query
     * @return array<string, Builder<Product>>
     */
    private function splitGroups(Builder $query, int $limit): array
    {
        $groups = [];
        $counts = (clone $query)->reorder()->toBase()->selectRaw('products.site_id, products.category_id, count(*) as aggregate')
            ->groupBy('products.site_id', 'products.category_id')
            ->get();

        foreach ($counts->groupBy('site_id') as $siteId => $categories) {
            $site = Site::find($siteId);
            $siteName = $this->fileSafe($site->domain ?? 'site-'.$siteId);
            $siteQuery = (clone $query)->where('products.site_id', $siteId);

            if ($categories->sum('aggregate') <= $limit) {
                $groups[$siteName] = $siteQuery;

                continue;
            }

            foreach ($categories as $row) {
                $categoryName = $row->category_id ? (Category::find($row->category_id)->slug ?? 'category-'.$row->category_id) : 'uncategorized';
                $categoryQuery = (clone $siteQuery)->where(fn (Builder $q) => $row->category_id
                    ? $q->where('products.category_id', $row->category_id)
                    : $q->whereNull('products.category_id'));

                if ($row->aggregate <= $limit) {
                    $groups["{$siteName}-{$categoryName}"] = $categoryQuery;

                    continue;
                }

                $ids = (clone $categoryQuery)->reorder()->orderBy('products.id')->pluck('products.id')->chunk($limit);

                foreach ($ids as $index => $chunk) {
                    $groups["{$siteName}-{$categoryName}-part-".($index + 1)] = (clone $categoryQuery)->whereIn('products.id', $chunk->all());
                }
            }
        }

        return $groups;
    }

    /**
     * @param  Builder<Product>  $query
     */
    private function imagesZip(Builder $query, string $path): string
    {
        File::ensureDirectoryExists(dirname($this->disk()->path($path)));
        $this->images->build($query, $this->disk()->path($path));

        return $path;
    }

    private function store(ExcelExport $export, string $path, string $writerType): string
    {
        Excel::store($export, $path, $this->diskName(), $writerType);

        return $path;
    }

    /**
     * @param  array<string, string>  $files  entry name => disk path
     */
    private function zipFiles(array $files, string $zipPath): void
    {
        $absolute = $this->disk()->path($zipPath);
        File::ensureDirectoryExists(dirname($absolute));

        $zip = new ZipArchive;

        if ($zip->open($absolute, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the ZIP file.');
        }

        foreach ($files as $entry => $path) {
            $zip->addFile($this->disk()->path($path), $entry.'.'.pathinfo($path, PATHINFO_EXTENSION));
        }

        $zip->close();
    }

    private function directory(Export $export): string
    {
        return trim((string) config('catalogix.exports.directory', 'exports'), '/').'/'.$export->user_id;
    }

    private function baseName(Export $export): string
    {
        $scope = $export->scope;

        $label = match (ExportScope::tryFrom($scope['type'])) {
            ExportScope::Route => 'route-'.($scope['scrape_target_id'] ?? ''),
            ExportScope::All => 'all-sites',
            default => $export->scrapeJob->discovery->site->domain ?? 'job-'.$export->scrape_job_id,
        };

        $suffix = $export->format === ExportFormat::ZipImages ? '-images' : ($export->format === ExportFormat::XlsxImages ? '-with-images' : '');
        $suffix .= isset($scope['product_ids']) ? '-selected' : (isset($scope['filters']) ? '-filtered' : '');

        return 'catalogix-'.$this->fileSafe($label).$suffix.'-'.now()->format('Ymd-His').'-'.$export->id;
    }

    /**
     * Keep domains readable in file names ("chaldal.com"), replacing anything else unsafe.
     */
    private function fileSafe(string $value): string
    {
        return trim((string) preg_replace('/[^a-z0-9.-]+/', '-', strtolower($value)), '-.') ?: 'export';
    }

    private function diskName(): string
    {
        return (string) config('catalogix.exports.disk', 'local');
    }

    private function disk(): Filesystem
    {
        return Storage::disk($this->diskName());
    }
}
