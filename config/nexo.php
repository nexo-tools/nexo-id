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
        'url' => env('NEXO_ATTRIBUTION_URL'),
    ],

    // Help-center contact target (instance-configurable). A support form URL wins;
    // otherwise a support email becomes a mailto:; otherwise the help page falls
    // back to the attribution URL (the repo) so "contact us" always resolves.
    'support_url' => env('NEXO_SUPPORT_URL', ''),
    // Mail al operador cuando algo revienta (nexo-ops). Off por default: una
    // instancia recién clonada no debería empezar a mandar correo sin que su
    // operador lo decida. Dedupe de 15 min por excepción, kill-switch por env.
    'ops_mail' => env('NEXO_OPS_MAIL', false),

    'support_email' => env('NEXO_SUPPORT_EMAIL', ''),

    // Who runs THIS instance, shown on /privacidad and /terminos. Empty by
    // default (the section is then omitted) so a self-host never publishes the
    // upstream author as the data controller of its own installation.
    'legal' => [
        'operator' => env('NEXO_LEGAL_OPERATOR', ''),
        'contact' => env('NEXO_LEGAL_CONTACT', ''),
    ],

    // Password policy (see SPEC AC-REG-3). Kept in config so it is testable and
    // adjustable per instance without touching validation code.
    'password' => [
        'min_length' => (int) env('NEXO_PASSWORD_MIN_LENGTH', 8),
    ],

    // Verification and password-reset link lifetimes (minutes) live in
    // config/auth.php as the single source of truth: auth.verification.expire
    // (NEXO_VERIFICATION_TTL) and auth.passwords.users.expire
    // (NEXO_PASSWORD_RESET_TTL), where Laravel's notifications/broker read them.

    // Cookieless ecosystem analytics (opt-in). Off by default so a standalone
    // install phones nobody home; when enabled, resources/js/nexo-beacon.js
    // sendBeacon()s an anonymous pageview to the Nexo Tools hub. See the shared
    // partials/beacon.blade.php (metas rendered only when enabled).
    'beacon' => [
        'enabled' => (bool) env('NEXO_BEACON_ENABLED', false),

        // The hub's ingestion endpoint (absolute — this tool is not the hub).
        'endpoint' => (string) env('NEXO_BEACON_ENDPOINT', 'https://nexotools.alvarocdev.com/beacon'),

        // This tool's slug, sent as the beacon `origin` (the hub allowlists it).
        'origin' => (string) env('NEXO_BEACON_ORIGIN', 'nexoid'),
    ],

    // Techos de rate limiting, env-tunables: un límite escrito a mano en el
    // provider es un límite que nadie puede subir a las 3 de la mañana cuando
    // un pico legítimo se confunde con un ataque (STANDARD.md, "Rate limiting").
    'login_rate' => [
        'per_ip' => env('NEXO_LOGIN_RATE_PER_IP', 20),
    ],

    'oidc_rate' => [
        'per_ip' => env('NEXO_OIDC_RATE_PER_IP', 60),
    ],
];
