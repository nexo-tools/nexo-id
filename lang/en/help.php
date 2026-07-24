<?php

// Help center content for Nexo ID (rendered by help/index via __('help.faqs')).
// Dot-notation lookups, so the JSON translation generator ignores them.
return [
    'meta_description' => 'Nexo ID help: what single sign-on is, what your account is for, security and privacy, and how to manage your sessions.',
    'faqs' => [
        [
            'q' => 'What is Nexo ID?',
            'a' => 'Nexo ID is the single sign-on for the Nexo ecosystem: one account you use to log in to every Nexo tool, instead of a separate password for each app.',
        ],
        [
            'q' => 'What do I use it for?',
            'a' => 'Sign in once with Nexo ID and connect to any Nexo tool with the same account. When a tool asks to sign you in, you approve it once on a consent screen and you are in.',
        ],
        [
            'q' => 'Is it secure? What about my privacy?',
            'a' => 'Your password is stored hashed, never in plain text. Each sign-in is a separate device session you can review and revoke. A tool only receives the data you approve on the consent screen (such as your name or email) — nothing more. Nexo ID is open source and can be self-hosted.',
        ],
        [
            'q' => 'How do I sign out or manage my sessions?',
            'a' => 'Open "Your account". You can sign out there, and under "Active sessions" you can see every device signed in and sign out any of them (or all other sessions) if you no longer recognise one.',
        ],
        [
            'q' => 'I forgot my password — how do I reset it?',
            'a' => 'On the sign-in page use "Forgot your password?" and enter your email. We send you a link to choose a new password; the link expires after a short while for safety.',
        ],
    ],
];
