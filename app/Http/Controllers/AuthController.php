<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AuthDiscoverRequest;
use App\Http\Requests\AuthLoginRequest;
use App\Http\Resources\AuthDiscoverResource;
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

    public function discover(AuthDiscoverRequest $request): JsonResponse
    {
        $payload = $this->authService->discover($request->validated('email'));

        return $this->successResponse(
            new AuthDiscoverResource($payload),
            $this->discoverMessage($payload['mode'])
        );
    }

    public function login(AuthLoginRequest $request): JsonResponse
    {
        return $this->loginResponse($request->validated());
    }

    /**
     * Logout del usuario (revocar token)
     */
    public function destroy(Request $request): JsonResponse
    {
        $this->authService->revokeAllTokens($request->user());

        return $this->successResponse(null, 'Sesión cerrada exitosamente');
    }

    public function show(Request $request): JsonResponse
    {
        return $this->successResponse(
            new UserResource($this->authService->bootstrap($request->user())),
            'Usuario obtenido exitosamente'
        );
    }

    private function loginResponse(array $credentials): JsonResponse
    {
        return $this->successResponse(
            new AuthResource($this->authService->login($credentials)),
            'Usuario autenticado exitosamente'
        );
    }

    private function discoverMessage(string $mode): string
    {
        return match ($mode) {
            'not_found' => 'No se encontraron accesos para ese correo.',
            'single_tenant' => 'Acceso encontrado.',
            'multi_tenant' => 'Selecciona un tenant para continuar.',
            default => 'Operacion exitosa',
        };
    }
}
