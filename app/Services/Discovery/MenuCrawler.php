<?php

namespace App\Services\Discovery;

use App\Services\Crawling\UrlTools;
use App\Services\Extraction\Dom;
use DOMElement;

/**
 * Collects internal links from a page's navigation: menus, headers, category sidebars and mega-menus.
 */
final class MenuCrawler
{
    private const array NAV_SELECTORS = [
        'nav a[href]',
        'header a[href]',
        '[role="navigation"] a[href]',
        '.menu a[href]',
        '.nav a[href]',
        '#menu a[href]',
        '[class*="categor"] a[href]',
        '[class*="mega"] a[href]',
        '[class*="sidebar"] a[href]',
        'aside a[href]',
    ];

    private const int MIN_NAV_LINKS = 5;

    private const int MAX_LINKS = 400;

    /**
     * Navigation links; falls back to every link on the page when the page has no recognisable menu.
     *
     * @return list<array{url: string, text: string}>
     */
    public function links(string $html, string $pageUrl, string $domain): array
    {
        $root = Dom::crawler($html, $pageUrl)->getNode(0);

        if (! $root instanceof DOMElement) {
            return [];
        }

        $links = $this->collect($root, self::NAV_SELECTORS, $pageUrl, $domain);

        if (count($links) < self::MIN_NAV_LINKS) {
            $links = $this->collect($root, ['a[href]'], $pageUrl, $domain);
        }

        return array_values($links);
    }

    /**
     * Links already resolved by the Python helper (rendered menus).
     *
     * @param  list<string>  $urls
     * @return list<array{url: string, text: string}>
     */
    public function fromRenderedLinks(array $urls, string $domain): array
    {
        $links = [];

        foreach (array_slice($urls, 0, self::MAX_LINKS) as $url) {
            if (UrlTools::isSameSite($url, $domain)) {
                $links[UrlTools::hash($url)] ??= ['url' => $url, 'text' => ''];
            }
        }

        return array_values($links);
    }

    /**
     * @param  list<string>  $selectors
     * @return array<string, array{url: string, text: string}>
     */
    private function collect(DOMElement $root, array $selectors, string $pageUrl, string $domain): array
    {
        $links = [];

        foreach ($selectors as $selector) {
            foreach (Dom::all($root, $selector) as $anchor) {
                $url = UrlTools::absolute($anchor->getAttribute('href'), $pageUrl);

                if ($url === null || ! UrlTools::isSameSite($url, $domain) || UrlTools::isAsset($url)) {
                    continue;
                }

                $text = Dom::text($anchor) ?: trim($anchor->getAttribute('title')) ?: trim($anchor->getAttribute('aria-label'));
                $key = UrlTools::hash($url);

                if (! isset($links[$key]) || ($links[$key]['text'] === '' && $text !== '')) {
                    $links[$key] = ['url' => $url, 'text' => mb_substr($text, 0, 120)];
                }

                if (count($links) >= self::MAX_LINKS) {
                    return $links;
                }
            }
        }

        return $links;
    }
}
