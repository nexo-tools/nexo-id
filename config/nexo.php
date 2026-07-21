<?php

return [

    // Locales the identity UI is translated into. The base language is English
    // (source strings live in the code); es/pt are generated maps kept in sync
    // by scripts/generate-translations.mjs and its guardian test.
    'locales' => ['en', 'es', 'pt'],

    // Instance-configurable attribution footer (multi-instance branding, per the
    // add-branding-footer skill). Neutral product default → the repo; Alvaro's
    // hosted instance overrides both env vars via .env, e.g.
    //   NEXO_ATTRIBUTION_LABEL="powered by alvarocdev.com"
    //   NEXO_ATTRIBUTION_URL="https://alvarocdev.com/?utm_source=nexo-id&utm_medium=powered-by"
    'attribution' => [
        'label' => env('NEXO_ATTRIBUTION_LABEL', 'made with Nexo ID'),
        'url' => env('NEXO_ATTRIBUTION_URL', 'https://github.com/alvarocdev-git/nexo-id'),
    ],

    // Password policy (see SPEC AC-REG-3). Kept in config so it is testable and
    // adjustable per instance without touching validation code.
    'password' => [
        'min_length' => (int) env('NEXO_PASSWORD_MIN_LENGTH', 8),
    ],

    // Verification and password-reset link lifetimes (minutes).
    'verification_ttl' => (int) env('NEXO_VERIFICATION_TTL', 60),
    'password_reset_ttl' => (int) env('NEXO_PASSWORD_RESET_TTL', 60),

];
