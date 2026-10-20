<?php

namespace App\Services\Extraction;

use App\Enums\ExtractionStrategy;
use App\Services\Crawling\UrlTools;
use DOMElement;

/**
 * Finds the next listing page: the recipe's Next selector, rel=next, common "Next" links,
 * then ?page=N. Platform APIs paginate by page number on the same listing URL.
 */
final class Paginator
{
    private const array NEXT_SELECTORS = [
        'link[rel~="next"][href]',
        'a[rel~="next"][href]',
        'a.next.page-numbers',
        '.pagination a.next',
        '.pagination li.next a',
        'li.next > a',
        'a.next',
        'a[aria-label="Next"]',
        'a[aria-label="Next page"]',
        'a[aria-label="next"]',
        'a[title="Next"]',
        'a[title="Next Page"]',
        '.pager-next a',
    ];

    private const array NEXT_TEXTS = ['next', 'next ›', 'next »', 'next page', '›', '»', '>', 'পরবর্তী'];

    /** A page this full is probably paginated even without a Next link. */
    private const int PAGED_LISTING_SIZE = 10;

    public function next(PageContext $context, LadderOutcome $outcome): ?string
    {
        if ($outcome->strategy === ExtractionStrategy::PlatformApi) {
            return $outcome->result->hasMorePages ? $context->url : null;
        }

        $candidates = [$outcome->result->nextUrl];

        $html = $outcome->result->usedRender && $context->rendered() !== null ? $context->rendered()->html : $context->html;
        $candidates[] = $this->fromHtml($html, $context->url);
        $candidates[] = $this->fromPageParameter($context->url, $context->page, count($outcome->products));

        $current = UrlTools::clean($context->url);
        $domain = $context->site->domain;

        foreach ($candidates as $candidate) {
            if ($candidate !== null && UrlTools::isSameSite($candidate, $domain) && UrlTools::clean($candidate) !== $current) {
                return $candidate;
            }
        }

        return null;
    }

    public function fromHtml(string $html, string $url): ?string
    {
        $root = Dom::crawler($html, $url)->getNode(0);

        if (! $root instanceof DOMElement) {
            return null;
        }

        foreach (self::NEXT_SELECTORS as $selector) {
            foreach (Dom::all($root, $selector) as $element) {
                if (($href = UrlTools::absolute($element->getAttribute('href'), $url)) !== null) {
                    return $href;
                }
            }
        }

        foreach (Dom::all($root, 'a[href]') as $anchor) {
            $text = mb_strtolower(Dom::text($anchor) ?: trim($anchor->getAttribute('aria-label')));

            if (in_array($text, self::NEXT_TEXTS, true) && ($href = UrlTools::absolute($anchor->getAttribute('href'), $url)) !== null) {
                return $href;
            }
        }

        return null;
    }

    /**
     * ?page=N+1 when the URL already pages, /page/N/ paths, or a full first page without a Next link.
     */
    public function fromPageParameter(string $url, int $page, int $productsOnPage): ?string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        if (isset($query['page']) && is_numeric($query['page'])) {
            return UrlTools::withQuery($url, ['page' => (int) $query['page'] + 1]);
        }

        $path = (string) parse_url($url, PHP_URL_PATH);

        if (preg_match('#/page/(\d+)/?$#', $path, $match)) {
            return str_replace($match[0], '/page/'.((int) $match[1] + 1).'/', $url);
        }

        if ($page === 1 && $productsOnPage >= self::PAGED_LISTING_SIZE) {
            return UrlTools::withQuery($url, ['page' => 2]);
        }

        return null;
    }
}
