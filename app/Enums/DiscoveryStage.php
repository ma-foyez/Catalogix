<?php

namespace App\Enums;

/**
 * Where a running discovery is, so the UI can say what is happening right now.
 */
enum DiscoveryStage: string
{
    case Waiting = 'waiting';
    case Home = 'home';
    case Sitemaps = 'sitemaps';
    case Catalogue = 'catalogue';
    case Menu = 'menu';
    case Probing = 'probing';
    case Rendering = 'rendering';
    case Finished = 'finished';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Waiting for a worker',
            self::Home => 'Opening the home page',
            self::Sitemaps => 'Reading sitemaps',
            self::Catalogue => 'Reading the store catalogue',
            self::Menu => 'Following the menu',
            self::Probing => 'Checking pages for products',
            self::Rendering => 'Trying JavaScript rendering',
            self::Finished => 'Finished',
        };
    }
}
