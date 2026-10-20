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
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * One sheet of products, readable without opening any link: with images, column A holds the
 * product picture itself (one Drawing per row, added in AfterSheet) instead of image URLs.
 *
 * Columns: [Picture ·] Name · SKU · Brand · Category · Subcategory · Price (৳) · Sale price (৳) · Discount ·
 * Stock · Description · Product link [· Image link · Local image path] [· Website]
 */
class ProductsSheet implements FromQuery, WithColumnWidths, WithCustomCsvSettings, WithEvents, WithHeadings, WithMapping, WithTitle
{
    private const int PICTURE_PX = 90;

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
     * Heading => column width. Image links are only listed when the picture itself is not in the file.
     *
     * @return array<string, int>
     */
    private function columns(): array
    {
        return [
            ...($this->withImages ? ['Picture' => 16] : []),
            'Name' => 40,
            'SKU' => 16,
            'Brand' => 16,
            'Category' => 22,
            'Subcategory' => 20,
            'Price (৳)' => 12,
            'Sale price (৳)' => 14,
            'Discount' => 10,
            'Stock' => 13,
            'Description' => 55,
            'Product link' => 45,
            ...($this->withImages ? [] : ['Image link' => 45, 'Local image path' => 45]),
            ...($this->withSource ? ['Website' => 22] : []),
        ];
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return array_keys($this->columns());
    }

    /**
     * @param  Product  $product
     * @return list<mixed>
     */
    public function map($product): array
    {
        $discount = $product->discountPercent();

        return [
            ...($this->withImages ? [''] : []),
            $product->name,
            $product->sku,
            $product->brand,
            $product->category?->name,
            $product->subcategory,
            $product->price !== null ? (float) $product->price : null,
            $product->sale_price !== null ? (float) $product->sale_price : null,
            $discount !== null && $discount > 0 ? "{$discount}%" : null,
            match ($product->in_stock) {
                true => 'In stock',
                false => 'Out of stock',
                default => '',
            },
            $product->description !== null ? Str::limit($product->description, 1000) : null,
            $product->product_url,
            ...($this->withImages ? [] : [$product->image?->source_url, $product->image?->local_path]),
            ...($this->withSource ? [$product->site->domain] : []),
        ];
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
        $widths = [];

        foreach (array_values($this->columns()) as $index => $width) {
            $widths[Coordinate::stringFromColumnIndex($index + 1)] = $width;
        }

        return $widths;
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
            $this->style($sheet);

            if ($this->withImages) {
                $this->addPictures($sheet);
            }
        }];
    }

    private function column(string $heading): string
    {
        return Coordinate::stringFromColumnIndex((int) array_search($heading, $this->headings(), true) + 1);
    }

    /**
     * Bold tinted header with a filter, wrapped name and description, prices with thousands
     * separators, and clickable product links.
     */
    private function style(Worksheet $sheet): void
    {
        $last = Coordinate::stringFromColumnIndex(count($this->headings()));
        $lastRow = max(2, $sheet->getHighestRow());

        $header = $sheet->getStyle("A1:{$last}1");
        $header->getFont()->setBold(true);
        $header->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EDE9FE');
        $header->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getRowDimension(1)->setRowHeight(22);

        $sheet->freezePane($this->withImages ? 'C2' : 'B2');
        $sheet->setAutoFilter("A1:{$last}{$lastRow}");

        $sheet->getStyle("A1:{$last}{$lastRow}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);

        foreach (['Name', 'Description'] as $heading) {
            $sheet->getStyle($this->column($heading)."2:{$this->column($heading)}{$lastRow}")->getAlignment()->setWrapText(true);
        }

        $link = $this->column('Product link');
        $prices = [$this->column('Price (৳)'), $this->column('Sale price (৳)')];

        for ($row = 2; $row <= $sheet->getHighestRow(); $row++) {
            foreach ($prices as $column) {
                $value = $sheet->getCell("{$column}{$row}")->getValue();

                if (is_int($value) || is_float($value)) {
                    $sheet->getStyle("{$column}{$row}")->getNumberFormat()->setFormatCode(floor($value) == $value ? '#,##0' : '#,##0.00');
                }
            }

            $url = $sheet->getCell("{$link}{$row}")->getValue();

            if (is_string($url) && str_starts_with($url, 'http')) {
                $sheet->getCell("{$link}{$row}")->getHyperlink()->setUrl($url);
                $sheet->getStyle("{$link}{$row}")->getFont()->setUnderline(true)->getColor()->setRGB('5B21B6');
            }
        }
    }

    /**
     * The 200 px thumbnail of each product, shown at 90 px in column A.
     */
    private function addPictures(Worksheet $sheet): void
    {
        $disk = Storage::disk((string) config('catalogix.images.disk', 'public'));
        $row = 2;

        foreach ((clone $this->query)->cursor() as $product) {
            $sheet->getRowDimension($row)->setRowHeight(self::PICTURE_PX * 0.75 + 10);
            $thumb = $product->image?->thumb_path;

            if ($thumb !== null && $disk->exists($thumb)) {
                $drawing = new Drawing;
                $drawing->setName($product->name);
                $drawing->setPath($disk->path($thumb));
                $drawing->setHeight(self::PICTURE_PX);
                $drawing->setCoordinates("A{$row}");
                $drawing->setOffsetX(8);
                $drawing->setOffsetY(6);
                $drawing->setWorksheet($sheet);
            }

            $row++;
        }
    }
}
