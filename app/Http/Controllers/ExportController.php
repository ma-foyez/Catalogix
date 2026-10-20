<?php

namespace App\Http\Controllers;

use App\Enums\ExportFormat;
use App\Enums\ExportLayout;
use App\Enums\ExportScope;
use App\Http\Requests\QuickExportRequest;
use App\Http\Requests\StoreExportRequest;
use App\Http\Resources\ExportResource;
use App\Models\Export;
use App\Models\ScrapeJob;
use App\Services\ExportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function index(Request $request): Response
    {
        $exports = $request->user()->exports()->with('scrapeJob.discovery.site')->latest()->paginate(20);

        return Inertia::render('exports/index', [
            'exports' => ExportResource::collection($exports),
        ]);
    }

    public function store(StoreExportRequest $request, ExportService $exports): RedirectResponse
    {
        $exports->create($request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Export started. It will appear under Exports when ready.']);

        return back();
    }

    /**
     * One click from a site card or the products page: Excel with pictures, one sheet. The page
     * then polls status() and downloads the file as soon as it is ready.
     */
    public function quick(QuickExportRequest $request, ScrapeJob $job, ExportService $exports): JsonResponse
    {
        Gate::authorize('view', $job);

        $export = $exports->create($request->user(), [
            ...$request->validated(),
            'scope' => ExportScope::Site->value,
            'job_id' => $job->id,
            'format' => ExportFormat::XlsxImages->value,
            'layout' => ExportLayout::Single->value,
        ]);

        return (new ExportResource($export))->response()->setStatusCode(202);
    }

    public function status(Export $export): ExportResource
    {
        Gate::authorize('view', $export);

        return new ExportResource($export);
    }

    /**
     * Signed URL, valid for 24 hours: the signature is the permission, so API clients can use it too.
     */
    public function download(Export $export): StreamedResponse
    {
        abort_unless($export->isDownloadable(), 410, 'This export has expired or is not ready.');

        $disk = Storage::disk((string) config('catalogix.exports.disk', 'local'));

        abort_unless($disk->exists((string) $export->file_path), 410, 'The export file is no longer available.');

        return $disk->download((string) $export->file_path, $export->fileName());
    }

    public function destroy(Export $export): RedirectResponse
    {
        Gate::authorize('delete', $export);

        if ($export->file_path !== null) {
            Storage::disk((string) config('catalogix.exports.disk', 'local'))->delete($export->file_path);
        }

        $export->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Export deleted.']);

        return back();
    }
}
