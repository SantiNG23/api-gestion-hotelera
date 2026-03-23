<?php

declare(strict_types=1);

namespace App\Services\Onboarding;

use App\Exceptions\OnboardingException;
use App\Mail\WelcomeUserMail;
use App\Models\OnboardingInvitation;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserSetting;
use App\Services\AuthService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CompleteOnboardingService
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly OnboardingTokenService $tokenService,
    ) {}

    public function createOwnerForTenant(Tenant $tenant, array $userData): User
    {
        $payload = array_merge($userData, [
            'role' => User::ROLE_OWNER,
        ]);

        return $this->authService->createUserForTenant($tenant, $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{token: string, user: User, tenant: Tenant}
     */
    public function complete(array $payload): array
    {
        return DB::transaction(function () use ($payload): array {
            $invitation = $this->findPendingInvitationForUpdate((string) $payload['token']);
            $tenantAttributes = $this->buildTenantAttributes(Arr::get($payload, 'tenant', []));
            $completedAt = now();

            if (Tenant::query()->where('slug', $tenantAttributes['slug'])->exists()) {
                throw OnboardingException::tenantSlugTaken();
            }

            try {
                $tenant = Tenant::query()->create($tenantAttributes);
                $owner = $this->createOwnerForTenant($tenant, [
                    'name' => Arr::get($payload, 'user.name'),
                    'email' => $invitation->email,
                    'password' => Arr::get($payload, 'user.password'),
                    'email_verified_at' => $this->resolveOwnerEmailVerifiedAt($completedAt),
                ]);

                $this->createOwnerSettings($owner);
                $this->markInvitationAsConsumed($invitation, $completedAt);
                $this->dispatchWelcomeMail($owner);

                $owner->loadMissing('tenant');

                return [
                    'token' => $this->authService->createApiToken($owner, 'onboarding-token'),
                    'user' => $owner,
                    'tenant' => $tenant,
                ];
            } catch (ValidationException) {
                throw OnboardingException::onboardingConflict();
            } catch (QueryException $exception) {
                if ($this->isTenantSlugUniqueViolation($exception)) {
                    throw OnboardingException::tenantSlugTaken();
                }

                throw $exception;
            }
        });
    }

    /**
     * @param  array<string, mixed>  $tenantPayload
     * @return array{name: string, slug: string, is_active: bool}
     */
    protected function buildTenantAttributes(array $tenantPayload): array
    {
        return [
            'name' => trim((string) ($tenantPayload['name'] ?? '')),
            'slug' => Str::lower(trim((string) ($tenantPayload['slug'] ?? ''))),
            'is_active' => true,
        ];
    }

    protected function createOwnerSettings(User $owner): UserSetting
    {
        $settings = new UserSetting([
            'user_id' => $owner->id,
            'tenant_id' => $owner->tenant_id,
            'locale' => 'es_AR',
            'timezone' => 'America/Argentina/Buenos_Aires',
            'marketing_emails' => false,
            'transactional_emails' => true,
        ]);

        $settings->save();

        return $settings;
    }

    protected function resolveOwnerEmailVerifiedAt(Carbon $completedAt): ?Carbon
    {
        if (! config('onboarding.completion.mark_email_as_verified', true)) {
            return null;
        }

        return $completedAt;
    }

    protected function dispatchWelcomeMail(User $owner): void
    {
        if (! config('onboarding.completion.send_welcome_mail', false)) {
            return;
        }

        DB::afterCommit(static function () use ($owner): void {
            Mail::to($owner)->queue(new WelcomeUserMail($owner));
        });
    }

    protected function findPendingInvitationForUpdate(string $plainTextToken): OnboardingInvitation
    {
        $invitation = OnboardingInvitation::query()
            ->where('token_hash', $this->tokenService->hashToken($plainTextToken))
            ->lockForUpdate()
            ->first();

        if (! $invitation) {
            throw OnboardingException::tokenInvalid();
        }

        if ($invitation->isRevoked()) {
            throw OnboardingException::tokenRevoked();
        }

        if ($invitation->isConsumed()) {
            throw OnboardingException::tokenConsumed();
        }

        if ($invitation->isExpired()) {
            throw OnboardingException::tokenExpired();
        }

        return $invitation;
    }

    protected function markInvitationAsConsumed(OnboardingInvitation $invitation, Carbon $consumedAt): void
    {
        $updatedRows = OnboardingInvitation::query()
            ->whereKey($invitation->getKey())
            ->whereNull('consumed_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', $consumedAt)
            ->update([
                'consumed_at' => $consumedAt,
            ]);

        if ($updatedRows !== 1) {
            $invitation->refresh();

            if ($invitation->isRevoked()) {
                throw OnboardingException::tokenRevoked();
            }

            if ($invitation->isConsumed()) {
                throw OnboardingException::tokenConsumed();
            }

            if ($invitation->isExpired()) {
                throw OnboardingException::tokenExpired();
            }

            throw OnboardingException::tokenInvalid();
        }

        $invitation->forceFill([
            'consumed_at' => $consumedAt,
        ]);
    }

    private function isTenantSlugUniqueViolation(QueryException $exception): bool
    {
        $message = Str::lower($exception->getMessage());

        return str_contains($message, 'tenants.slug') || str_contains($message, 'tenants_slug_unique');
    }
}
