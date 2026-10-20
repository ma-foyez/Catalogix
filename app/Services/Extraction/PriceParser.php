<?php

namespace App\Services\Extraction;

final class PriceParser
{
    private const array BENGALI_DIGITS = [
        '০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4',
        '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9',
    ];

    /** Currency marker followed by an amount, or an amount followed by a marker. */
    private const string PRICE_PATTERN = '/(?:৳|tk\.?|bdt|taka|টাকা|\$|€|£|rs\.?)\s*([0-9০-৯][0-9০-৯,]*(?:\.[0-9০-৯]+)?)|([0-9০-৯][0-9০-৯,]*(?:\.[0-9০-৯]+)?)\s*(?:৳|tk\.?|bdt|taka|টাকা)/iu';

    public static function toAsciiDigits(string $value): string
    {
        return strtr($value, self::BENGALI_DIGITS);
    }

    /**
     * Parse one price from a number or a string such as "৳ ১,২৫০" or "Tk. 1,250.50". Zero or no number gives null.
     */
    public static function parse(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            return $value > 0 ? round((float) $value, 2) : null;
        }

        if (! is_string($value)) {
            return null;
        }

        $clean = self::toAsciiDigits(html_entity_decode($value, ENT_QUOTES | ENT_HTML5));
        $clean = preg_replace('/(৳|tk\.?|bdt|taka|টাকা|rs\.?|\$|€|£|\x{00A0})/iu', ' ', $clean) ?? $clean;

        if (! preg_match('/\d[\d,]*(?:\.\d+)?/', $clean, $match)) {
            return null;
        }

        $number = (float) str_replace(',', '', $match[0]);

        return $number > 0 ? round($number, 2) : null;
    }

    /**
     * Every currency-marked price in a block of text, in order of appearance.
     * Amounts right after "save", "off" or "discount" are skipped so "Save ৳20" is not read as a price.
     *
     * @return list<float>
     */
    public static function allMarked(string $text): array
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5);

        if (! preg_match_all(self::PRICE_PATTERN, $text, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            return [];
        }

        $prices = [];

        foreach ($matches as $match) {
            $before = mb_strtolower(substr($text, max(0, $match[0][1] - 12), min(12, $match[0][1])));

            if (preg_match('/(save|off|discount|ছাড়)\W*$/u', $before)) {
                continue;
            }

            $amount = $match[1][0] !== '' ? $match[1][0] : ($match[2][0] ?? '');
            $price = self::parse($amount);

            if ($price !== null) {
                $prices[] = $price;
            }
        }

        return $prices;
    }

    public static function looksLikePrice(string $text): bool
    {
        return (bool) preg_match(self::PRICE_PATTERN, $text);
    }
}
