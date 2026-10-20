<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExtractRoutesRequest;
use App\Http\Requests\StoreDiscoveryRequest;
use App\Http\Resources\DiscoveryResource;
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
     * The domain input plus the user's recent discoveries.
     */
    public function index(Request $request): Response
    {
        $recent = $request->user()->discoveries()->with('site')->latest()->limit(8)->get();

        return Inertia::render('discover/index', [
            'recent' => DiscoveryResource::collection($recent),
        ]);
    }

    public function store(StoreDiscoveryRequest $request, DiscoveryService $discoveries): RedirectResponse
    {
        $discovery = $discoveries->start($request->user(), $request->validated('domain'));

        return to_route('discoveries.show', $discovery);
    }

    /**
     * The scored checklist of suggested routes; it fills in live while discovery runs.
     */
    public function show(Discovery $discovery): Response
    {
        Gate::authorize('view', $discovery);

        return Inertia::render('discover/show', [
            'discovery' => new DiscoveryResource($discovery->load(['site', 'routes'])),
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
