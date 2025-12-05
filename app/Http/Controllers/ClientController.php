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
        $params = $this->getQueryParams($request);
        $clients = $this->clientService->getClients($params);

        return $this->paginatedResponse($clients, ClientResource::class);
    }

    /**
     * Crear cliente
     */
    public function store(ClientRequest $request): JsonResponse
    {
        $client = $this->clientService->createClient($request->validated());

        return $this->successResponse(
            new ClientResource($client),
            'Cliente creado exitosamente',
            201
        );
    }

    /**
     * Mostrar cliente
     */
    public function show(int $id): JsonResponse
    {
        $client = $this->clientService->getClientWithReservations($id);

        return $this->successResponse(new ClientResource($client));
    }

    /**
     * Actualizar cliente
     */
    public function update(ClientRequest $request, int $id): JsonResponse
    {
        $client = $this->clientService->updateClient($id, $request->validated());

        return $this->successResponse(
            new ClientResource($client),
            'Cliente actualizado exitosamente'
        );
    }

    /**
     * Eliminar cliente
     */
    public function destroy(int $id): JsonResponse
    {
        $this->clientService->deleteClient($id);

        return $this->successResponse(null, 'Cliente eliminado exitosamente');
    }

    /**
     * Buscar cliente por DNI
     */
    public function searchByDni(string $dni): JsonResponse
    {
        $client = $this->clientService->searchByDni($dni);

        if (!$client) {
            return $this->errorResponse('Cliente no encontrado', 404);
        }

        return $this->successResponse(new ClientResource($client));
    }
}

