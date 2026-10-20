<?php

namespace App\Exports;

use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

/**
 * One sheet of products. With images, column A holds the 200 px thumbnail anchored to the cell
 * (one PhpSpreadsheet Drawing per row, added in AfterSheet).
 *
 * Columns: Image · Name · Price (৳) · Sale price · Category · Stock · Product URL · Image URL · Local image path [· Source]
 */
class ProductsSheet implements FromQuery, WithColumnWidths, WithCustomCsvSettings, WithEvents, WithHeadings, WithMapping, WithTitle
{
    /**
     * @param  Builder<Product>  $query
     */
    public function __construct(
        private readonly Builder $query,
        private readonly bool $withImages = false,
        private readonly bool $withSource = false,
        private readonly string $title = 'Products',
    ) {}

    /**
     * @return Builder<Product>
     */
    public function query(): Builder
    {
        return $this->query;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        $headings = ['Name', 'Price (৳)', 'Sale price', 'Category', 'Stock', 'Product URL', 'Image URL', 'Local image path'];

        if ($this->withImages) {
            array_unshift($headings, 'Image');
        }

        if ($this->withSource) {
            $headings[] = 'Source';
        }

        return $headings;
    }

    /**
     * @param  Product  $product
     * @return list<mixed>
     */
    public function map($product): array
    {
        $row = [
            $product->name,
            $product->price !== null ? (float) $product->price : null,
            $product->sale_price !== null ? (float) $product->sale_price : null,
            $product->category?->name,
            match ($product->in_stock) {
                true => 'In stock',
                false => 'Out of stock',
                default => '',
            },
            $product->product_url,
            $product->image?->source_url,
            $product->image?->local_path,
        ];

        if ($this->withImages) {
            array_unshift($row, '');
        }

        if ($this->withSource) {
            $row[] = $product->site->domain;
        }

        return $row;
    }

    public function title(): string
    {
        // Excel sheet names: 31 characters, none of : \ / ? * [ ]
        return Str::limit(str_replace([':', '\\', '/', '?', '*', '[', ']'], '-', $this->title), 31, '');
    }

    /**
     * @return array<string, int>
     */
    public function columnWidths(): array
    {
        $widths = [42, 12, 12, 22, 12, 50, 50, 50, 22];

        if ($this->withImages) {
            array_unshift($widths, 14);
        }

        $columns = [];

        foreach (array_slice($widths, 0, count($this->headings())) as $index => $width) {
            $columns[chr(65 + $index)] = $width;
        }

        return $columns;
    }

    /**
     * @return array<string, mixed>
     */
    public function getCsvSettings(): array
    {
        // A BOM makes Excel open Bengali text and ৳ correctly.
        return ['use_bom' => true];
    }

    /**
     * @return array<class-string, callable>
     */
    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event): void {
            $sheet = $event->sheet->getDelegate();
            $lastColumn = chr(64 + count($this->headings()));

            $sheet->freezePane('A2');
            $sheet->getStyle("A1:{$lastColumn}1")->getFont()->setBold(true);

            if (! $this->withImages) {
                return;
            }

            $disk = Storage::disk((string) config('catalogix.images.disk', 'public'));
            $row = 2;

            foreach ((clone $this->query)->cursor() as $product) {
                $sheet->getRowDimension($row)->setRowHeight(60);
                $thumb = $product->image?->thumb_path;

                if ($thumb !== null && $disk->exists($thumb)) {
                    $drawing = new Drawing;
                    $drawing->setPath($disk->path($thumb));
                    $drawing->setHeight(70);
                    $drawing->setCoordinates("A{$row}");
                    $drawing->setOffsetX(6);
                    $drawing->setOffsetY(6);
                    $drawing->setWorksheet($sheet);
                }

                $row++;
            }
        }];
    }
}
