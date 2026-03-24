<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

class OnboardingException extends RuntimeException
{
    /**
     * @param  array<string, array<int, string>>  $errors
     */
    public function __construct(
        private readonly int $status,
        private readonly array $errors,
        string $message,
    ) {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public static function tokenInvalid(): self
    {
        return new self(410, ['code' => ['token_invalid']], 'La invitacion no es valida.');
    }

    public static function tokenExpired(): self
    {
        return new self(410, ['code' => ['token_expired']], 'La invitacion ha expirado.');
    }

    public static function tokenConsumed(): self
    {
        return new self(410, ['code' => ['token_consumed']], 'La invitacion ya fue utilizada.');
    }

    public static function tokenRevoked(): self
    {
        return new self(410, ['code' => ['token_revoked']], 'La invitacion fue revocada.');
    }

    public static function tenantSlugTaken(string $message = 'El slug seleccionado no esta disponible.'): self
    {
        return new self(409, [
            'code' => ['tenant_slug_taken'],
            'tenant.slug' => [$message],
        ], $message);
    }

    public static function onboardingConflict(): self
    {
        return new self(409, ['code' => ['onboarding_conflict']], 'No se pudo completar el onboarding para este correo.');
    }

    public static function onboardingUnavailable(): self
    {
        return new self(503, ['code' => ['onboarding_unavailable']], 'El onboarding no esta disponible temporalmente.');
    }
}
