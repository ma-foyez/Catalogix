<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExportRequest;
use App\Http\Resources\ExportResource;
use App\Models\Export;
use App\Services\ExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ExportController extends Controller
{
    /**
     * POST /api/exports {job_id, scope, format, embed_images, layout}.
     */
    public function store(StoreExportRequest $request, ExportService $exports): JsonResponse
    {
        $export = $exports->create($request->user(), $request->validated());

        return (new ExportResource($export))->response()->setStatusCode(202);
    }

    /**
     * GET /api/exports/{id}: status, file size and, when ready, a signed download URL.
     */
    public function show(Export $export): ExportResource
    {
        Gate::authorize('view', $export);

        return new ExportResource($export);
    }
}
