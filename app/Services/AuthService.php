<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\UserRegistered;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantContextResolver;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    public function __construct(
        private readonly TenantContextResolver $tenantContextResolver,
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * Valida las credenciales del usuario
     */
    public function validateCredentials(User|string $user, string $password, ?int $tenantId = null): bool
    {
        if (is_string($user)) {
            $user = $this->findUserByEmail($user, $tenantId);
        }

        if (! $user) {
            return false;
        }

        return Hash::check($password, $user->password);
    }

    /**
     * Crea un nuevo usuario
     */
    public function createUser(array $userData): User
    {
        $tenantId = $this->resolveTrustedTenantId($userData);

        return User::create([
            'name' => $userData['name'],
            'email' => $userData['email'],
            'password' => Hash::make($userData['password']),
            'tenant_id' => $tenantId,
        ]);
    }

    /**
     * Autentica a un usuario (login o registro)
     */
    public function authenticate(array $data): User
    {
        $tenantId = $this->resolveTrustedTenantId($data);

        $user = $this->findUserByEmail(
            $data['email'],
            $tenantId
        );

        if (! $user) {
            $user = $this->createUser($data);
            UserRegistered::dispatch($user->id, (int) $user->tenant_id);
        } elseif (! $this->validateCredentials($user, $data['password'])) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        $user->token = $this->createApiToken($user, 'auth-token');

        return $user;
    }

    /**
     * Revoca todos los tokens del usuario
     */
    public function revokeAllTokens(User $user): void
    {
        $user->tokens()->delete();
    }

    /**
     * Crea un nuevo token API para el usuario
     */
    public function createApiToken(User $user, string $tokenName): string
    {
        return $user->createToken($tokenName)->plainTextToken;
    }

    /**
     * Busca un usuario por email respetando el contexto de tenant cuando exista.
     *
     * Si no hay contexto de tenant y existen múltiples coincidencias, falla de forma
     * explícita para evitar autenticación cruzada entre tenants.
     */
    private function findUserByEmail(string $email, ?int $tenantId = null): ?User
    {
        $query = User::query()
            ->withoutGlobalScope('tenant')
            ->where('email', $email);

        if ($tenantId !== null) {
            /** @var ?User $user */
            $user = $query->where('tenant_id', $tenantId)->first();

            return $user;
        }

        $users = $query->get();

        if ($users->count() > 1) {
            throw ValidationException::withMessages([
                'tenant_id' => ['Se requiere contexto de tenant para autenticar usuarios con el mismo email en distintos tenants.'],
            ]);
        }

        /** @var ?User $user */
        $user = $users->first();

        return $user;
    }

    /**
     * Obtiene el tenant explícito de la request o del usuario autenticado.
     */
    private function resolveTrustedTenantId(array $data): int
    {
        $tenantId = $this->tenantContext->id() ?? Auth::user()?->tenant_id;

        if ($tenantId !== null) {
            return (int) $tenantId;
        }

        $bootstrapTenantId = $this->tenantContextResolver->resolveForPublicAuth($data);

        if ($bootstrapTenantId !== null) {
            return $bootstrapTenantId;
        }

        if (! Tenant::query()->exists()) {
            return Tenant::query()->create([
                'name' => 'Default Tenant',
                'slug' => 'default-tenant',
                'is_active' => true,
            ])->id;
        }

        throw ValidationException::withMessages([
            'tenant_id' => ['No se pudo resolver un tenant confiable para este flujo de autenticación.'],
        ]);
    }
}
