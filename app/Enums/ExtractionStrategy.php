<?php

namespace App\Enums;

/**
 * The no-AI extraction ladder, in the order it is tried.
 */
enum ExtractionStrategy: string
{
    case Recipe = 'recipe';
    case JsonLd = 'json_ld';
    case PlatformApi = 'platform_api';
    case EmbeddedState = 'embedded_state';
    case NetworkJson = 'network_json';
    case CardDetection = 'card_detection';

    public function label(): string
    {
        return match ($this) {
            self::Recipe => 'Saved recipe',
            self::JsonLd => 'JSON-LD / microdata',
            self::PlatformApi => 'Platform API',
            self::EmbeddedState => 'Embedded state JSON',
            self::NetworkJson => 'Captured network JSON',
            self::CardDetection => 'Automatic card detection',
        };
    }
}
