<?php

namespace App\Http\Controllers;

use App\Models\Discovery;
use App\Models\ScrapeJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CollectionControlController extends Controller
{
    public function pauseDiscovery(Request $request, Discovery $discovery): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $discovery);
        $discovery->forceFill(['paused_at' => $discovery->paused_at ?? now()])->save();

        return $this->respond($request, true);
    }

    public function resumeDiscovery(Request $request, Discovery $discovery): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $discovery);
        $discovery->forceFill(['paused_at' => null])->save();
        // Also resume a job paused from its product page.
        $discovery->scrapeJobs()->whereNotNull('paused_at')->update(['paused_at' => null]);

        return $this->respond($request, false);
    }

    public function pauseJob(Request $request, ScrapeJob $job): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $job);
        $job->forceFill(['paused_at' => $job->paused_at ?? now()])->save();

        return $this->respond($request, true);
    }

    public function resumeJob(Request $request, ScrapeJob $job): JsonResponse|RedirectResponse
    {
        Gate::authorize('update', $job);
        $job->forceFill(['paused_at' => null])->save();
        $job->discovery?->forceFill(['paused_at' => null])->save();

        return $this->respond($request, false);
    }

    private function respond(Request $request, bool $paused): JsonResponse|RedirectResponse
    {
        return $request->expectsJson() ? response()->json(['paused' => $paused]) : back();
    }
}
