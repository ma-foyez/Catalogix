<?php

use App\Services\Crawling\BlockDetector;

it('blocks real challenge pages, CAPTCHA walls and login redirects', function () {
    $detector = new BlockDetector;

    expect($detector->reason('https://a.com.bd/x', 'https://a.com.bd/x', 403, page('cloudflare-challenge.html')))->toContain('bot challenge')
        ->and($detector->reason('https://a.com.bd/x', 'https://a.com.bd/x', 200, '<html><body><div class="g-recaptcha"></div></body></html>'))->toContain('CAPTCHA')
        ->and($detector->reason('https://a.com.bd/x', 'https://a.com.bd/account/login?next=/x', 200, '<html>Sign in</html>'))->toContain('login')
        ->and($detector->reason('https://a.com.bd/x', 'https://a.com.bd/x', 401, ''))->toContain('login');
});

it('does not block normal pages that load bot-protection or reCAPTCHA scripts', function () {
    $detector = new BlockDetector;
    $shop = page('chaldal-popular.html');

    expect($detector->reason('https://chaldal.com/popular', 'https://chaldal.com/popular', 200, $shop))->toBeNull()
        ->and($detector->reason('https://chaldal.com/popular', 'https://chaldal.com/popular', 200, $shop.'<div class="g-recaptcha"></div>'))->toBeNull();
});

it('treats a very large page as a real page even when PCRE gives up on it', function () {
    $huge = '<html><body><script src="/cdn-cgi/challenge-platform/scripts/jsd/main.js"></script>'
        .str_repeat('<style>.a{color:red}</style><div class="productV2Catalog">৳ 120</div>', 30000)
        .'</body></html>';

    expect((new BlockDetector)->reason('https://chaldal.com/popular', 'https://chaldal.com/popular', 200, $huge))->toBeNull();
});
