<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AuthRequest;
use App\Http\Resources\AuthResource;
use App\Http\Resources\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService
    ) {}

    /**
     * Autenticación de usuario (login o registro)
     */
    public function store(AuthRequest $request): JsonResponse
    {
        $user = $this->authService->authenticate($request->validated());
        $isNew = $user->wasRecentlyCreated;

        return $this->successResponse(
            new AuthResource($user),
            $isNew ? 'Usuario registrado exitosamente' : 'Usuario autenticado exitosamente',
            $isNew ? 201 : 200
        );
    }

    /**
     * Logout del usuario (revocar token)
     */
    public function destroy(Request $request): JsonResponse
    {
        $this->authService->revokeAllTokens($request->user());

        return $this->successResponse(null, 'Sesión cerrada exitosamente');
    }

    /**
     * Obtener el usuario autenticado
     */
    public function show(Request $request): JsonResponse
    {
        return $this->successResponse(
            new UserResource($request->user()),
            'Usuario obtenido exitosamente'
        );
    }
}
