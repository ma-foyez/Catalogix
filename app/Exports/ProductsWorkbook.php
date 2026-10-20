<?php

namespace App\Exports;

use App\Enums\ExportLayout;
use App\Models\Product;
use App\Models\Site;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Export;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * XLSX workbook: one sheet per site, or a single sheet with a Source column.
 */
class ProductsWorkbook implements Export, WithMultipleSheets
{
    /**
     * @param  Builder<Product>  $query
     */
    public function __construct(
        private readonly Builder $query,
        private readonly ExportLayout $layout,
        private readonly bool $withImages,
    ) {}

    /**
     * @return list<ProductsSheet>
     */
    public function sheets(): array
    {
        if ($this->layout === ExportLayout::Single) {
            return [new ProductsSheet($this->query, $this->withImages, withSource: true, title: 'Products')];
        }

        $siteIds = (clone $this->query)->reorder()->select('products.site_id')->distinct()->pluck('site_id');
        $sites = Site::query()->whereIn('id', $siteIds)->orderBy('domain')->get();

        if ($sites->isEmpty()) {
            return [new ProductsSheet($this->query, $this->withImages, title: 'Products')];
        }

        return array_values($sites->map(fn (Site $site) => new ProductsSheet(
            (clone $this->query)->where('products.site_id', $site->id),
            $this->withImages,
            title: $site->domain,
        ))->all());
    }
}
