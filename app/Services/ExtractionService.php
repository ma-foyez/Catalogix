<?php

namespace App\Services;

use App\Enums\ImageStatus;
use App\Enums\JobStatus;
use App\Enums\TargetStatus;
use App\Jobs\DownloadImageJob;
use App\Jobs\ProcessPageJob;
use App\Models\DiscoveredRoute;
use App\Models\Discovery;
use App\Models\ProductImage;
use App\Models\ScrapeJob;
use App\Models\User;
use App\Services\Crawling\UrlTools;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ExtractionService
{
    /**
     * Start extracting the ticked routes of a discovery. Ticks are saved so a re-run uses the same routes.
     *
     * @param  list<int>  $routeIds
     */
    public function start(User $user, Discovery $discovery, array $routeIds, ?int $maxProducts = null): ScrapeJob
    {
        $routes = $discovery->routes()->whereIn('id', $routeIds)->get();

        if ($routes->isEmpty()) {
            throw new InvalidArgumentException('Tick at least one route to extract.');
        }

        $job = DB::transaction(function () use ($user, $discovery, $routes, $maxProducts): ScrapeJob {
            $discovery->routes()->update(['is_selected' => false]);
            DiscoveredRoute::whereIn('id', $routes->pluck('id'))->update(['is_selected' => true]);

            $job = ScrapeJob::create([
                'user_id' => $user->id,
                'discovery_id' => $discovery->id,
                'status' => JobStatus::Queued,
                'max_products' => $maxProducts ?? $discovery->max_products,
                'total_routes' => $routes->count(),
            ]);

            foreach ($routes as $route) {
                $job->targets()->create([
                    'discovered_route_id' => $route->id,
                    'url' => $route->url,
                    'status' => TargetStatus::Queued,
                ]);
            }

            return $job;
        });

        foreach ($job->targets as $target) {
            ProcessPageJob::dispatch($target->id, $target->url);
        }

        return $job;
    }

    /**
     * Extract what discovery recommends without asking: the high-score routes (the home page only
     * when nothing else qualifies), falling back to routes that showed at least one product.
     * Returns null when no route showed products.
     */
    public function startRecommended(Discovery $discovery): ?ScrapeJob
    {
        $routes = $discovery->routes()->get();
        $limit = (int) config('catalogix.auto_extract.max_routes', 25);

        $pick = fn (int $minScore, bool $withHome) => $routes
            ->filter(fn (DiscoveredRoute $route) => $route->score >= $minScore && ($withHome || ! UrlTools::isHomePage($route->url)))
            ->take($limit);

        $chosen = $pick(DiscoveredRoute::HIGH_SCORE, false);

        foreach ([[30, false], [30, true]] as [$minScore, $withHome]) {
            if ($chosen->isEmpty()) {
                $chosen = $pick($minScore, $withHome);
            }
        }

        if ($chosen->isEmpty()) {
            return null;
        }

        return $this->start($discovery->user, $discovery, array_values($chosen->pluck('id')->map(fn ($id) => (int) $id)->all()));
    }

    /**
     * Run the same routes again; products update in place thanks to the (site_id, hash) upsert.
     */
    public function rerun(ScrapeJob $job): ScrapeJob
    {
        $discovery = $job->discovery ?? throw new InvalidArgumentException('This job has no discovery to re-run.');

        $routeIds = array_values($job->targets()
            ->whereNotNull('discovered_route_id')
            ->pluck('discovered_route_id')
            ->map(fn ($id) => (int) $id)
            ->all());

        return $this->start($job->user, $discovery, $routeIds, $job->max_products);
    }

    /**
     * Queue failed image downloads of a job again. Returns how many were queued.
     */
    public function retryImages(ScrapeJob $job): int
    {
        $domain = $job->discovery->site->domain ?? 'unknown';

        $images = ProductImage::query()
            ->where('status', ImageStatus::Failed)
            ->whereHas('product.scrapeTargets', fn ($query) => $query->where('scrape_targets.scrape_job_id', $job->id))
            ->get();

        foreach ($images as $image) {
            $image->forceFill(['status' => ImageStatus::Pending, 'error' => null, 'attempts' => 0])->save();
            DownloadImageJob::dispatch($image->id, $domain);
        }

        return $images->count();
    }
}
