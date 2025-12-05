<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PriceGroup;
use App\Models\PriceRange;
use Carbon\Carbon;
use Carbon\CarbonPeriod;

/**
 * Servicio utilitario para calcular precios de reservas
 *
 * NO extiende Service porque no maneja CRUD de una entidad
 */
class PriceCalculatorService
{
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

        $breakdown = [];
        $total = 0;

        // Iterar por cada noche (desde check_in hasta el día antes de check_out)
        $period = CarbonPeriod::create($checkIn, $checkOut->copy()->subDay());

        foreach ($period as $date) {
            $pricePerNight = $this->getPriceForDate($date);
            $breakdown[] = [
                'date' => $date->format('Y-m-d'),
                'price' => $pricePerNight,
                'price_group' => $this->getPriceGroupNameForDate($date),
            ];
            $total += $pricePerNight;
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
     * Obtiene el precio por noche para una fecha específica
     */
    public function getPriceForDate(Carbon $date): float
    {
        // Buscar rango de precio que cubra esta fecha
        $priceRange = $this->getPriceRangeForDate($date);

        if ($priceRange) {
            return (float) $priceRange->priceGroup->price_per_night;
        }

        // Si no hay rango, usar el grupo por defecto
        $defaultGroup = $this->getDefaultPriceGroup();

        if ($defaultGroup) {
            return (float) $defaultGroup->price_per_night;
        }

        // Sin precio configurado, retornar 0
        return 0;
    }

    /**
     * Obtiene el nombre del grupo de precio para una fecha
     */
    public function getPriceGroupNameForDate(Carbon $date): ?string
    {
        $priceRange = $this->getPriceRangeForDate($date);

        if ($priceRange) {
            return $priceRange->priceGroup->name;
        }

        $defaultGroup = $this->getDefaultPriceGroup();

        return $defaultGroup?->name;
    }

    /**
     * Obtiene el rango de precio aplicable para una fecha
     */
    private function getPriceRangeForDate(Carbon $date): ?PriceRange
    {
        return PriceRange::whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->with('priceGroup')
            ->first();
    }

    /**
     * Obtiene el grupo de precio por defecto
     */
    private function getDefaultPriceGroup(): ?PriceGroup
    {
        return PriceGroup::where('is_default', true)->first();
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
