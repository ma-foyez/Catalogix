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
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ExtractionService
{
    /**
     * Start extracting the ticked routes of a discovery. Ticks are saved so a re-run uses the same routes.
     *
     * @param  list<int>  $routeIds
     */
    public function start(User $user, Discovery $discovery, array $routeIds): ScrapeJob
    {
        $routes = $discovery->routes()->whereIn('id', $routeIds)->get();

        if ($routes->isEmpty()) {
            throw new InvalidArgumentException('Tick at least one route to extract.');
        }

        $job = DB::transaction(function () use ($user, $discovery, $routes): ScrapeJob {
            $discovery->routes()->update(['is_selected' => false]);
            DiscoveredRoute::whereIn('id', $routes->pluck('id'))->update(['is_selected' => true]);

            $job = ScrapeJob::create([
                'user_id' => $user->id,
                'discovery_id' => $discovery->id,
                'status' => JobStatus::Queued,
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
     * Run the same routes again; products update in place thanks to the (site_id, hash) upsert.
     */
    public function rerun(ScrapeJob $job): ScrapeJob
    {
        $discovery = $job->discovery ?? throw new InvalidArgumentException('This job has no discovery to re-run.');

        $routeIds = $job->targets()->whereNotNull('discovered_route_id')->pluck('discovered_route_id')->all();

        return $this->start($job->user, $discovery, $routeIds);
    }

    /**
     * Queue failed image downloads of a job again. Returns how many were queued.
     */
    public function retryImages(ScrapeJob $job): int
    {
        $domain = $job->discovery?->site?->domain ?? 'unknown';

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
