<?php

return [

    // Locales the identity UI is translated into. The base language is English
    // (source strings live in the code); es/pt are generated maps kept in sync
    // by scripts/generate-translations.mjs and its guardian test.
    'locales' => ['en', 'es', 'pt'],

    // Instance-configurable "powered by" attribution (multi-instance branding).
    // Alvaro's hosted instance sets these with a UTM-tagged URL; self-hosters
    // leave them unset (neutral default) or point to their own site.
    'attribution_url' => env('NEXO_ATTRIBUTION_URL'),
    'attribution_text' => env('NEXO_ATTRIBUTION_TEXT'),

    // Password policy (see SPEC AC-REG-3). Kept in config so it is testable and
    // adjustable per instance without touching validation code.
    'password' => [
        'min_length' => (int) env('NEXO_PASSWORD_MIN_LENGTH', 8),
    ],

    // Verification and password-reset link lifetimes (minutes).
    'verification_ttl' => (int) env('NEXO_VERIFICATION_TTL', 60),
    'password_reset_ttl' => (int) env('NEXO_PASSWORD_RESET_TTL', 60),

];
