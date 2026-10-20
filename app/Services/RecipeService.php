<?php

namespace App\Services;

use App\Enums\ExtractionStrategy;
use App\Exceptions\RenderFailedException;
use App\Models\Site;
use App\Services\Crawling\PageFetcher;
use App\Services\Crawling\PageRenderer;
use App\Services\Extraction\ProductNormalizer;
use App\Services\Extraction\RecipeApplier;
use RuntimeException;

/**
 * Backs the visual recipe picker: serves a script-free copy of the page for the picker frame,
 * previews selectors against it, and saves the recipe on the site.
 */
final class RecipeService
{
    public function __construct(
        private readonly PageFetcher $fetcher,
        private readonly PageRenderer $renderer,
        private readonly RecipeApplier $applier,
        private readonly ProductNormalizer $normalizer,
    ) {}

    /**
     * The page HTML exactly as extraction will see it (rendered for JavaScript sites).
     */
    public function source(Site $site, string $url): string
    {
        if ($site->needs_js) {
            try {
                return $this->renderer->render($url)->html;
            } catch (RenderFailedException) {
                // Fall back to the static HTML below.
            }
        }

        $result = $this->fetcher->get($url);

        if (! $result->ok()) {
            throw new RuntimeException('Could not load the page: '.$result->failureMessage());
        }

        return $result->body;
    }

    /**
     * HTML for the sandboxed picker frame: page scripts, inline handlers and refreshes removed,
     * a <base> so relative images load, and the picker script added.
     */
    public function frame(Site $site, string $url, string $pickerScriptUrl): string
    {
        $html = $this->source($site, $url);

        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html) ?? $html;
        $html = preg_replace('#<noscript\b[^>]*>(.*?)</noscript>#is', '$1', $html) ?? $html;
        $html = preg_replace('#<meta[^>]+http-equiv=["\']?refresh[^>]*>#i', '', $html) ?? $html;
        $html = preg_replace('#\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html) ?? $html;
        $html = preg_replace('#<base\b[^>]*>#i', '', $html) ?? $html;

        $head = '<base href="'.e($url).'"><script src="'.e($pickerScriptUrl).'" defer></script>';

        if (preg_match('#<head\b[^>]*>#i', $html)) {
            return preg_replace('#<head\b[^>]*>#i', '$0'.$head, $html, 1) ?? $html;
        }

        return '<!doctype html><html><head>'.$head.'</head><body>'.$html.'</body></html>';
    }

    /**
     * @param  array<string, string|null>  $selectors
     * @return array{cards: int, products: list<array<string, mixed>>, next: string|null}
     */
    public function preview(Site $site, string $url, array $selectors): array
    {
        $result = $this->applier->apply($this->source($site, $url), $url, $selectors);
        $products = [];

        foreach ($result['products'] as $raw) {
            if (($product = $this->normalizer->normalize($raw, $url)) !== null) {
                $products[] = [
                    'name' => $product['name'],
                    'price' => $product['price'],
                    'sale_price' => $product['sale_price'],
                    'image_url' => $product['image_url'],
                    'product_url' => $product['product_url'],
                ];
            }
        }

        return ['cards' => $result['cards'], 'products' => $products, 'next' => $result['next']];
    }

    /**
     * @param  array<string, string|null>  $selectors
     */
    public function save(Site $site, array $selectors): Site
    {
        $site->forceFill([
            'selectors' => $selectors,
            'recipe_valid' => true,
            'strategy' => ExtractionStrategy::Recipe,
        ])->save();

        return $site;
    }
}
