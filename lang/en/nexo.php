<?php

// Strings for the shared Nexo chrome (header, switchers, footer, help). These
// are dot-notation lookups (nexo.*), resolved from this PHP file — the JSON
// generator ignores them, so they need no entry in lang/{es,pt}.json.
return [
    'theme' => [
        'light' => 'Switch to light mode',
        'dark' => 'Switch to dark mode',
    ],
    'locale' => [
        'label' => 'Change language',
    ],
    'nav' => [
        'primary' => 'Primary navigation',
    ],
    'switcher' => [
        'label' => 'Browse Nexo tools',
        'title' => 'Nexo ecosystem',
        'soon' => 'Soon',
        'discover' => 'Discover them all on Nexo Tools',
        'developers' => 'For developers: view the source',
    ],
    'footer' => [
        'part_of' => 'Part of the Nexo ecosystem',
        'powered_by' => 'Made by',
        'source' => 'Open source on GitHub',
    ],
    'help' => [
        'title' => 'Help center',
        'intro' => 'Have a question? Start here.',
        'contact_title' => "Didn't find what you needed?",
        'contact_cta' => 'Contact us',
    ],
];
