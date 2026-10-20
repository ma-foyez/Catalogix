<?php

namespace App\Services\Extraction;

use App\Enums\ExtractionStrategy;
use App\Enums\Platform;
use App\Models\ScrapeTarget;
use App\Models\Site;
use App\Services\Crawling\PageFetcher;
use App\Services\Crawling\PageRenderer;
use App\Services\Crawling\PlatformDetector;

/**
 * Extracts one page of one route: fetch (robots + block checks), run the ladder, save products,
 * remember what worked on the site, and work out the next page.
 */
final class PageProcessor
{
    public function __construct(
        private readonly PageFetcher $fetcher,
        private readonly PageRenderer $renderer,
        private readonly PlatformDetector $platforms,
        private readonly ExtractionLadder $ladder,
        private readonly Paginator $paginator,
        private readonly ProductUpserter $upserter,
    ) {}

    public function process(ScrapeTarget $target, int $page, string $url, ?ExtractionStrategy $locked = null): PageOutcome
    {
        $site = $target->site();
        $fetch = $this->fetcher->get($url);

        if ($fetch->isBlocked()) {
            $site->forceFill(['is_blocked' => true, 'blocked_reason' => $fetch->blockedReason])->save();

            return PageOutcome::blocked((string) $fetch->blockedReason);
        }

        if (! $fetch->ok() && ($page > 1 || ! $site->needs_js)) {
            return $page > 1 ? PageOutcome::done() : PageOutcome::failed('Could not load the page: '.$fetch->failureMessage());
        }

        $html = $fetch->ok() ? $fetch->body : '';

        if ($page === 1 && in_array($site->platform, [Platform::Unknown, Platform::Custom], true) && $html !== '') {
            $site->platform = $this->platforms->detect($html);
        }

        $context = new PageContext($site, $url, $page, $html, $target->categoryName(), $this->renderer);
        $outcome = $this->ladder->run($context, $page > 1 ? $locked : null);

        if ($outcome === null) {
            $site->save();

            if ($page > 1) {
                return PageOutcome::done();
            }

            $hint = $context->renderError !== null ? ' The JavaScript renderer also failed: '.$context->renderError : '';

            return PageOutcome::failed('No products found on this page. An admin can teach Catalogix this layout with the visual recipe picker.'.$hint);
        }

        if ($page === 1) {
            $this->rememberWinner($site, $outcome);
        }

        $saved = $this->upserter->save($target, $site, $outcome->products);

        if ($page > 1 && $saved->newForTarget === 0) {
            return PageOutcome::done($outcome->strategy, $saved);
        }

        $maxPages = min($target->scrapeJob->max_pages, $this->maxPages());
        $nextUrl = $page < $maxPages ? $this->paginator->next($context, $outcome) : null;

        return new PageOutcome(
            status: $nextUrl !== null ? PageOutcome::NEXT : PageOutcome::DONE,
            strategy: $outcome->strategy,
            saved: $saved,
            nextUrl: $nextUrl,
        );
    }

    private function rememberWinner(Site $site, LadderOutcome $outcome): void
    {
        $attributes = [
            'strategy' => $outcome->strategy,
            'last_success_at' => now(),
            'is_blocked' => false,
            'blocked_reason' => null,
        ];

        if ($outcome->result->usedRender) {
            $attributes['needs_js'] = true;
        }

        if ($outcome->strategy === ExtractionStrategy::Recipe) {
            $attributes['recipe_valid'] = true;
        }

        if ($outcome->strategy === ExtractionStrategy::CardDetection && $outcome->result->recipe !== null) {
            $attributes['selectors'] = $outcome->result->recipe;
            $attributes['recipe_valid'] = true;
        }

        $site->forceFill($attributes)->save();
    }

    private function maxPages(): int
    {
        return max(1, (int) config('catalogix.max_pages', 30));
    }
}
