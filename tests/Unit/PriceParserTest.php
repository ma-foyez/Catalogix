<?php

use App\Services\Extraction\PriceParser;

it('parses taka prices in every common spelling', function (string $input, ?float $expected) {
    expect(PriceParser::parse($input))->toBe($expected);
})->with([
    ['৳ 1,250', 1250.0],
    ['Tk. 1,250.50', 1250.5],
    ['BDT 999', 999.0],
    ['৳ ১,২৫০', 1250.0],
    ['৯০ টাকা', 90.0],
    ['1,25,000', 125000.0],
    ['৳ 0', null],
    ['Free', null],
]);

it('accepts numbers and rejects other types', function () {
    expect(PriceParser::parse(380))->toBe(380.0)
        ->and(PriceParser::parse(18.5))->toBe(18.5)
        ->and(PriceParser::parse(null))->toBeNull()
        ->and(PriceParser::parse(['amount' => 1]))->toBeNull();
});

it('finds every marked price but skips "save" amounts', function () {
    expect(PriceParser::allMarked('৳149৳230'))->toBe([149.0, 230.0])
        ->and(PriceParser::allMarked('Tk 3,499 Tk 2,999 Save ৳500'))->toBe([3499.0, 2999.0])
        ->and(PriceParser::allMarked('৳ 1,250 1 kg'))->toBe([1250.0]);
});
