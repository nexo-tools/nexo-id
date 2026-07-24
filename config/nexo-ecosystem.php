<?php

// Nexo ecosystem registry — the single source for the app-switcher and the
// footer's ecosystem links. Copy into a tool's config/ as `nexo-ecosystem.php`
// and set `current` to that tool's key. URLs are env-overridable so self-hosters
// can point at their own instances. Marks come from nexo-brand/marks/<key>.svg
// (copied into the tool's public/ as part of applying the brand).

return [

    // Which tool is this? The app-switcher marks it as current (or hides it from
    // its own list). One of the keys in `tools` below, or null for none (e.g. a
    // standalone self-host with no ecosystem identity).
    'current' => env('NEXO_ECOSYSTEM_CURRENT', null),

    // Portada (non-dev entry point) and the developer entry points.
    'hub_url' => env('NEXO_HUB_URL', 'https://nexotools.alvarocdev.com'),
    'github_org_url' => env('NEXO_GITHUB_ORG', 'https://github.com/nexo-tools'),
    'author_url' => env('NEXO_ATTRIBUTION_URL', 'https://alvarocdev.com'),

    // The tools. `status`: 'live' | 'soon'. `mark`: public path to the isotype.
    'tools' => [
        'nexotools' => [
            'name' => 'Nexo Tools',
            'tagline' => 'The open Nexo ecosystem.',
            'url' => env('NEXO_URL_TOOLS', 'https://nexotools.alvarocdev.com'),
            'mark' => '/ecosystem/nexotools.svg',
            'status' => 'live',
        ],
        'nexoid' => [
            'name' => 'Nexo ID',
            'tagline' => 'One account for every Nexo tool.',
            'url' => env('NEXO_URL_ID', 'https://nexoid.alvarocdev.com'),
            'mark' => '/ecosystem/nexoid.svg',
            'status' => 'live',
        ],
        'nexolinks' => [
            'name' => 'Nexo Links',
            'tagline' => 'Your links. Your domain. Your data.',
            'url' => env('NEXO_URL_LINKS', 'https://nexolinks.alvarocdev.com'),
            'mark' => '/ecosystem/nexolinks.svg',
            'status' => 'live',
        ],
        'nexoagenda' => [
            'name' => 'Nexo Agenda',
            'tagline' => 'Bookings without the lock-in.',
            'url' => env('NEXO_URL_AGENDA', 'https://nexoagenda.alvarocdev.com'),
            'mark' => '/ecosystem/nexoagenda.svg',
            'status' => 'live',
        ],
        'nexoshort' => [
            'name' => 'Nexo Short',
            'tagline' => 'Short links you own.',
            'url' => env('NEXO_URL_SHORT', 'https://nexoshort.alvarocdev.com'),
            'mark' => '/ecosystem/nexoshort.svg',
            'status' => 'live',
        ],
        'nexoevents' => [
            'name' => 'Nexo Events',
            'tagline' => 'Events, tickets and check-in.',
            'url' => env('NEXO_URL_EVENTS', 'https://nexoevents.alvarocdev.com'),
            'mark' => '/ecosystem/nexoevents.svg',
            'status' => 'soon',
        ],
    ],
];
