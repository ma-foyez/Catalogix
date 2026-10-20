<?php

namespace App\Services\Crawling;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Robots\RobotsTxt;
use Throwable;

/**
 * Reads and caches each site's robots.txt. A missing robots.txt allows everything.
 */
final class RobotsPolicy
{
    /** The token sites use to address Catalogix in robots.txt. */
    public const string AGENT_TOKEN = 'CatalogixBot';

    /** @var array<string, string> */
    private array $memo = [];

    public function allows(string $url): bool
    {
        $content = $this->content($url);

        if ($content === '') {
            return true;
        }

        try {
            // spatie/robots-txt splits on PHP_EOL ("\r\n" on Windows), so normalise line endings first.
            $content = (string) preg_replace("/\r\n|\r|\n/", PHP_EOL, $content);

            return RobotsTxt::create($content)->allows($url, self::AGENT_TOKEN);
        } catch (Throwable) {
            return true;
        }
    }

    /**
     * The sitemap URLs listed in robots.txt.
     *
     * @return list<string>
     */
    public function sitemaps(string $url): array
    {
        preg_match_all('/^\s*sitemap\s*:\s*(\S+)/im', $this->content($url), $matches);

        return array_values(array_unique($matches[1]));
    }

    private function content(string $url): string
    {
        $parts = parse_url($url);

        if (! isset($parts['host'])) {
            return '';
        }

        $origin = ($parts['scheme'] ?? 'https').'://'.$parts['host'];

        return $this->memo[$origin] ??= Cache::remember(
            'robots:'.sha1($origin),
            now()->addHour(),
            function () use ($origin): string {
                try {
                    $response = Http::withUserAgent((string) config('catalogix.user_agent'))
                        ->timeout(10)
                        ->get($origin.'/robots.txt');
                } catch (Throwable) {
                    return '';
                }

                return $response->successful() && ! str_contains(strtolower($response->header('Content-Type')), 'html')
                    ? $response->body()
                    : '';
            },
        );
    }
}
