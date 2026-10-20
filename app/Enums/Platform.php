<?php

namespace App\Enums;

enum Platform: string
{
    case WooCommerce = 'woocommerce';
    case Shopify = 'shopify';
    case Custom = 'custom';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::WooCommerce => 'WooCommerce',
            self::Shopify => 'Shopify',
            self::Custom => 'Custom',
            self::Unknown => 'Unknown',
        };
    }
}
