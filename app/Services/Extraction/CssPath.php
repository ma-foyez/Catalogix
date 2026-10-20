<?php

namespace App\Services\Extraction;

use DOMElement;

/**
 * Builds stable CSS selectors (tag + meaningful classes, no :nth-child) so a recipe keeps
 * working on page 2, 3 and on the next run.
 */
final class CssPath
{
    private const array UNSTABLE_CLASS_WORDS = ['active', 'selected', 'hover', 'focus', 'show', 'open', 'lazy', 'loaded', 'loading', 'visible', 'hidden', 'first', 'last', 'odd', 'even', 'current'];

    /**
     * Stable classes of an element, sorted, at most $limit.
     *
     * @return list<string>
     */
    public static function stableClasses(DOMElement $element, int $limit = 2): array
    {
        $classes = preg_split('/\s+/', trim($element->getAttribute('class'))) ?: [];

        $stable = array_values(array_filter($classes, function (string $class): bool {
            if (! preg_match('/^[a-zA-Z_][\w-]{1,40}$/', $class) || preg_match('/\d{3,}/', $class)) {
                return false;
            }

            foreach (self::UNSTABLE_CLASS_WORDS as $word) {
                if (stripos($class, $word) !== false) {
                    return false;
                }
            }

            return true;
        }));

        sort($stable);

        return array_slice($stable, 0, $limit);
    }

    /**
     * "div.product-card" style selector for one element.
     */
    public static function segment(DOMElement $element): string
    {
        $classes = self::stableClasses($element);

        return strtolower($element->tagName).($classes === [] ? '' : '.'.implode('.', $classes));
    }

    /**
     * Selector from the document (or nearest id) down to the element.
     */
    public static function absolute(DOMElement $element): string
    {
        $segments = [];
        $node = $element;

        while ($node instanceof DOMElement && ! in_array(strtolower($node->tagName), ['body', 'html'], true)) {
            $id = $node->getAttribute('id');

            if ($id !== '' && preg_match('/^[a-zA-Z][\w-]{0,60}$/', $id) && ! preg_match('/\d{3,}/', $id)) {
                array_unshift($segments, '#'.$id);

                break;
            }

            array_unshift($segments, self::segment($node));
            $node = $node->parentNode;

            if (count($segments) >= 6) {
                break;
            }
        }

        return implode(' > ', $segments);
    }

    /**
     * Selector for $element relative to an ancestor (descendant combinators, so wrappers may vary).
     */
    public static function relative(DOMElement $element, DOMElement $ancestor): ?string
    {
        if ($element === $ancestor) {
            return null;
        }

        $segments = [];
        $node = $element;

        while ($node instanceof DOMElement && $node !== $ancestor) {
            if ($node === $element || self::stableClasses($node) !== []) {
                array_unshift($segments, self::segment($node));
            }

            $node = $node->parentNode;

            if (count($segments) >= 4) {
                break;
            }
        }

        return $node === $ancestor || count($segments) >= 4 ? implode(' ', $segments) : null;
    }
}
