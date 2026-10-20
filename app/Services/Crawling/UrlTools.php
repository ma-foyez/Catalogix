<?php

namespace App\Services\Crawling;

final class UrlTools
{
    /** Query parameters that only track visitors and never change the page. */
    private const array TRACKING_PARAMS = ['fbclid', 'gclid', 'yclid', 'msclkid', 'mc_cid', 'mc_eid', '_ga', 'ref', 'srsltid'];

    /**
     * Turn whatever the user typed ("chaldal.com", "https://www.chaldal.com/popular") into a bare domain.
     */
    public static function isHomePage(string $url): bool
    {
        $path = parse_url($url, PHP_URL_PATH);

        return (! is_string($path) || trim($path, '/') === '') && parse_url($url, PHP_URL_QUERY) === null;
    }

    public static function domainFromInput(string $input): ?string
    {
        $input = trim($input);

        if ($input === '') {
            return null;
        }

        if (! preg_match('#^https?://#i', $input)) {
            $input = 'https://'.$input;
        }

        $host = parse_url($input, PHP_URL_HOST);

        if (! is_string($host) || ! str_contains($host, '.')) {
            return null;
        }

        $host = strtolower(rtrim($host, '.'));

        if (! preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $host)) {
            return null;
        }

        return preg_replace('/^www\./', '', $host);
    }

    /**
     * Resolve an href against the page it was found on. Returns null for non-navigable links.
     */
    public static function absolute(?string $href, string $base): ?string
    {
        if ($href === null) {
            return null;
        }

        $href = trim(html_entity_decode($href, ENT_QUOTES | ENT_HTML5));

        if ($href === '' || str_starts_with($href, '#') || preg_match('#^(javascript|mailto|tel|sms|data|whatsapp):#i', $href)) {
            return null;
        }

        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }

        $parts = parse_url($base);

        if (! isset($parts['host'])) {
            return null;
        }

        $scheme = $parts['scheme'] ?? 'https';
        $origin = $scheme.'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (str_starts_with($href, '//')) {
            return $scheme.':'.$href;
        }

        if (str_starts_with($href, '/')) {
            return $origin.self::removeDotSegments($href);
        }

        $basePath = $parts['path'] ?? '/';

        if (str_starts_with($href, '?')) {
            return $origin.$basePath.$href;
        }

        $directory = preg_replace('#/[^/]*$#', '/', $basePath) ?: '/';

        return $origin.self::removeDotSegments($directory.$href);
    }

    /**
     * Drop the fragment and tracking parameters (utm_*, fbclid, ...) so the same page always has one URL.
     */
    public static function clean(string $url): string
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['host'])) {
            return $url;
        }

        $query = '';

        if (isset($parts['query']) && $parts['query'] !== '') {
            parse_str($parts['query'], $params);

            $params = array_filter(
                $params,
                fn ($key) => ! str_starts_with(strtolower((string) $key), 'utm_') && ! in_array(strtolower((string) $key), self::TRACKING_PARAMS, true),
                ARRAY_FILTER_USE_KEY,
            );

            $query = $params === [] ? '' : '?'.http_build_query($params);
        }

        $path = $parts['path'] ?? '/';

        return strtolower($parts['scheme'] ?? 'https').'://'
            .strtolower($parts['host'])
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .($path === '' ? '/' : $path)
            .$query;
    }

    public static function hash(string $url): string
    {
        return sha1(self::clean($url));
    }

    /**
     * The host without "www.", lowercased.
     */
    public static function host(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? preg_replace('/^www\./', '', strtolower($host)) : null;
    }

    public static function isSameSite(string $url, string $domain): bool
    {
        $host = self::host($url);

        return $host !== null && ($host === $domain || str_ends_with($host, '.'.$domain));
    }

    /**
     * Path plus query string, used as the route label in the discovery checklist.
     */
    public static function pathWithQuery(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '/';
        $query = parse_url($url, PHP_URL_QUERY);

        return $path.($query ? '?'.$query : '');
    }

    /**
     * Replace or add query parameters.
     *
     * @param  array<string, scalar>  $params
     */
    public static function withQuery(string $url, array $params): string
    {
        $parts = parse_url($url);
        $existing = [];

        if (! empty($parts['query'])) {
            parse_str($parts['query'], $existing);
        }

        $query = http_build_query(array_merge($existing, $params));
        $base = strtok($url, '?#');

        return $base.($query !== '' ? '?'.$query : '');
    }

    public static function isAsset(string $url): bool
    {
        $path = strtolower(parse_url($url, PHP_URL_PATH) ?: '');

        return (bool) preg_match('/\.(jpe?g|png|gif|webp|svg|ico|css|js|pdf|zip|rar|mp4|mp3|woff2?|ttf|xml|json|txt)$/', $path);
    }

    private static function removeDotSegments(string $path): string
    {
        [$pathOnly, $rest] = array_pad(preg_split('/(?=[?#])/', $path, 2) ?: [$path], 2, '');

        $output = [];

        foreach (explode('/', $pathOnly) as $segment) {
            if ($segment === '..') {
                array_pop($output);
            } elseif ($segment !== '.') {
                $output[] = $segment;
            }
        }

        $resolved = implode('/', $output);

        return (str_starts_with($resolved, '/') ? $resolved : '/'.$resolved).$rest;
    }
}
