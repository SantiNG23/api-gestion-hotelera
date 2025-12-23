<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Events\UserRegistered;

class AuthService
{
    /**
     * Valida las credenciales del usuario
     */
    public function validateCredentials(string $email, string $password): bool
    {
        $user = User::where('email', $email)->first();

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
        ]);
    }

    /**
     * Autentica a un usuario (login o registro)
     */
    public function authenticate(array $data): User
    {
        $user = User::where('email', $data['email'])->first();

        if (!$user) {
            $user = $this->createUser($data);
            UserRegistered::dispatch($user);
        } elseif (!$this->validateCredentials($data['email'], $data['password'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.']
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
}
