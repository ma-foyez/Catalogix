<?php

namespace App\Enums;

enum ExportFormat: string
{
    case XlsxImages = 'xlsx_images';
    case Xlsx = 'xlsx';
    case Csv = 'csv';
    case ZipImages = 'zip_images';

    public function label(): string
    {
        return match ($this) {
            self::XlsxImages => 'Excel with pictures',
            self::Xlsx => 'Excel (picture links)',
            self::Csv => 'CSV',
            self::ZipImages => 'Pictures (ZIP)',
        };
    }

    public function extension(): string
    {
        return match ($this) {
            self::XlsxImages, self::Xlsx => 'xlsx',
            self::Csv => 'csv',
            self::ZipImages => 'zip',
        };
    }

    public function embedsImages(): bool
    {
        return $this === self::XlsxImages;
    }
}
