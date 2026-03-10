<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\UserRegistered;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
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
        return User::create([
            'name' => $userData['name'],
            'email' => $userData['email'],
            'password' => Hash::make($userData['password']),
            'tenant_id' => $this->resolveTenantIdForRegistration($userData),
        ]);
    }

    /**
     * Autentica a un usuario (login o registro)
     */
    public function authenticate(array $data): User
    {
        $user = $this->findUserByEmail(
            $data['email'],
            $this->resolveRequestedTenantId($data)
        );

        if (! $user) {
            $user = $this->createUser($data);
            UserRegistered::dispatch($user);
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
    private function resolveRequestedTenantId(array $data): ?int
    {
        $tenantId = $data['tenant_id'] ?? Auth::user()?->tenant_id;

        return $tenantId !== null ? (int) $tenantId : null;
    }

    /**
     * Resuelve el tenant para el registro público.
     *
     * - Si llega `tenant_id`, se usa.
     * - Si hay usuario autenticado, se reutiliza su tenant.
     * - Si no existen tenants, se crea uno por defecto para mantener compatibilidad.
     * - Si existe exactamente un tenant, se usa ese.
     * - Si existen múltiples tenants sin contexto, se rechaza por ambigüedad.
     */
    private function resolveTenantIdForRegistration(array $data): int
    {
        $tenantId = $this->resolveRequestedTenantId($data);

        if ($tenantId !== null) {
            return $tenantId;
        }

        $tenantCount = Tenant::query()->count();

        if ($tenantCount === 0) {
            return Tenant::query()->create([
                'name' => 'Default Tenant',
                'slug' => 'default-tenant',
                'is_active' => true,
            ])->id;
        }

        if ($tenantCount === 1) {
            return (int) Tenant::query()->value('id');
        }

        throw ValidationException::withMessages([
            'tenant_id' => ['Se requiere contexto de tenant para registrar usuarios cuando existen múltiples tenants.'],
        ]);
    }
}
