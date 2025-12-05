<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\AvailabilityCalendarRequest;
use App\Http\Requests\AvailabilityCheckRequest;
use App\Http\Requests\AvailabilityShowRequest;
use App\Http\Resources\CabinResource;
use App\Models\Cabin;
use App\Services\AvailabilityService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AvailabilityController extends Controller
{
    public function __construct(
        private readonly AvailabilityService $availabilityService
    ) {
    }

    /**
     * Verificar disponibilidad
     *
     * Si se proporciona cabin_id, verifica esa cabaña específica.
     * Si no, devuelve la lista de cabañas disponibles.
     */
    public function check(AvailabilityCheckRequest $request): JsonResponse
    {
        try {
            $checkIn = Carbon::parse($request->input('check_in_date'));
            $checkOut = Carbon::parse($request->input('check_out_date'));

            // Si se especifica cabaña, verificar disponibilidad
            if ($request->input('cabin_id')) {
                $isAvailable = $this->availabilityService->isAvailable(
                    (int) $request->input('cabin_id'),
                    $checkIn,
                    $checkOut
                );

                return $this->successResponse([
                    'cabin_id' => (int) $request->input('cabin_id'),
                    'check_in_date' => $checkIn->format('Y-m-d'),
                    'check_out_date' => $checkOut->format('Y-m-d'),
                    'is_available' => $isAvailable,
                ]);
            }

            // Si no se especifica cabaña, devolver las disponibles
            $availableCabins = $this->availabilityService->getAvailableCabins($checkIn, $checkOut);

            return $this->successResponse([
                'check_in_date' => $checkIn->format('Y-m-d'),
                'check_out_date' => $checkOut->format('Y-m-d'),
                'available_cabins' => CabinResource::collection($availableCabins),
                'available_count' => $availableCabins->count(),
            ]);
        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Obtiene los rangos bloqueados de una cabaña específica
     *
     * GET /availability/{cabin_id}?from=2025-01-01&to=2025-01-31
     */
    public function show(int $cabinId, AvailabilityShowRequest $request): JsonResponse
    {
        try {
            // Verificar que la cabaña existe
            Cabin::findOrFail($cabinId);

            $from = Carbon::parse($request->input('from'));
            $to = Carbon::parse($request->input('to'));

            $blockedRanges = $this->availabilityService->getBlockedRanges($cabinId, $from, $to);

            return $this->successResponse($blockedRanges);
        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }

    /**
     * Obtiene el calendario de disponibilidad con vista día a día
     *
     * GET /availability/calendar?from=2025-01-01&to=2025-01-31
     */
    public function calendar(AvailabilityCalendarRequest $request): JsonResponse
    {
        try {
            $from = Carbon::parse($request->input('from'));
            $to = Carbon::parse($request->input('to'));

            $calendar = $this->availabilityService->getCalendarDays($from, $to);

            return $this->successResponse($calendar);
        } catch (\Exception $e) {
            return $this->handleError($e);
        }
    }
}
