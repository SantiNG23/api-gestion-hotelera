<?php

declare(strict_types=1);

return [
    'frontend_url' => env('ONBOARDING_FRONTEND_URL', 'http://localhost:3000/onboarding/bootstrap'),

    'invitation' => [
        'expires_in_hours' => (int) env('ONBOARDING_INVITATION_EXPIRES_IN_HOURS', 72),
    ],

    'completion' => [
        'mark_email_as_verified' => true,
        'send_welcome_mail' => false,
    ],
];
