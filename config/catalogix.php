<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Crawling
    |--------------------------------------------------------------------------
    |
    | Politeness rules: one page at a time per domain, a random 1-3 second gap
    | between pages, and robots.txt is always respected.
    |
    */

    'user_agent' => env('CATALOGIX_USER_AGENT', 'CatalogixBot/1.0'),

    'http' => [
        'timeout' => (int) env('CATALOGIX_HTTP_TIMEOUT', 20),
        'retries' => 2,
    ],

    'page_delay' => [
        'min' => (int) env('CATALOGIX_PAGE_DELAY_MIN', 1),
        'max' => (int) env('CATALOGIX_PAGE_DELAY_MAX', 3),
    ],

    'max_pages' => (int) env('CATALOGIX_MAX_PAGES', 30),

    /** A strategy wins when it finds at least this many products with a name and a price. */
    'min_products' => 3,

    /*
    |--------------------------------------------------------------------------
    | Discovery
    |--------------------------------------------------------------------------
    */

    'discovery' => [
        'menu_depth' => 2,
        'max_sitemap_urls' => 5000,
        'max_menu_pages' => 15,
        'max_probes' => (int) env('CATALOGIX_MAX_PROBES', 40),
        'listing_patterns' => [
            '/category/', '/categories/', '/product-category/', '/collections/',
            '/shop', '/c/', 'cat=', '/catalog/', '/department/',
        ],
        'product_patterns' => ['/product/', '/products/', '/p/', '/item/'],
        'ignore_patterns' => [
            '/blog', '/news', '/about', '/contact', '/cart', '/checkout', '/account',
            '/login', '/register', '/wishlist', '/privacy', '/terms', '/faq',
            '/careers', '/help', '/policy', '/my-account', '/wp-admin', '/feed',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Python helper (JavaScript-heavy pages)
    |--------------------------------------------------------------------------
    */

    'python_bin' => env('PYTHON_BIN', 'python'),

    'render' => [
        'script' => base_path('scraper/render.py'),
        'timeout' => (int) env('CATALOGIX_RENDER_TIMEOUT', 45),
        'process_timeout' => 90,
        'scroll_steps' => 6,
    ],

    /*
    |--------------------------------------------------------------------------
    | Images
    |--------------------------------------------------------------------------
    */

    'images' => [
        'disk' => 'public',
        'concurrency_per_site' => (int) env('CATALOGIX_IMAGE_CONCURRENCY', 4),
        'delay_ms' => (int) env('CATALOGIX_IMAGE_DELAY_MS', 300),
        'min_bytes' => 1024,
        'min_dimension' => 40,
        'max_bytes' => 15 * 1024 * 1024,
        'thumb_width' => 200,
        'max_attempts' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Exports
    |--------------------------------------------------------------------------
    */

    'exports' => [
        'disk' => 'local',
        'directory' => 'exports',
        'image_row_limit' => (int) env('CATALOGIX_EXPORT_IMAGE_ROW_LIMIT', 3000),
        'expires_hours' => 24,
    ],

];
