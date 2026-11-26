<?php

// Help center content for Nexo ID (rendered by help/index via __('help.faqs')).
// Dot-notation lookups, so the JSON translation generator ignores them. What it
// says must be what happens: Nexo tools are first-party and sign in without a
// permission screen (OauthClient::skipsAuthorization); only a third-party app
// shows one (H-nexoid-11, HelpContentTest).
return [
    'meta_description' => 'Nexo ID help: what single sign-on is, what your account is for, security and privacy, how to manage your sessions and how to delete your account.',
    'faqs' => [
        [
            'q' => 'What is Nexo ID?',
            'a' => 'Nexo ID is the single sign-on for the Nexo ecosystem: one account you use to sign in to every Nexo tool, instead of a separate password for each app.',
        ],
        [
            'q' => 'What do I use it for?',
            'a' => 'Sign in once with Nexo ID and get into any Nexo tool with the same account. Nexo tools let you straight in, without asking for permission, because they are part of the same ecosystem. An app from another company, on the other hand, first shows you which data it wants and asks for your permission.',
        ],
        [
            'q' => 'Is it secure? What about my privacy?',
            'a' => 'Your password is stored encrypted, never as is. Each sign-in is a device session you can review and close. A Nexo tool only receives your account identifier, your display name and your email — never your password, your sessions or the list of the other tools you use. Nexo ID is open source and can be installed on your own server.',
        ],
        [
            'q' => 'How do I sign out or manage my sessions?',
            'a' => '"Sign out", in your account menu, signs this device out of Nexo ID and of every Nexo tool you opened with it. In "Your account", under "Active sessions", you see each device with its browser, its last activity and the tools it was used in, and you can sign out one ("Sign out this session"), all the others, or all of them, this one included.',
        ],
        [
            'q' => 'Where do I see the tools that use my account?',
            'a' => 'In "Your account", under "Connected tools": every Nexo tool where you used this account, when you last did, and a button to open it.',
        ],
        [
            'q' => 'How do I delete my account?',
            'a' => 'In "Your account", at the bottom, under "Delete your account", with your password. Your name, your email, your password and your sessions are deleted, and every Nexo tool signs you out. What you have in each tool is not deleted with this: if you want to delete it, do it first in each tool, because afterwards you will not be able to get in with this account.',
        ],
        [
            'q' => 'I forgot my password — how do I reset it?',
            'a' => 'On the sign-in page use "Forgot your password?" and enter your email. We send you a link to choose a new password; the link expires after a short while for safety.',
        ],
    ],
];
