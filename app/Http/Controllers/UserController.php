<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UserRequest;
use App\Http\Resources\AuthResource;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService
    ) {}

    /**
     * Obtener el perfil del usuario autenticado
     */
    public function profile(Request $request): JsonResponse
    {
        return $this->successResponse(
            $this->transformResource($request->user()),
            'Perfil obtenido exitosamente'
        );
    }

    /**
     * Actualizar el perfil del usuario
     */
    public function updateProfile(UserRequest $request): JsonResponse
    {
        $user = $this->userService->updateProfile($request->user(), $request->validated());

        return $this->successResponse(
            $this->transformResource($user),
            'Perfil actualizado exitosamente'
        );
    }

    /**
     * Actualizar la contraseña del usuario
     */
    public function updatePassword(UserRequest $request): JsonResponse
    {
        $this->userService->updatePassword($request->user(), $request->validated('password'));

        return $this->successResponse(null, 'Contraseña actualizada exitosamente');
    }
}
