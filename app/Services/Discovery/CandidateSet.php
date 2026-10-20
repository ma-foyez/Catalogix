<?php

namespace App\Services\Discovery;

use App\Enums\RouteSource;
use App\Services\Crawling\UrlTools;

/**
 * Collects candidate routes from every source, deduplicated by clean URL.
 */
final class CandidateSet
{
    private const array SOURCE_PRIORITY = ['platform_api' => 4, 'menu' => 3, 'sitemap' => 2, 'pattern' => 1];

    /** @var array<string, CandidateRoute> */
    private array $items = [];

    public function __construct(
        private readonly string $domain,
        private readonly RouteClassifier $classifier,
    ) {}

    public function add(string $url, RouteSource $source, ?string $name = null, int $estProducts = 0): void
    {
        if (! UrlTools::isSameSite($url, $this->domain) || $this->classifier->isIgnored($url)) {
            return;
        }

        $url = UrlTools::clean($url);

        if ($this->classifier->isProduct($url)) {
            if (($parent = $this->classifier->parentListing($url)) !== null) {
                $this->add($parent, RouteSource::Pattern);
                $this->items[UrlTools::hash($parent)]->estProducts++;
            }

            return;
        }

        $name = $name !== null ? trim(preg_replace('/\s+/u', ' ', $name) ?? '') : null;
        $name = $name === '' ? null : mb_substr((string) $name, 0, 120);

        $key = UrlTools::hash($url);
        $existing = $this->items[$key] ?? null;

        if ($existing === null) {
            $this->items[$key] = new CandidateRoute($url, $source, $name, $estProducts, $this->classifier->isListing($url));

            return;
        }

        if (self::SOURCE_PRIORITY[$source->value] > self::SOURCE_PRIORITY[$existing->source->value]) {
            $existing->source = $source;
            $existing->name = $name ?? $existing->name;
        } else {
            $existing->name ??= $name;
        }

        $existing->estProducts = max($existing->estProducts, $estProducts);
    }

    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @return list<CandidateRoute>
     */
    public function ranked(): array
    {
        $items = array_values($this->items);

        usort($items, fn (CandidateRoute $a, CandidateRoute $b) => [$b->rank(), $b->estProducts, strlen($a->url)] <=> [$a->rank(), $a->estProducts, strlen($b->url)]);

        return $items;
    }
}
