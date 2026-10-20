<?php

namespace App\Support;

use App\Services\Crawling\UrlTools;

/**
 * Reads whatever people paste — "chaldal.com", "https://www.rflbd.com/shop", several of them
 * separated by commas, spaces or new lines — into a clean list of domains.
 */
final class DomainList
{
    public const int MAX = 20;

    /**
     * @param  string|list<string>  $input
     * @return array{domains: list<string>, invalid: list<string>}
     */
    public static function parse(string|array $input): array
    {
        $text = is_array($input) ? implode("\n", $input) : $input;
        $tokens = preg_split('/[\s,;|]+/u', trim($text)) ?: [];
        $domains = [];
        $invalid = [];

        foreach ($tokens as $token) {
            $token = trim($token, " \t\n\r\0\x0B\"'<>()[]");

            if ($token === '') {
                continue;
            }

            $domain = UrlTools::domainFromInput($token);

            if ($domain === null) {
                $invalid[] = mb_strimwidth($token, 0, 60, '…');
            } elseif (! in_array($domain, $domains, true)) {
                $domains[] = $domain;
            }
        }

        return ['domains' => $domains, 'invalid' => array_values(array_unique($invalid))];
    }
}
