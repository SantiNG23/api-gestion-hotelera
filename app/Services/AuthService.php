<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantContextResolver;
use Illuminate\Database\Eloquent\Collection;
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
        $tenant = Tenant::query()->findOrFail($tenantId);

        return $this->createUserForTenant($tenant, $userData);
    }

    public function createUserForTenant(Tenant $tenant, array $userData): User
    {
        return User::create([
            'name' => $userData['name'],
            'email' => $userData['email'],
            'password' => Hash::make($userData['password']),
            'role' => $userData['role'] ?? User::ROLE_STAFF,
            'tenant_id' => $tenant->id,
        ]);
    }

    public function discover(string $email): array
    {
        $tenants = Tenant::query()
            ->select(['tenants.id', 'tenants.slug', 'tenants.name'])
            ->join('users', 'users.tenant_id', '=', 'tenants.id')
            ->where('users.email', $email)
            ->where('tenants.is_active', true)
            ->distinct()
            ->orderBy('tenants.name')
            ->get();

        return [
            'mode' => $this->resolveDiscoverMode($tenants),
            'email' => $email,
            'tenants' => $tenants,
        ];
    }

    public function login(array $credentials): array
    {
        $tenant = $this->resolveTenantForLogin($credentials['tenant_slug'] ?? null);

        $user = $this->findUserByEmail($credentials['email'], $tenant->id);

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            $this->throwFunctionalError(
                'invalid_credentials',
                'email',
                'Las credenciales proporcionadas son incorrectas.'
            );
        }

        $user->loadMissing('tenant');

        return [
            'token' => $this->createApiToken($user, 'auth-token'),
            'user' => $user,
            'tenant' => $tenant,
        ];
    }

    public function bootstrap(User $user): User
    {
        return $user->loadMissing('tenant');
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

    private function resolveTenantForLogin(mixed $tenantSlug): Tenant
    {
        if (! is_string($tenantSlug) || $tenantSlug === '') {
            $this->throwFunctionalError('tenant_required', 'tenant_slug', 'Selecciona una cuenta para continuar.');
        }

        $tenant = Tenant::query()->where('slug', $tenantSlug)->first();

        if (! $tenant) {
            $this->throwFunctionalError('tenant_required', 'tenant_slug', 'Selecciona una cuenta para continuar.');
        }

        if (! $tenant->is_active) {
            $this->throwFunctionalError('inactive_tenant', 'tenant_slug', 'La cuenta seleccionada no esta disponible.');
        }

        return $tenant;
    }

    private function resolveDiscoverMode(Collection $tenants): string
    {
        return match ($tenants->count()) {
            0 => 'not_found',
            1 => 'single_tenant',
            default => 'multi_tenant',
        };
    }

    private function throwFunctionalError(string $code, string $field, string $message): never
    {
        throw ValidationException::withMessages([
            'code' => [$code],
            $field => [$message],
        ]);
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
