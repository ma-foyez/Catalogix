/*
 * Catalogix visual recipe picker — runs inside the sandboxed picker frame.
 *
 * The parent page tells it which field to pick ("name", "price", "sale_price", "image", "next"),
 * the admin clicks one example on the page, and this script reports a CSS path back.
 * Once name, price and image are picked, "build" turns the examples into a recipe:
 * the repeated product card plus field selectors relative to that card.
 */
(function () {
    'use strict';

    var UNSTABLE = ['active', 'selected', 'hover', 'focus', 'show', 'open', 'lazy', 'loaded', 'loading', 'visible', 'hidden', 'first', 'last', 'odd', 'even', 'current'];
    var COLORS = { name: '#2563eb', price: '#16a34a', sale_price: '#0d9488', image: '#d97706', next: '#9333ea', card: '#e11d48' };

    var currentField = null;
    var picked = {};
    var hovered = null;

    var style = document.createElement('style');
    style.textContent =
        '[data-cx-hover]{outline:2px dashed #2563eb !important;outline-offset:2px !important;cursor:crosshair !important}' +
        '[data-cx-picked]{outline:3px solid var(--cx-color,#2563eb) !important;outline-offset:2px !important}' +
        '[data-cx-match]{box-shadow:inset 0 0 0 3px rgba(225,29,72,.75) !important}';
    document.addEventListener('DOMContentLoaded', function () {
        document.head.appendChild(style);
        send({ type: 'catalogix:ready' });
    });

    function send(message) {
        window.parent.postMessage(message, '*');
    }

    function stableClasses(el) {
        return Array.prototype.filter
            .call(el.classList, function (c) {
                if (!/^[a-zA-Z_][\w-]{1,40}$/.test(c) || /\d{3,}/.test(c)) return false;
                var lower = c.toLowerCase();
                return !UNSTABLE.some(function (w) { return lower.indexOf(w) !== -1; });
            })
            .sort()
            .slice(0, 2);
    }

    function segment(el) {
        var classes = stableClasses(el);
        return el.tagName.toLowerCase() + (classes.length ? '.' + classes.join('.') : '');
    }

    function absolutePath(el) {
        var parts = [];
        while (el && el.nodeType === 1 && el !== document.body && el !== document.documentElement && parts.length < 6) {
            if (el.id && /^[a-zA-Z][\w-]{0,60}$/.test(el.id) && !/\d{3,}/.test(el.id)) {
                parts.unshift('#' + el.id);
                break;
            }
            parts.unshift(segment(el));
            el = el.parentElement;
        }
        return parts.join(' > ');
    }

    function relativePath(el, ancestor) {
        if (el === ancestor) return null;
        var parts = [];
        var node = el;
        while (node && node !== ancestor && parts.length < 4) {
            if (node === el || stableClasses(node).length) parts.unshift(segment(node));
            node = node.parentElement;
        }
        return parts.join(' ');
    }

    function commonAncestor(elements) {
        var first = elements[0];
        var node = first;
        while (node) {
            if (elements.every(function (e) { return node.contains(e); })) return node;
            node = node.parentElement;
        }
        return document.body;
    }

    function sameSignatureSiblings(el) {
        if (!el.parentElement) return [];
        var sig = segment(el);
        return Array.prototype.filter.call(el.parentElement.children, function (c) { return segment(c) === sig; });
    }

    function findCard(elements) {
        var card = commonAncestor(elements);
        while (card && card !== document.body) {
            if (sameSignatureSiblings(card).length >= 3) return card;
            card = card.parentElement;
        }
        return null;
    }

    function clearMarks(attribute) {
        document.querySelectorAll('[' + attribute + ']').forEach(function (e) { e.removeAttribute(attribute); });
    }

    function describe(el) {
        var img = el.tagName === 'IMG' ? el : el.querySelector('img');
        var anchor = el.closest('a') || el.querySelector('a');
        return {
            text: (el.innerText || el.textContent || '').trim().slice(0, 160),
            src: img ? img.getAttribute('data-src') || img.currentSrc || img.src : null,
            href: anchor ? anchor.href : null,
        };
    }

    function build() {
        var examples = ['name', 'price', 'image']
            .map(function (f) { return picked[f]; })
            .filter(Boolean);

        if (examples.length < 2) {
            send({ type: 'catalogix:error', message: 'Pick at least a name and a price first.' });
            return;
        }

        var card = findCard(examples);

        if (!card) {
            send({ type: 'catalogix:error', message: 'Could not find a repeated product card around your picks. Try picking inside one product box.' });
            return;
        }

        var signature = segment(card);
        var siblings = sameSignatureSiblings(card);
        var cardSelector = document.querySelectorAll(signature).length === siblings.length
            ? signature
            : absolutePath(card.parentElement) + ' > ' + signature;

        var selectors = {
            card: cardSelector,
            name: picked.name ? relativePath(picked.name, card) : null,
            price: picked.price ? relativePath(picked.price, card) : null,
            sale_price: picked.sale_price && card.contains(picked.sale_price) ? relativePath(picked.sale_price, card) : null,
            image: picked.image ? relativePath(picked.image, card) : null,
            link: null,
            next: picked.next ? absolutePath(picked.next) : null,
        };

        highlight(cardSelector);
        send({ type: 'catalogix:recipe', selectors: selectors, count: document.querySelectorAll(cardSelector).length });
    }

    function highlight(selector) {
        clearMarks('data-cx-match');
        try {
            document.querySelectorAll(selector).forEach(function (e) { e.setAttribute('data-cx-match', ''); });
        } catch (e) {
            send({ type: 'catalogix:error', message: 'That selector is not valid CSS.' });
        }
    }

    document.addEventListener('mouseover', function (event) {
        if (!currentField) return;
        if (hovered) hovered.removeAttribute('data-cx-hover');
        hovered = event.target;
        hovered.setAttribute('data-cx-hover', '');
    }, true);

    document.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();

        if (!currentField) return;

        var el = event.target;

        if (currentField === 'image' && el.tagName !== 'IMG') {
            el = el.querySelector('img') || (el.closest('a') && el.closest('a').querySelector('img')) || el;
        }

        if (currentField === 'next') {
            el = el.closest('a') || el;
        }

        if (picked[currentField]) picked[currentField].removeAttribute('data-cx-picked');
        picked[currentField] = el;
        el.setAttribute('data-cx-picked', '');
        el.style.setProperty('--cx-color', COLORS[currentField] || '#2563eb');

        send({ type: 'catalogix:picked', field: currentField, path: absolutePath(el), sample: describe(el) });
        currentField = null;
        if (hovered) hovered.removeAttribute('data-cx-hover');
    }, true);

    document.addEventListener('submit', function (event) { event.preventDefault(); }, true);

    window.addEventListener('message', function (event) {
        var data = event.data || {};

        if (data.type === 'catalogix:pick') currentField = data.field;
        if (data.type === 'catalogix:build') build();
        if (data.type === 'catalogix:highlight') highlight(data.selector);
        if (data.type === 'catalogix:reset') {
            picked = {};
            currentField = null;
            clearMarks('data-cx-picked');
            clearMarks('data-cx-match');
        }
    });
})();
