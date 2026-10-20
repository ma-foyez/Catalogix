<?php

namespace App\Services\Discovery;

use App\Services\Crawling\PageFetcher;
use Closure;

/**
 * Reads sitemap.xml files and sitemap indexes (gzip included). Category and collection
 * sitemaps are read first because they list exactly the routes discovery is looking for.
 */
final class SitemapReader
{
    private const array PREFERRED = ['category', 'categories', 'collection', 'product_cat', 'product-cat', 'taxonomy', 'catalog'];

    private const array SKIPPED = ['post-sitemap', 'page-sitemap', 'author', 'post_tag', 'tag-sitemap', 'blog', 'news', 'video', 'image-sitemap'];

    public function __construct(private readonly PageFetcher $fetcher) {}

    private int $remainingRequests = 12;

    private ?Closure $checkpoint = null;

    public function begin(int $maxRequests, ?Closure $checkpoint = null): void
    {
        $this->remainingRequests = max(1, $maxRequests);
        $this->checkpoint = $checkpoint;
    }

    /**
     * @return list<string>
     */
    public function urls(string $sitemapUrl, int $limit, int $depth = 0): array
    {
        if ($this->remainingRequests-- <= 0) {
            return [];
        }

        ($this->checkpoint ?? static fn () => null)();
        $result = $this->fetcher->get($sitemapUrl, 'application/xml,text/xml;q=0.9,*/*;q=0.8');

        if (! $result->ok()) {
            return [];
        }

        $body = $result->body;

        if (str_starts_with($body, "\x1f\x8b")) {
            $body = @gzdecode($body) ?: '';
        }

        preg_match_all('#<loc>\s*(?:<!\[CDATA\[)?\s*(.*?)\s*(?:\]\]>)?\s*</loc>#is', $body, $matches);
        $locations = array_map(fn (string $loc) => html_entity_decode(trim($loc), ENT_QUOTES | ENT_XML1), $matches[1]);

        if (stripos($body, '<sitemapindex') === false) {
            return array_slice($locations, 0, $limit);
        }

        if ($depth >= 2) {
            return [];
        }

        $urls = [];

        foreach ($this->orderChildren($locations) as $child) {
            if (count($urls) >= $limit || $this->remainingRequests <= 0) {
                break;
            }

            array_push($urls, ...$this->urls($child, $limit - count($urls), $depth + 1));
        }

        return $urls;
    }

    /**
     * @param  list<string>  $children
     * @return list<string>
     */
    private function orderChildren(array $children): array
    {
        $preferred = [];
        $rest = [];

        foreach ($children as $child) {
            $name = strtolower(basename((string) parse_url($child, PHP_URL_PATH)));

            if ($this->contains($name, self::SKIPPED)) {
                continue;
            }

            if ($this->contains($name, self::PREFERRED)) {
                $preferred[] = $child;
            } else {
                $rest[] = $child;
            }
        }

        return [...$preferred, ...$rest];
    }

    /**
     * @param  list<string>  $needles
     */
    private function contains(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
