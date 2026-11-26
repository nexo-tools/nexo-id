<?php

namespace App\Support;

/**
 * URI checks shared by the client registration commands.
 */
final class SsoClientUris
{
    /** An absolute http(s) URL with a host (http stays allowed for local dev). */
    public static function valid(string $uri): bool
    {
        if (filter_var($uri, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($uri);

        return is_array($parts)
            && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            && ($parts['host'] ?? '') !== ''
            && ! isset($parts['fragment']);
    }
}
