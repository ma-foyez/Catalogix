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
     * POST /api/discoveries {domain} or {domains: [...]}: start route discovery. With
     * auto_extract=true the recommended routes are extracted as soon as discovery finishes.
     */
    public function store(StoreDiscoveryRequest $request, DiscoveryService $discoveries): JsonResponse
    {
        $domains = $request->domains();
        $auto = $request->boolean('auto_extract');
        $maxProducts = $request->filled('max_products') ? $request->integer('max_products') : null;

        if (count($domains) === 1) {
            return (new DiscoveryResource($discoveries->start($request->user(), $domains[0], $auto, $maxProducts)))->response()->setStatusCode(202);
        }

        $started = $discoveries->startMany($request->user(), $domains, $auto, $maxProducts)['started'];

        return DiscoveryResource::collection($started)->response()->setStatusCode(202);
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
        $job = $extraction->start($request->user(), $discovery, $request->routeIds(), $request->filled('max_products') ? $request->integer('max_products') : null);

        return (new ScrapeJobResource($job->load(['discovery.site', 'targets.discoveredRoute'])))->response()->setStatusCode(202);
    }
}
