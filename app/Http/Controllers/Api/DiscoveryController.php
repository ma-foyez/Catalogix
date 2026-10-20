<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ExtractRoutesRequest;
use App\Http\Requests\StoreDiscoveryRequest;
use App\Http\Resources\DiscoveryResource;
use App\Http\Resources\ScrapeJobResource;
use App\Models\Discovery;
use App\Services\DiscoveryService;
use App\Services\ExtractionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class DiscoveryController extends Controller
{
    /**
     * POST /api/discoveries {domain}: start route discovery.
     */
    public function store(StoreDiscoveryRequest $request, DiscoveryService $discoveries): JsonResponse
    {
        $discovery = $discoveries->start($request->user(), $request->validated('domain'));

        return (new DiscoveryResource($discovery))->response()->setStatusCode(202);
    }

    /**
     * GET /api/discoveries/{id}: status plus suggested routes with score, estimated count and sample thumbnails.
     */
    public function show(Discovery $discovery): DiscoveryResource
    {
        Gate::authorize('view', $discovery);

        return new DiscoveryResource($discovery->load(['site', 'routes']));
    }

    /**
     * POST /api/discoveries/{id}/extract {route_ids[]}: start extraction of the ticked routes.
     */
    public function extract(ExtractRoutesRequest $request, Discovery $discovery, ExtractionService $extraction): JsonResponse
    {
        $job = $extraction->start($request->user(), $discovery, $request->routeIds());

        return (new ScrapeJobResource($job->load(['discovery.site', 'targets.discoveredRoute'])))->response()->setStatusCode(202);
    }
}
