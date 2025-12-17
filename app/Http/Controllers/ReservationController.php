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
    public function calculatePrice(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cabin_id' => 'required|integer|exists:cabins,id',
            'check_in_date' => 'required|date|date_format:Y-m-d|after_or_equal:today',
            'check_out_date' => 'required|date|date_format:Y-m-d|after:check_in_date',
            'num_guests' => 'required|integer|min:1|max:255',
        ]);

        $cabin = \App\Models\Cabin::where('tenant_id', auth()->user()->tenant_id)
            ->findOrFail($validated['cabin_id']);

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

        $checkIn = new \DateTime($validated['check_in_date']);
        $checkOut = new \DateTime($validated['check_out_date']);
        $nights = $checkIn->diff($checkOut)->days;

        // Calcular precio por noche para cada día
        $pricingBreakdown = [];
        $totalPrice = 0;
        $currentDate = clone $checkIn;

        for ($i = 0; $i < $nights; $i++) {
            $dateStr = $currentDate->format('Y-m-d');
            
            // Buscar el precio aplicable para esta fecha
            $priceInfo = $this->getPriceForDate(
                $cabin->id,
                $dateStr,
                $validated['num_guests']
            );

            $pricingBreakdown[] = [
                'date' => $dateStr,
                'price' => $priceInfo['price'],
                'price_group_id' => $priceInfo['price_group_id'],
                'price_group_name' => $priceInfo['price_group_name'],
            ];

            $totalPrice += $priceInfo['price'];
            $currentDate->modify('+1 day');
        }

        // Calcular seña y saldo (30% - 70%)
        $depositAmount = round($totalPrice * 0.30, 2);
        $balanceAmount = round($totalPrice - $depositAmount, 2);

        return $this->successResponse([
            'cabin_id' => $cabin->id,
            'cabin_name' => $cabin->name,
            'check_in_date' => $validated['check_in_date'],
            'check_out_date' => $validated['check_out_date'],
            'num_guests' => $validated['num_guests'],
            'nights' => $nights,
            'price_per_night' => count($pricingBreakdown) > 0 ? $pricingBreakdown[0]['price'] : 0,
            'total_price' => $totalPrice,
            'deposit_amount' => $depositAmount,
            'balance_amount' => $balanceAmount,
            'pricing_breakdown' => $pricingBreakdown,
        ]);
    }

    /**
     * Obtener el precio para una fecha específica
     */
    private function getPriceForDate(int $cabinId, string $date, int $numGuests): array
    {
        // 1. Buscar rangos de precio que contengan esta fecha
        $priceRange = \App\Models\PriceRange::where('tenant_id', auth()->user()->tenant_id)
            ->where('start_date', '<=', $date)
            ->where('end_date', '>=', $date)
            ->with('priceGroup')
            ->first();

        // 2. Si existe un rango, buscar el precio específico
        if ($priceRange) {
            $cabinPrice = \App\Models\CabinPriceByGuests::where('tenant_id', auth()->user()->tenant_id)
                ->where('cabin_id', $cabinId)
                ->where('price_group_id', $priceRange->price_group_id)
                ->where('num_guests', $numGuests)
                ->first();

            if ($cabinPrice) {
                return [
                    'price' => (float) $cabinPrice->price_per_night,
                    'price_group_id' => $priceRange->price_group_id,
                    'price_group_name' => $priceRange->priceGroup->name,
                ];
            }
        }

        // 3. Si no hay rango, buscar el grupo por defecto
        $defaultGroup = \App\Models\PriceGroup::where('tenant_id', auth()->user()->tenant_id)
            ->where('is_default', true)
            ->first();

        if ($defaultGroup) {
            $cabinPrice = \App\Models\CabinPriceByGuests::where('tenant_id', auth()->user()->tenant_id)
                ->where('cabin_id', $cabinId)
                ->where('price_group_id', $defaultGroup->id)
                ->where('num_guests', $numGuests)
                ->first();

            if ($cabinPrice) {
                return [
                    'price' => (float) $cabinPrice->price_per_night,
                    'price_group_id' => $defaultGroup->id,
                    'price_group_name' => $defaultGroup->name,
                ];
            }
        }

        // 4. Si no se encontró ningún precio, lanzar error
        throw new \Exception(
            "No se encontró un precio configurado para {$numGuests} huéspedes en la fecha {$date}"
        );
    }
}
