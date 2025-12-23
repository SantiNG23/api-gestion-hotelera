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
use App\Services\PriceCalculatorService;
use App\Models\Cabin;

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
        return ['status', 'client_id', 'cabin_id', 'check_in_date', 'check_out_date', 'global'];
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
            (int) $request->validated()['cabin_id'],
            $request->validated()['check_in_date'],
            $request->validated()['check_out_date'],
            (int) $request->validated()['num_guests']
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
     * Pagar saldo diferido (puede ser antes del check-in)
     */
    public function payBalance(ReservationPaymentRequest $request, int $id): JsonResponse
    {
        $reservation = $this->reservationService->payBalance($id, $request->validated());

        return $this->successResponse(
            new ReservationResource($reservation),
            'Saldo pagado exitosamente'
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

    /**
     * Calcular precio de reserva basado en cabaña, fechas y cantidad de huéspedes
     */
    public function calculatePrice(Request $request, PriceCalculatorService $priceCalculator): JsonResponse
    {
        $validated = $request->validate([
            'cabin_id' => 'required|integer|exists:cabins,id',
            'check_in_date' => 'required|date|date_format:Y-m-d|after_or_equal:today',
            'check_out_date' => 'required|date|date_format:Y-m-d|after:check_in_date',
            'num_guests' => 'required|integer|min:2|max:255',
        ]);

        $cabin = Cabin::findOrFail($validated['cabin_id']);

        // Validar que num_guests no exceda la capacidad
        if ($validated['num_guests'] > $cabin->capacity) {
            return response()->json([
                'success' => false,
                'message' => 'La cantidad de huéspedes excede la capacidad de la cabaña',
                'errors' => [
                    'num_guests' => [
                        "La cabaña '{$cabin->name}' tiene capacidad para {$cabin->capacity} personas máximo"
                    ]
                ]
            ], 422);
        }

        $checkIn = \Carbon\Carbon::parse($validated['check_in_date']);
        $checkOut = \Carbon\Carbon::parse($validated['check_out_date']);

        $priceDetails = $priceCalculator->calculatePrice($checkIn, $checkOut, $cabin->id, (int) $validated['num_guests']);

        return $this->successResponse([
            'cabin_id' => $cabin->id,
            'cabin_name' => $cabin->name,
            'check_in_date' => $validated['check_in_date'],
            'check_out_date' => $validated['check_out_date'],
            'num_guests' => $validated['num_guests'],
            'nights' => $priceDetails['nights'],
            'total_price' => $priceDetails['total'],
            'deposit_amount' => $priceDetails['deposit'],
            'balance_amount' => $priceDetails['balance'],
            'pricing_breakdown' => $priceDetails['breakdown'],
        ]);
    }

}
