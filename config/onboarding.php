<?php

declare(strict_types=1);

return [
    'frontend_url' => env('ONBOARDING_FRONTEND_URL', 'http://localhost:3000/onboarding/bootstrap'),

    'invitation' => [
        'expires_in_hours' => (int) env('ONBOARDING_INVITATION_EXPIRES_IN_HOURS', 72),
    ],
];
