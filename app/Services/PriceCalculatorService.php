<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CabinPriceByGuests;
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
     * @param  int|null  $cabinId  ID de la cabaña (opcional para precios específicos por cabaña y huéspedes)
     * @param  int|null  $numGuests  Cantidad de huéspedes (opcional para precios específicos por cabaña y huéspedes)
     * @return array{total: float, deposit: float, balance: float, nights: int, breakdown: array}
     */
    public function calculatePrice(Carbon $checkIn, Carbon $checkOut, ?int $cabinId = null, ?int $numGuests = null): array
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
            $pricePerNight = $this->getPriceForDate($date, $cabinId, $numGuests);
            $breakdown[] = [
                'date' => $date->format('Y-m-d'),
                'price' => $pricePerNight,
                'price_group' => $this->getPriceGroupNameForDate($date, $cabinId, $numGuests),
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
     *
     * @param  Carbon  $date  Fecha para la cual obtener el precio
     * @param  int|null  $cabinId  ID de la cabaña (opcional)
     * @param  int|null  $numGuests  Cantidad de huéspedes (opcional)
     */
    public function getPriceForDate(Carbon $date, ?int $cabinId = null, ?int $numGuests = null): float
    {
        // Si se proporciona cabinId y numGuests, buscar precio específico por cabaña y cantidad de huéspedes
        if ($cabinId !== null && $numGuests !== null) {
            $cabinPrice = $this->getPriceByCabinAndGuests($cabinId, $date, $numGuests);
            if ($cabinPrice > 0) {
                return $cabinPrice;
            }
        }

        // Si solo se proporciona cabinId, buscar precio de la cabaña en el rango de precio
        if ($cabinId !== null) {
            $cabinPrice = $this->getPriceByCabin($cabinId, $date);
            if ($cabinPrice > 0) {
                return $cabinPrice;
            }
        }

        // Fallback a rango de precio por fecha
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
     * Obtiene el precio específico de una cabaña para una cantidad de huéspedes en una fecha
     *
     * @param  int  $cabinId  ID de la cabaña
     * @param  Carbon  $date  Fecha para obtener el grupo de precio
     * @param  int  $numGuests  Cantidad de huéspedes
     */
    private function getPriceByCabinAndGuests(int $cabinId, Carbon $date, int $numGuests): float
    {
        // Obtener el grupo de precio para esta fecha
        $priceGroup = $this->getPriceGroupForDate($date);

        if (!$priceGroup) {
            return 0;
        }

        // Buscar el precio específico para la cabaña, cantidad de huéspedes y grupo de precio
        $cabinPriceByGuests = CabinPriceByGuests::where('cabin_id', $cabinId)
            ->where('price_group_id', $priceGroup->id)
            ->where('num_guests', $numGuests)
            ->first();

        return $cabinPriceByGuests ? (float) $cabinPriceByGuests->price_per_night : 0;
    }

    /**
     * Obtiene el precio de una cabaña para una fecha específica (si está configurado)
     *
     * @param  int  $cabinId  ID de la cabaña
     * @param  Carbon  $date  Fecha para obtener el grupo de precio
     */
    private function getPriceByCabin(int $cabinId, Carbon $date): float
    {
        // Obtener el grupo de precio para esta fecha
        $priceGroup = $this->getPriceGroupForDate($date);

        if (!$priceGroup) {
            return 0;
        }

        // Buscar cualquier precio específico para la cabaña en este grupo de precio
        // Retornamos el precio si existe, aunque no sea para cantidad específica de huéspedes
        $cabinPrice = CabinPriceByGuests::where('cabin_id', $cabinId)
            ->where('price_group_id', $priceGroup->id)
            ->first();

        return $cabinPrice ? (float) $cabinPrice->price_per_night : 0;
    }

    /**
     * Obtiene el nombre del grupo de precio para una fecha
     *
     * @param  Carbon  $date  Fecha para la cual obtener el nombre del grupo
     * @param  int|null  $cabinId  ID de la cabaña (opcional)
     * @param  int|null  $numGuests  Cantidad de huéspedes (opcional)
     */
    public function getPriceGroupNameForDate(Carbon $date, ?int $cabinId = null, ?int $numGuests = null): ?string
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
     * Obtiene el grupo de precio para una fecha específica
     */
    private function getPriceGroupForDate(Carbon $date): ?PriceGroup
    {
        $priceRange = $this->getPriceRangeForDate($date);

        if ($priceRange) {
            return $priceRange->priceGroup;
        }

        return $this->getDefaultPriceGroup();
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
     * @param  int  $cabinId  ID de la cabaña
     * @param  string  $checkIn  Fecha de check-in
     * @param  string  $checkOut  Fecha de check-out
     * @param  int|null  $numGuests  Cantidad de huéspedes (opcional para usar precios por cabaña y huéspedes)
     * @return array{cabin_id: int, check_in: string, check_out: string, total: float, deposit: float, balance: float, nights: int, breakdown: array}
     */
    public function generateQuote(int $cabinId, string $checkIn, string $checkOut, ?int $numGuests = null): array
    {
        $checkInDate = Carbon::parse($checkIn);
        $checkOutDate = Carbon::parse($checkOut);

        $priceDetails = $this->calculatePrice($checkInDate, $checkOutDate, $cabinId, $numGuests);

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
