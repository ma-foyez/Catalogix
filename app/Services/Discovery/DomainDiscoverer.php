<?php

namespace App\Services\Discovery;

use App\Enums\JobStatus;
use App\Enums\RouteSource;
use App\Events\RoutesDiscovered;
use App\Exceptions\RenderFailedException;
use App\Http\Resources\DiscoveryResource;
use App\Models\DiscoveredRoute;
use App\Models\Discovery;
use App\Models\Site;
use App\Services\Crawling\CrawlDelay;
use App\Services\Crawling\FetchResult;
use App\Services\Crawling\PageFetcher;
use App\Services\Crawling\PageRenderer;
use App\Services\Crawling\PlatformDetector;
use App\Services\Crawling\RobotsPolicy;
use App\Services\Crawling\UrlTools;
use App\Support\Live;
use Illuminate\Support\Str;

/**
 * Domain → suggested product routes:
 * 1. sitemaps, 2. platform catalogue, 3. menu crawl (2 levels), 4. URL-pattern filter, 5. probe and score.
 */
final class DomainDiscoverer
{
    /** Rendering is slow, so only this many routes get a JavaScript retry. */
    private const int JS_RETRY_ROUTES = 5;

    private const int BROADCAST_EVERY = 5;

    /** @var array<string, FetchResult> pages fetched during the menu crawl, reused by the prober */
    private array $fetched = [];

    public function __construct(
        private readonly PageFetcher $fetcher,
        private readonly PageRenderer $renderer,
        private readonly RobotsPolicy $robots,
        private readonly PlatformDetector $platforms,
        private readonly SitemapReader $sitemaps,
        private readonly PlatformCatalogue $catalogue,
        private readonly MenuCrawler $menus,
        private readonly RouteClassifier $classifier,
        private readonly RouteProber $prober,
        private readonly CrawlDelay $delay,
    ) {}

    public function run(Discovery $discovery): void
    {
        $this->fetched = [];
        $site = $discovery->site;

        $discovery->forceFill(['status' => JobStatus::Running, 'started_at' => now(), 'error' => null])->save();
        $discovery->routes()->delete();
        $this->announce($discovery);

        $home = $this->fetchHome($site);

        if ($home->isBlocked()) {
            $site->forceFill(['is_blocked' => true, 'blocked_reason' => $home->blockedReason])->save();
            $this->finish($discovery, JobStatus::Failed, 'Blocked: '.$home->blockedReason);

            return;
        }

        if (! $home->ok()) {
            $this->finish($discovery, JobStatus::Failed, "Could not reach {$site->domain}: ".$home->failureMessage());

            return;
        }

        $base = $this->origin($home->finalUrl);
        $site->forceFill(['platform' => $this->platforms->detect($home->body), 'is_blocked' => false, 'blocked_reason' => null])->save();

        $candidates = new CandidateSet($site->domain, $this->classifier);

        $this->fromSitemaps($candidates, $base);
        $this->fromPlatform($candidates, $site, $base, $home->body);
        $this->fromMenus($candidates, $site, $base, $home);

        $candidates->add($base.'/', RouteSource::Menu, 'Home');

        $this->probeAll($discovery, $site, $candidates);

        $this->finish($discovery, JobStatus::Done);
    }

    private function fetchHome(Site $site): FetchResult
    {
        $home = $this->fetcher->get('https://'.$site->domain.'/');

        if (! $home->ok() && ! $home->isBlocked()) {
            $www = $this->fetcher->get('https://www.'.$site->domain.'/');
            $home = $www->ok() || $www->isBlocked() ? $www : $this->fetcher->get('http://'.$site->domain.'/');
        }

        return $home;
    }

    private function fromSitemaps(CandidateSet $candidates, string $base): void
    {
        $limit = (int) config('catalogix.discovery.max_sitemap_urls', 5000);
        $sources = array_unique([...$this->robots->sitemaps($base.'/'), $base.'/sitemap.xml', $base.'/sitemap_index.xml']);
        $seen = 0;

        foreach ($sources as $sitemap) {
            if ($seen >= $limit) {
                break;
            }

            foreach ($this->sitemaps->urls($sitemap, $limit - $seen) as $url) {
                $seen++;

                if ($this->classifier->isListing($url) || $this->classifier->isProduct($url)) {
                    $candidates->add($url, RouteSource::Sitemap);
                }
            }
        }
    }

    private function fromPlatform(CandidateSet $candidates, Site $site, string $base, string $homeHtml): void
    {
        $catalogue = $this->catalogue->read($site->platform, $base, $homeHtml);

        if ($catalogue['platform'] !== null && $catalogue['platform'] !== $site->platform) {
            $site->forceFill(['platform' => $catalogue['platform']])->save();
        }

        foreach ($catalogue['routes'] as $route) {
            $candidates->add($route['url'], RouteSource::PlatformApi, $route['name'], $route['count']);
        }
    }

    /**
     * Main navigation two levels deep; JavaScript menus are rendered by the Python helper first.
     */
    private function fromMenus(CandidateSet $candidates, Site $site, string $base, FetchResult $home): void
    {
        $links = $this->menus->links($home->body, $home->finalUrl, $site->domain);

        if (count($links) < 10) {
            try {
                $rendered = $this->renderer->render($home->finalUrl);
                $links = [
                    ...$links,
                    ...$this->menus->links($rendered->html, $rendered->finalUrl, $site->domain),
                    ...$this->menus->fromRenderedLinks($rendered->links, $site->domain),
                ];
                $site->forceFill(['needs_js' => true])->save();
            } catch (RenderFailedException) {
                // No Python helper: carry on with the static links.
            }
        }

        $followed = 0;
        $maxPages = (int) config('catalogix.discovery.max_menu_pages', 15);
        $depth = (int) config('catalogix.discovery.menu_depth', 2);

        foreach ($links as $link) {
            $candidates->add($link['url'], RouteSource::Menu, $link['text'] ?: null);
        }

        if ($depth < 2) {
            return;
        }

        foreach ($links as $link) {
            if ($followed >= $maxPages) {
                break;
            }

            $url = UrlTools::clean($link['url']);

            if ($this->classifier->isIgnored($url) || $this->classifier->isProduct($url) || UrlTools::clean($base.'/') === $url) {
                continue;
            }

            $followed++;
            $this->delay->pause();

            $page = $this->fetcher->get($url);
            $this->fetched[UrlTools::hash($url)] = $page;

            if (! $page->ok()) {
                continue;
            }

            foreach ($this->menus->links($page->body, $page->finalUrl, $site->domain) as $child) {
                $candidates->add($child['url'], RouteSource::Menu, $child['text'] ?: null);
            }
        }
    }

    private function probeAll(Discovery $discovery, Site $site, CandidateSet $candidates): void
    {
        $maxProbes = (int) config('catalogix.discovery.max_probes', 40);
        $probed = 0;
        $best = 0;
        $weak = [];

        foreach ($candidates->ranked() as $candidate) {
            if ($probed >= $maxProbes) {
                $this->store($discovery, $candidate, new ProbeResult(
                    score: $candidate->source === RouteSource::PlatformApi && $candidate->estProducts > 0 ? 70 : 0,
                    estProducts: $candidate->estProducts,
                ));

                continue;
            }

            $probed++;
            $key = UrlTools::hash($candidate->url);

            if (! isset($this->fetched[$key])) {
                $this->delay->pause();
            }

            $fetch = $this->fetched[$key] ?? $this->fetcher->get($candidate->url);
            $result = $this->prober->probe($fetch, $candidate->matchesListingPattern);

            if ($candidate->source === RouteSource::PlatformApi && $candidate->estProducts > 0 && $result->score < 70 && $result->blockedReason === null) {
                // The platform says this category has products even if the HTML shows few (JavaScript themes).
                $result = new ProbeResult(70, $result->productCount, max($candidate->estProducts, $result->estProducts), $result->sampleImages, $result->heading);
            }

            $route = $this->store($discovery, $candidate, $result);
            $best = max($best, $route->score);

            if ($route->score < 30 && $result->blockedReason === null) {
                $weak[] = $route;
            }

            if ($probed % self::BROADCAST_EVERY === 0) {
                $this->announce($discovery);
            }
        }

        if ($best < 30 && $weak !== []) {
            $this->retryWithJavaScript($site, $weak);
        }
    }

    /**
     * When no static page shows products the site probably builds them with JavaScript:
     * render the top few routes and score them again.
     *
     * @param  list<DiscoveredRoute>  $routes
     */
    private function retryWithJavaScript(Site $site, array $routes): void
    {
        usort($routes, fn (DiscoveredRoute $a, DiscoveredRoute $b) => $b->score <=> $a->score);

        foreach (array_slice($routes, 0, self::JS_RETRY_ROUTES) as $route) {
            try {
                $rendered = $this->renderer->render($route->url, (int) config('catalogix.render.scroll_steps', 6));
            } catch (RenderFailedException) {
                return;
            }

            $result = $this->prober->evaluate($rendered->html, $route->url, $rendered->jsonResponses, $this->classifier->isListing($route->url));

            if ($result->score > $route->score) {
                $route->forceFill([
                    'score' => $result->score,
                    'est_products' => max($route->est_products, $result->estProducts),
                    'sample_images' => $result->sampleImages,
                    'category_name' => $route->category_name ?? $this->categoryName($route->url, null, $result->heading),
                    'is_selected' => $result->score >= DiscoveredRoute::HIGH_SCORE,
                ])->save();

                $site->forceFill(['needs_js' => true])->save();
            }
        }
    }

    private function store(Discovery $discovery, CandidateRoute $candidate, ProbeResult $result): DiscoveredRoute
    {
        return DiscoveredRoute::updateOrCreate(
            ['discovery_id' => $discovery->id, 'url_hash' => UrlTools::hash($candidate->url)],
            [
                'url' => $candidate->url,
                'path' => Str::limit(UrlTools::pathWithQuery($candidate->url), 495, ''),
                'category_name' => $result->score > 0 ? $this->categoryName($candidate->url, $candidate->name, $result->heading) : null,
                'source' => $candidate->source,
                'score' => $result->score,
                'est_products' => max($result->estProducts, $candidate->estProducts),
                'sample_images' => $result->sampleImages,
                'is_selected' => $result->score >= DiscoveredRoute::HIGH_SCORE,
            ],
        );
    }

    private function categoryName(string $url, ?string $linkText, ?string $heading): string
    {
        if ($linkText !== null && $linkText !== '' && mb_strlen($linkText) <= 60) {
            return $linkText;
        }

        if ($heading !== null) {
            return $heading;
        }

        $segment = trim((string) parse_url($url, PHP_URL_PATH), '/');
        $segment = $segment === '' ? 'Home' : (string) last(explode('/', $segment));

        return Str::headline(urldecode($segment));
    }

    private function finish(Discovery $discovery, JobStatus $status, ?string $error = null): void
    {
        $discovery->forceFill([
            'status' => $status,
            'error' => $error,
            'routes_found' => $discovery->routes()->where('score', '>', 0)->count(),
            'finished_at' => now(),
        ])->save();

        $this->announce($discovery);
    }

    private function announce(Discovery $discovery): void
    {
        $discovery->load(['site', 'routes']);

        Live::send(new RoutesDiscovered($discovery->id, (new DiscoveryResource($discovery))->resolve()));
    }

    private function origin(string $url): string
    {
        $parts = parse_url($url);

        return ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');
    }
}
