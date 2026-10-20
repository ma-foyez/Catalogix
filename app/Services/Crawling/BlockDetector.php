<?php

namespace App\Services\Crawling;

/**
 * Recognises login walls, CAPTCHAs and bot challenges. Catalogix marks these "blocked" and never tries to get past them.
 */
final class BlockDetector
{
    /** Only ever present on an interstitial challenge page. */
    private const array CHALLENGE_PAGE_MARKERS = [
        '<title>Just a moment...</title>',
        'cf-browser-verification',
        'Attention Required! | Cloudflare',
        'px-captcha',
        'Pardon Our Interruption',
        'distil_r_captcha',
    ];

    /**
     * Bot-protection scripts that sites behind Cloudflare, DataDome or Incapsula also load on normal pages,
     * so they only mean "blocked" on an error response or a page with almost no content.
     */
    private const array PROTECTION_SCRIPT_MARKERS = [
        '/cdn-cgi/challenge-platform/',
        'cf_chl_opt',
        'datadome',
        '_Incapsula_Resource',
    ];

    private const array CAPTCHA_MARKERS = ['g-recaptcha', 'h-captcha', 'hcaptcha.com/1/api.js', 'cf-turnstile'];

    private const string LOGIN_PATH_PATTERN = '#/(login|signin|sign-in|log-in|account/login|auth/login|my-account)(/|$|\?)#i';

    /** A page with less visible text than this is an interstitial, not a shop page. */
    private const int NEAR_EMPTY_TEXT = 600;

    private const int INTERSTITIAL_MAX_BYTES = 100_000;

    public function reason(string $requestedUrl, string $finalUrl, int $status, string $body): ?string
    {
        if ($status === 401) {
            return 'The page requires a login.';
        }

        if ($this->containsAny($body, self::CHALLENGE_PAGE_MARKERS)
            || ($this->containsAny($body, self::PROTECTION_SCRIPT_MARKERS) && $this->looksLikeInterstitial($status, $body))) {
            return 'The site answered with a bot challenge.';
        }

        // Many shops load reCAPTCHA for a newsletter form, so a CAPTCHA script alone is not a wall either.
        if ($this->containsAny($body, self::CAPTCHA_MARKERS) && $this->looksLikeInterstitial($status, $body)) {
            return 'The page is behind a CAPTCHA.';
        }

        if (! preg_match(self::LOGIN_PATH_PATTERN, $requestedUrl) && preg_match(self::LOGIN_PATH_PATTERN, $finalUrl)) {
            return 'The site redirected to a login page.';
        }

        if ($status === 403) {
            return 'The site refused access (HTTP 403).';
        }

        return null;
    }

    /**
     * @param  list<string>  $markers
     */
    private function containsAny(string $body, array $markers): bool
    {
        foreach ($markers as $marker) {
            if (stripos($body, $marker) !== false) {
                return true;
            }
        }

        return false;
    }

    private function looksLikeInterstitial(int $status, string $body): bool
    {
        if ($status >= 400) {
            return true;
        }

        // Challenge pages are a few kilobytes; a large page is a real page.
        if (strlen($body) > self::INTERSTITIAL_MAX_BYTES) {
            return false;
        }

        $withoutCode = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $body) ?? $body;

        return mb_strlen(trim(strip_tags($withoutCode))) < self::NEAR_EMPTY_TEXT;
    }
}
