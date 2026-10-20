<?php

namespace App\Services\Extraction;

use App\Exceptions\RenderFailedException;
use App\Models\Site;
use App\Services\Crawling\PageRenderer;
use App\Services\Crawling\RenderResult;

/**
 * Everything a strategy may look at for one page: the static HTML, and a rendered copy from
 * the Python helper that is produced at most once, only when a strategy asks for it.
 */
final class PageContext
{
    private ?RenderResult $rendered = null;

    private bool $renderAttempted = false;

    public ?string $renderError = null;

    public function __construct(
        public readonly Site $site,
        public readonly string $url,
        public readonly int $page,
        public readonly string $html,
        public readonly ?string $category,
        private readonly ?PageRenderer $renderer = null,
    ) {}

    /**
     * Render the page with the Python helper (scrolling for infinite-scroll pages). Null when rendering fails.
     */
    public function rendered(): ?RenderResult
    {
        if ($this->renderAttempted || $this->renderer === null) {
            return $this->rendered;
        }

        $this->renderAttempted = true;

        try {
            $this->rendered = $this->renderer->render($this->url, (int) config('catalogix.render.scroll_steps', 6));
        } catch (RenderFailedException $e) {
            $this->renderError = $e->getMessage();
        }

        return $this->rendered;
    }

    public function hasRendered(): bool
    {
        return $this->rendered !== null;
    }

    /**
     * The HTML parsing strategies should read: the rendered DOM for sites known to need JavaScript.
     */
    public function html(): string
    {
        if ($this->site->needs_js && ($rendered = $this->rendered()) !== null) {
            return $rendered->html;
        }

        return $this->html;
    }
}
