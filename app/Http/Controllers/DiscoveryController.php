<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExtractRoutesRequest;
use App\Http\Requests\StoreDiscoveryRequest;
use App\Http\Resources\DiscoveryResource;
use App\Http\Resources\SiteRunResource;
use App\Models\Discovery;
use App\Services\DiscoveryService;
use App\Services\ExtractionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class DiscoveryController extends Controller
{
    /**
     * The one page most people need: paste websites, watch each one go from "finding" to
     * "ready", then view or download its products.
     */
    public function index(Request $request): Response
    {
        $runs = $request->user()->discoveries()
            ->with(['site', 'latestJob' => fn ($query) => $query->withCount([
                'targets',
                'targets as finished_targets_count' => fn ($targets) => $targets->whereIn('status', ['done', 'blocked', 'failed']),
            ])])
            ->latest()
            ->paginate(15);

        return Inertia::render('discover/index', [
            'runs' => SiteRunResource::collection($runs),
        ]);
    }

    /**
     * One or many websites; each is discovered and then extracted without further clicks.
     */
    public function store(StoreDiscoveryRequest $request, DiscoveryService $discoveries): RedirectResponse
    {
        ['started' => $started, 'skipped' => $skipped] = $discoveries->startMany($request->user(), $request->domains());

        $message = match (true) {
            $started === [] => (count($skipped) === 1 ? "{$skipped[0]} is" : 'These websites are').' already being collected.',
            count($started) === 1 => "Started {$started[0]->site->domain}. Products appear here as they are collected.",
            default => 'Started '.count($started).' websites. Products appear here as they are collected.',
        };

        Inertia::flash('toast', ['type' => $started === [] ? 'info' : 'success', 'message' => $message]);

        return to_route('discover');
    }

    /**
     * "Refresh": collect the same pages again when a site has been collected before,
     * otherwise look for product pages again.
     */
    public function refresh(Discovery $discovery, DiscoveryService $discoveries, ExtractionService $extraction): RedirectResponse
    {
        Gate::authorize('update', $discovery);

        $job = $discovery->latestJob;

        if (($job !== null && ! $job->status->isFinished()) || ! $discovery->status->isFinished()) {
            return back();
        }

        if ($job !== null && $job->total_products > 0) {
            $extraction->rerun($job);
        } else {
            $discovery->forceFill(['auto_extract' => true])->save();
            $discoveries->restart($discovery);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => "Refreshing {$discovery->site->domain}."]);

        return back();
    }

    /**
     * The scored checklist of suggested routes; it fills in live while discovery runs.
     */
    public function show(Discovery $discovery): Response
    {
        Gate::authorize('view', $discovery);

        return Inertia::render('discover/show', [
            'discovery' => (new DiscoveryResource($discovery->load(['site', 'routes'])))->resolve(),
        ]);
    }

    public function extract(ExtractRoutesRequest $request, Discovery $discovery, ExtractionService $extraction): RedirectResponse
    {
        $job = $extraction->start($request->user(), $discovery, $request->routeIds());

        return to_route('jobs.show', $job);
    }

    public function rediscover(Discovery $discovery, DiscoveryService $discoveries): RedirectResponse
    {
        Gate::authorize('update', $discovery);

        $discoveries->restart($discovery);

        return to_route('discoveries.show', $discovery);
    }

    public function destroy(Discovery $discovery): RedirectResponse
    {
        Gate::authorize('delete', $discovery);

        $discovery->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Discovery deleted.']);

        return to_route('discover');
    }
}
