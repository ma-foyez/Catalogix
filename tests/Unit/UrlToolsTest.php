<?php

use App\Services\Crawling\UrlTools;

it('turns any domain input into a bare domain', function (string $input, ?string $domain) {
    expect(UrlTools::domainFromInput($input))->toBe($domain);
})->with([
    ['chaldal.com', 'chaldal.com'],
    ['www.Chaldal.com', 'chaldal.com'],
    ['https://www.chaldal.com/popular?x=1', 'chaldal.com'],
    ['v2.sawdahexpress.com.bd/category/shoes', 'v2.sawdahexpress.com.bd'],
    ['not a domain', null],
    ['localhost', null],
]);

it('resolves relative links against the page', function () {
    $base = 'https://shop.com.bd/category/shoes?page=2';

    expect(UrlTools::absolute('/product/a', $base))->toBe('https://shop.com.bd/product/a')
        ->and(UrlTools::absolute('b', $base))->toBe('https://shop.com.bd/category/b')
        ->and(UrlTools::absolute('../sale', $base))->toBe('https://shop.com.bd/sale')
        ->and(UrlTools::absolute('//cdn.shop.com.bd/i.jpg', $base))->toBe('https://cdn.shop.com.bd/i.jpg')
        ->and(UrlTools::absolute('?page=3', $base))->toBe('https://shop.com.bd/category/shoes?page=3')
        ->and(UrlTools::absolute('#top', $base))->toBeNull()
        ->and(UrlTools::absolute('javascript:void(0)', $base))->toBeNull();
});

it('removes tracking parameters and fragments', function () {
    expect(UrlTools::clean('https://Shop.com.bd/p/a?utm_source=fb&color=red&fbclid=1#reviews'))
        ->toBe('https://shop.com.bd/p/a?color=red');
});
