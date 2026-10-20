<?php

use App\Services\Extraction\ProductNormalizer;

it('makes the lower of two prices the sale price', function () {
    $product = (new ProductNormalizer)->normalize(
        ['name' => 'Tata Tea Premium', 'prices' => [149, 230], 'image' => 'https://i.chaldn.com/tea.jpg'],
        'https://chaldal.com/popular',
    );

    expect($product['price'])->toBe(230.0)
        ->and($product['sale_price'])->toBe(149.0)
        ->and($product['currency'])->toBe('BDT');
});

it('makes links and images absolute and hashes by product URL', function () {
    $product = (new ProductNormalizer)->normalize(
        ['name' => 'Apex Sneaker', 'price' => 'Tk 2,450', 'url' => '/product/apex?utm_source=x', 'image' => '/img/a-300.jpg 300w, /img/a-800.jpg 800w'],
        'https://sawdah.com.bd/category/shoes',
    );

    expect($product['product_url'])->toBe('https://sawdah.com.bd/product/apex')
        ->and($product['image_url'])->toBe('https://sawdah.com.bd/img/a-800.jpg')
        ->and($product['hash'])->toBe(sha1('https://sawdah.com.bd/product/apex'));
});

it('hashes by name, price and image when there is no product URL', function () {
    $product = (new ProductNormalizer)->normalize(['name' => 'Potato (Alu)', 'price' => '৳ ৪৫'], 'https://chaldal.com/fresh-vegetables');

    expect($product['price'])->toBe(45.0)
        ->and($product['hash'])->toBe(sha1('potato (alu)|45|'));
});

it('rejects rows without a usable name', function () {
    expect((new ProductNormalizer)->normalize(['name' => '৳ 120', 'price' => 120], 'https://x.com.bd'))->toBeNull()
        ->and((new ProductNormalizer)->normalize(['price' => 120], 'https://x.com.bd'))->toBeNull();
});
