<?php

namespace App\Enums;

enum ExportScope: string
{
    /** One extracted route (scrape target). */
    case Route = 'route';

    /** Every route of one scrape job (one site). */
    case Site = 'site';

    /** Every product from all of the user's scrape jobs. */
    case All = 'all';
}
