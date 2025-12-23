<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ClientRequest;
use App\Http\Resources\ClientResource;
use App\Services\ClientService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function __construct(
        private readonly ClientService $clientService
    ) {}

    /**
     * Filtros permitidos para clientes
     */
    protected function getAllowedFilters(): array
    {
        return ['name', 'dni', 'city', 'global'];
    }

    /**
     * Listar clientes
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $params = $this->getQueryParams($request);
            $clients = $this->clientService->getClients($params);

            return $this->paginatedResponse($clients, ClientResource::class);
        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Crear cliente
     */
    public function store(ClientRequest $request): JsonResponse
    {
        try {
            $client = $this->clientService->createClient($request->validated());

            return $this->successResponse(
                $this->transformResource($client),
                'Cliente creado exitosamente',
                201
            );
        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Mostrar cliente
     */
    public function show(int $id): JsonResponse
    {
        try {
            $client = $this->clientService->getClientWithReservations($id);

            return $this->successResponse($this->transformResource($client));
        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Actualizar cliente
     */
    public function update(ClientRequest $request, int $id): JsonResponse
    {
        try {
            $client = $this->clientService->updateClient($id, $request->validated());

            return $this->successResponse(
                $this->transformResource($client),
                'Cliente actualizado exitosamente'
            );
        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Eliminar cliente
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->clientService->deleteClient($id);

            return $this->successResponse(null, 'Cliente eliminado exitosamente');
        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Buscar cliente por DNI
     */
    public function searchByDni(string $dni): JsonResponse
    {
        try {
            $client = $this->clientService->searchByDni($dni);

            if (!$client) {
                return $this->errorResponse('Cliente no encontrado', 404);
            }

            return $this->successResponse($this->transformResource($client));
        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }
}

