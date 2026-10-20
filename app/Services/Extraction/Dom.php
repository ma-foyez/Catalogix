<?php

namespace App\Services\Extraction;

use DOMElement;
use DOMNode;
use Symfony\Component\DomCrawler\Crawler;
use Throwable;

/**
 * Small helpers over Symfony DomCrawler for the strategies.
 */
final class Dom
{
    private const array SKIPPED_TAGS = ['script', 'style', 'noscript', 'template', 'svg'];

    public static function crawler(string $html, string $uri): Crawler
    {
        return new Crawler($html, $uri);
    }

    /**
     * Visible text of a node with whitespace collapsed; script, style and svg contents are ignored.
     */
    public static function text(DOMNode $node): string
    {
        return trim(preg_replace('/\s+/u', ' ', self::collectText($node)) ?? '');
    }

    /**
     * The first element inside $context matching a CSS selector, or null for an empty or invalid selector.
     */
    public static function first(DOMElement $context, ?string $selector): ?DOMElement
    {
        return self::all($context, $selector)[0] ?? null;
    }

    /**
     * @return list<DOMElement>
     */
    public static function all(DOMNode $context, ?string $selector): array
    {
        if ($selector === null || trim($selector) === '') {
            return [];
        }

        try {
            $elements = [];

            foreach ((new Crawler($context))->filter($selector) as $node) {
                if ($node instanceof DOMElement) {
                    $elements[] = $node;
                }
            }

            return $elements;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Best image URL on an element: lazy-load attributes first, then the widest srcset entry, then src.
     */
    public static function imageSource(DOMElement $img): ?string
    {
        foreach (['data-src', 'data-lazy-src', 'data-original', 'data-lazy', 'data-image', 'data-zoom-image'] as $attribute) {
            $value = trim($img->getAttribute($attribute));

            if ($value !== '' && ! str_starts_with($value, 'data:')) {
                return $value;
            }
        }

        foreach (['srcset', 'data-srcset'] as $attribute) {
            $value = trim($img->getAttribute($attribute));

            if ($value !== '' && ($largest = ProductNormalizer::largestFromSrcset($value)) !== null) {
                return $largest;
            }
        }

        $src = trim($img->getAttribute('src'));

        return $src !== '' && ! str_starts_with($src, 'data:') ? $src : null;
    }

    private static function collectText(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) {
            return $node->textContent.' ';
        }

        if ($node instanceof DOMElement && in_array(strtolower($node->tagName), self::SKIPPED_TAGS, true)) {
            return ' ';
        }

        $text = '';

        foreach ($node->childNodes as $child) {
            $text .= self::collectText($child);
        }

        return $text;
    }
}
