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
            self::XlsxImages => 'Excel with images',
            self::Xlsx => 'Excel without images',
            self::Csv => 'CSV',
            self::ZipImages => 'Images ZIP',
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
