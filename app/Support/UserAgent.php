<?php

namespace App\Support;

/**
 * «Chrome en Windows» out of a raw user-agent string, for the session list
 * (H-nexoid-06: the rows used to show «Mozilla/5.0 (Windows NT 10.0…», which
 * told nobody which device was which).
 *
 * Deliberately small: the families people actually recognise, matched in the
 * order that keeps the impostors out (every Chromium browser says «Chrome»,
 * every iOS browser says «Safari», Edge and Opera also say «Chrome»). No
 * version numbers — they change monthly and say nothing to a person. Anything
 * unrecognised comes back as null and the view says «Unknown browser».
 */
final class UserAgent
{
    /** @var array<int, array{0: string, 1: string}> pattern => browser, first match wins */
    private const BROWSERS = [
        ['/\bEdg(e|A|iOS)?\//', 'Edge'],
        ['/\b(OPR|Opera|OPiOS)\//', 'Opera'],
        ['/\bSamsungBrowser\//', 'Samsung Internet'],
        ['/\b(Firefox|FxiOS)\//', 'Firefox'],
        ['/\b(Chrome|CriOS|HeadlessChrome|Chromium)\//', 'Chrome'],
        ['/\bVersion\/[\d.]+.*\bSafari\//', 'Safari'],
        ['/\bAppleWebKit\/.*\b(iPhone|iPad)\b/', 'Safari'],
    ];

    /** @var array<int, array{0: string, 1: string}> pattern => system, first match wins */
    private const SYSTEMS = [
        ['/\b(iPhone|iPod)\b/', 'iPhone'],
        ['/\biPad\b/', 'iPad'],
        ['/\bAndroid\b/', 'Android'],
        ['/\bCrOS\b/', 'ChromeOS'],
        ['/\bWindows\b/', 'Windows'],
        ['/\bMac OS X\b|\bMacintosh\b/', 'macOS'],
        ['/\bLinux\b/', 'Linux'],
    ];

    /** @return array{browser: string|null, system: string|null} */
    public static function parse(?string $userAgent): array
    {
        $userAgent = (string) $userAgent;

        return [
            'browser' => self::first(self::BROWSERS, $userAgent),
            'system' => self::first(self::SYSTEMS, $userAgent),
        ];
    }

    /** @param  array<int, array{0: string, 1: string}>  $rules */
    private static function first(array $rules, string $userAgent): ?string
    {
        foreach ($rules as [$pattern, $name]) {
            if (preg_match($pattern, $userAgent) === 1) {
                return $name;
            }
        }

        return null;
    }
}
