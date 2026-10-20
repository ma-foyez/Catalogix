<?php

namespace App\Enums;

enum RouteSource: string
{
    case Sitemap = 'sitemap';
    case PlatformApi = 'platform_api';
    case Menu = 'menu';
    case Pattern = 'pattern';
}
