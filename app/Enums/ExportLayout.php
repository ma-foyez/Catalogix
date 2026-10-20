<?php

namespace App\Enums;

enum ExportLayout: string
{
    case PerSite = 'per_site';
    case Single = 'single';

    public function label(): string
    {
        return match ($this) {
            self::PerSite => 'One sheet per site',
            self::Single => 'One sheet with a Source column',
        };
    }
}
