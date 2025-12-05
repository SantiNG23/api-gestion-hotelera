<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ReservationPaymentRequest;
use App\Http\Requests\ReservationQuoteRequest;
use App\Http\Requests\ReservationRequest;
use App\Http\Resources\ReservationResource;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function __construct(
        private readonly ReservationService $reservationService
    ) {}

    /**
     * Filtros permitidos para reservas
     */
    protected function getAllowedFilters(): array
    {
        return ['status', 'client_id', 'cabin_id', 'global'];
    }

    /**
     * Listar reservas
     */
    public function index(Request $request): JsonResponse
    {
        $params = $this->getQueryParams($request);
        $reservations = $this->reservationService->getReservations($params);

        return $this->paginatedResponse($reservations, ReservationResource::class);
    }

    /**
     * Crear reserva
     */
    public function store(ReservationRequest $request): JsonResponse
    {
        $reservation = $this->reservationService->createReservation($request->validated());

        return $this->successResponse(
            new ReservationResource($reservation),
            'Reserva creada exitosamente',
            201
        );
    }

    /**
     * Mostrar reserva
     */
    public function show(int $id): JsonResponse
    {
        $reservation = $this->reservationService->getReservation($id);

        return $this->successResponse(new ReservationResource($reservation));
    }

    /**
     * Actualizar reserva
     */
    public function update(ReservationRequest $request, int $id): JsonResponse
    {
        $reservation = $this->reservationService->updateReservation($id, $request->validated());

        return $this->successResponse(
            new ReservationResource($reservation),
            'Reserva actualizada exitosamente'
        );
    }

    /**
     * Eliminar reserva
     */
    public function destroy(int $id): JsonResponse
    {
        $this->reservationService->deleteReservation($id);

        return $this->successResponse(null, 'Reserva eliminada exitosamente');
    }

    /**
     * Generar cotización
     */
    public function quote(ReservationQuoteRequest $request): JsonResponse
    {
        $quote = $this->reservationService->generateQuote(
            $request->validated()['cabin_id'],
            $request->validated()['check_in_date'],
            $request->validated()['check_out_date']
        );

        return $this->successResponse($quote);
    }

    /**
     * Confirmar reserva (pago de seña)
     */
    public function confirm(ReservationPaymentRequest $request, int $id): JsonResponse
    {
        $reservation = $this->reservationService->confirm($id, $request->validated());

        return $this->successResponse(
            new ReservationResource($reservation),
            'Reserva confirmada exitosamente'
        );
    }

    /**
     * Check-in (pago de saldo)
     */
    public function checkIn(ReservationPaymentRequest $request, int $id): JsonResponse
    {
        $reservation = $this->reservationService->checkIn($id, $request->validated());

        return $this->successResponse(
            new ReservationResource($reservation),
            'Check-in realizado exitosamente'
        );
    }

    /**
     * Check-out
     */
    public function checkOut(int $id): JsonResponse
    {
        $reservation = $this->reservationService->checkOut($id);

        return $this->successResponse(
            new ReservationResource($reservation),
            'Check-out realizado exitosamente'
        );
    }

    /**
     * Cancelar reserva
     */
    public function cancel(int $id): JsonResponse
    {
        $reservation = $this->reservationService->cancel($id);

        return $this->successResponse(
            new ReservationResource($reservation),
            'Reserva cancelada exitosamente'
        );
    }
}

