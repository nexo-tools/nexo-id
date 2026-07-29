<?php

// Nexo ID legal pages (privacy + terms), rendered by legal/show.
//
// NOT reviewed by a lawyer. Written to describe accurately what this codebase
// actually does — the part anyone can verify by reading the repo — so that a
// legal review, if the operator wants one, starts from something true instead of
// from boilerplate about data the app never touches.
//
// Spanish (lang/es/legal.php) is the source of this content; this is its translation.
return [
    'updated' => 'Last updated: 28 July 2026',

    'operator' => [
        'h' => 'Who runs this instance',
        'p' => 'This instance is operated by :operator.',
        'contact' => 'You can write to :contact.',
    ],

    'privacy' => [
        'title' => 'Privacy',
        'intro' => 'Nexo ID is the identity service of the Nexo ecosystem: one account for every Nexo tool. It is open source and self-hostable, and this policy describes what this instance does. We store the minimum needed to identify you and nothing else: no tracking cookies, no third-party analytics, no advertising.',
        'sections' => [
            [
                'h' => 'What we store about your account',
                'p' => 'Your display name, your email address and your password turned into an irreversible hash: nobody can read it, not even whoever runs this instance. We also store the date you verified your email and the language you chose. The email is stored normalized (trimmed and lowercased) so the same address cannot register twice.',
            ],
            [
                'h' => 'Email verification',
                'p' => 'When you register we send you a signed link that expires. Until you confirm it you cannot reach your profile or authorize any tool to use your account: verification is what makes your identity count in the rest of the ecosystem.',
            ],
            [
                'h' => 'Your sessions',
                'p' => 'For every sign-in we store a session identifier, the IP address and the browser you signed in from, and the time of the last activity. You can see them all under "Your account" and close any of them, or every other one at once. They exist so you can spot an access that is not yours, and they are deleted when you sign out or when they expire.',
            ],
            [
                'h' => 'What a tool receives when you authorize it',
                'p' => 'When you sign in to a Nexo tool with your account, that tool receives only the data covered by the scope it was granted: always an identifier for your account (the "sub" claim) and — if it asks for the profile or email scope — your display name ("name"), your email address ("email") and whether it is verified ("email_verified"). It never receives your password, your sessions, your IP address or the list of the other tools you use.',
            ],
            [
                'h' => 'Your identifier is the same in every tool',
                'p' => 'The identifier tools receive is your account id and it never changes: it is what makes you still be you when you come back, and what makes "one account for everything" possible. The honest trade-off is that two tools comparing that value can tell you are the same person. If you would rather avoid that, use separate accounts or each tool\'s standalone mode, which works without Nexo ID.',
            ],
            [
                'h' => 'What we store about authorizations',
                'p' => 'We store which application you authorized, with which scopes and until when, as codes and tokens tied to your account; they expire on their own and can be revoked. The Nexo tools themselves are first-party clients and show no consent screen, because whoever runs this instance registered them; a third-party application does ask for your consent. There is no screen in your profile yet to review and revoke those grants: if you want one cut off, write to this instance\'s contact.',
            ],
            [
                'h' => 'Cookies',
                'p' => 'Only the ones the service needs: the session cookie (encrypted), the CSRF protection cookie, and two preferences shared with the rest of the ecosystem — "nexo-lang" for the language and "nexo-theme" for the light/dark theme — which are deliberately unencrypted so every tool can read them and hold no data about you. If you tick "Remember me" a cookie with a random token is added so you are not asked for your password on every visit. None of them is used for advertising or tracking.',
            ],
            [
                'h' => 'Emails we send you',
                'p' => 'Account emails only: email verification, password reset and the notice you get when your password changes. They go out through the email provider this instance has configured, which necessarily processes the destination address and the message content in order to deliver it. We send no newsletters and no promotions.',
            ],
            [
                'h' => 'Security and rate limits',
                'p' => 'We count failed sign-in attempts per email and IP address combination to temporarily block brute-force attacks, and we apply per-IP limits to sensitive requests. Those counters are temporary, live in the cache and expire on their own.',
            ],
            [
                'h' => 'Metrics',
                'p' => 'This instance may enable the ecosystem pageview counter, which sends an anonymous signal — tool and path, nothing else — with no cookies and honouring "Do Not Track". It ships disabled and identifies nobody.',
            ],
            [
                'h' => 'Every tool has its own policy',
                'p' => 'Nexo ID only deals with your identity. What each tool does with whatever you create inside it — your links, your bookings, your events — is explained by that tool\'s privacy policy, not by this one.',
            ],
            [
                'h' => 'How long we keep the data',
                'p' => 'Your account and its data are kept for as long as the account exists. Sessions and tokens expire on their own; verification and password reset links expire within minutes and are single-use.',
            ],
            [
                'h' => 'Your rights',
                'p' => 'You change your display name and your password yourself under "Your account". To request access to your data, its correction or the deletion of your account, write to whoever runs this instance (the contact is below and on the help page).',
            ],
            [
                'h' => 'Other instances',
                'p' => 'Nexo ID can be installed on any server. Each installation is independent and answers for its own data: this policy covers this instance only.',
            ],
        ],
    ],

    'terms' => [
        'title' => 'Terms of use',
        'intro' => 'By using this instance of Nexo ID you accept what follows. It is a free service, offered as is.',
        'sections' => [
            [
                'h' => 'What the service is',
                'p' => 'Nexo ID is an identity provider: you create an account here and use it to sign in to the tools of the Nexo ecosystem through OAuth 2.0 with PKCE and OpenID Connect. It does not host the content you create inside each tool, and it is not an email or file service.',
            ],
            [
                'h' => 'Your account',
                'p' => 'You need a real email address and you must verify it before the account can be used with the tools. You are responsible for keeping your password safe and for what is done from your sessions. If you suspect someone else got in, change your password and close the other sessions from your profile. An account belongs to one person: do not share it.',
            ],
            [
                'h' => 'What you authorize when you sign in to a tool',
                'p' => 'Using Nexo ID to sign in to a tool gives that tool access to the data covered by the requested scope: your identifier, your display name, your email and its verification status. From then on that tool handles those data under its own terms and its own privacy policy; Nexo ID does not answer for what it does with them.',
            ],
            [
                'h' => 'Misuse',
                'p' => 'You may not impersonate another person, create accounts automatically, try out somebody else\'s credentials, work around the rate limits, or attack the service or the tools that depend on it. Whoever runs this instance may suspend an account that does any of those things.',
            ],
            [
                'h' => 'Client applications',
                'p' => 'Registering an application against this instance is decided by whoever runs it. A registered application must request only the scopes it needs and use the data for what the user authorized; the registration can be revoked at any time.',
            ],
            [
                'h' => 'Availability',
                'p' => 'The service is offered with no availability guarantee. Bear in mind that if Nexo ID is down you will not be able to sign in to the tools that depend on it either, even when those tools are up.',
            ],
            [
                'h' => 'Limitation of liability',
                'p' => 'Whoever runs this instance is not liable for damages arising from the use of the service, including access that does not work, outages or data loss.',
            ],
            [
                'h' => 'Free software',
                'p' => 'Nexo ID is distributed under the MIT license: you can read the code, modify it and host your own instance. The software is provided with no warranty, as that license states.',
            ],
            [
                'h' => 'Changes',
                'p' => 'These terms may change. The date above marks the last update.',
            ],
        ],
    ],
];
