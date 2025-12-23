<?php

declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;

/**
 * Servicio utilitario para calcular precios de reservas
 *
 * NO extiende Service porque no maneja CRUD de una entidad
 */
class PriceCalculatorService
{
    public function __construct(
        private readonly PriceRangeService $priceRangeService
    ) {
    }

    /**
     * Calcula el precio total y el desglose por noche
     *
     * @param  Carbon  $checkIn  Fecha de check-in
     * @param  Carbon  $checkOut  Fecha de check-out
     * @return array{total: float, deposit: float, balance: float, nights: int, breakdown: array}
     */
    public function calculatePrice(Carbon $checkIn, Carbon $checkOut): array
    {
        $nights = $checkIn->diffInDays($checkOut);

        if ($nights < 1) {
            return [
                'total' => 0,
                'deposit' => 0,
                'balance' => 0,
                'nights' => 0,
                'breakdown' => [],
            ];
        }

        // Obtener todas las tarifas aplicables en un solo paso (Adiós N+1)
        $rates = $this->priceRangeService->getApplicableRates(
            $checkIn->format('Y-m-d'),
            $checkOut->copy()->subDay()->format('Y-m-d')
        );

        $breakdown = [];
        $total = 0;

        foreach ($rates as $date => $rate) {
            $breakdown[] = [
                'date' => $date,
                'price' => $rate['price'],
                'price_group' => $rate['group_name'],
            ];
            $total += $rate['price'];
        }

        $deposit = round($total * 0.5, 2);
        $balance = round($total - $deposit, 2);

        return [
            'total' => round($total, 2),
            'deposit' => $deposit,
            'balance' => $balance,
            'nights' => $nights,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Genera una cotización para una reserva
     *
     * @return array{cabin_id: int, check_in: string, check_out: string, total: float, deposit: float, balance: float, nights: int, breakdown: array}
     */
    public function generateQuote(int $cabinId, string $checkIn, string $checkOut): array
    {
        $checkInDate = Carbon::parse($checkIn);
        $checkOutDate = Carbon::parse($checkOut);

        $priceDetails = $this->calculatePrice($checkInDate, $checkOutDate);

        return [
            'cabin_id' => $cabinId,
            'check_in' => $checkIn,
            'check_out' => $checkOut,
            'total' => (float) $priceDetails['total'],
            'deposit' => (float) $priceDetails['deposit'],
            'balance' => (float) $priceDetails['balance'],
            'nights' => (int) $priceDetails['nights'],
            'breakdown' => $priceDetails['breakdown'],
        ];
    }
}
