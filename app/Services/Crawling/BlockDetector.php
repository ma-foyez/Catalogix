<?php

namespace App\Services\Crawling;

/**
 * Recognises login walls, CAPTCHAs and bot challenges. Catalogix marks these "blocked" and never tries to get past them.
 */
final class BlockDetector
{
    private const array CHALLENGE_MARKERS = [
        '/cdn-cgi/challenge-platform/',
        'cf-browser-verification',
        'cf_chl_opt',
        '<title>Just a moment...</title>',
        'Attention Required! | Cloudflare',
        'px-captcha',
        '_Incapsula_Resource',
        'Pardon Our Interruption',
        'distil_r_captcha',
        'datadome',
    ];

    private const array CAPTCHA_MARKERS = ['g-recaptcha', 'h-captcha', 'hcaptcha.com/1/api.js', 'cf-turnstile'];

    private const string LOGIN_PATH_PATTERN = '#/(login|signin|sign-in|log-in|account/login|auth/login|my-account)(/|$|\?)#i';

    public function reason(string $requestedUrl, string $finalUrl, int $status, string $body): ?string
    {
        if ($status === 401) {
            return 'The page requires a login.';
        }

        foreach (self::CHALLENGE_MARKERS as $marker) {
            if (stripos($body, $marker) !== false) {
                return 'The site answered with a bot challenge.';
            }
        }

        if ($this->isCaptchaWall($status, $body)) {
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
     * Many shops load reCAPTCHA for a newsletter form, so a CAPTCHA script alone is not a wall:
     * it only counts when the response is an error or a near-empty page.
     */
    private function isCaptchaWall(int $status, string $body): bool
    {
        foreach (self::CAPTCHA_MARKERS as $marker) {
            if (stripos($body, $marker) !== false) {
                $visibleText = trim(strip_tags(preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $body) ?? ''));

                return $status >= 400 || mb_strlen($visibleText) < 600;
            }
        }

        return false;
    }
}
