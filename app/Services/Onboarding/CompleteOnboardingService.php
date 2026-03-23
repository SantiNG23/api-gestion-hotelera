<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Models\Tenant;
use App\Models\User;
use App\Services\AuthService;

class CompleteOnboardingService
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function createOwnerForTenant(Tenant $tenant, array $userData): User
    {
        $payload = array_merge($userData, [
            'role' => User::ROLE_OWNER,
        ]);

        return $this->authService->createUserForTenant($tenant, $payload);
    }
}
