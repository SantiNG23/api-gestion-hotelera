<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Client;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationService extends Service
{
    public function __construct(
        private readonly PriceCalculatorService $priceCalculator,
        private readonly AvailabilityService $availabilityService
    ) {
        parent::__construct(new Reservation());
    }

    /**
     * Obtiene reservas con filtros aplicados
     */
    public function getReservations(array $params): LengthAwarePaginator
    {
        $query = $this->model->query()
            ->with(['client', 'cabin', 'guests', 'payments']);
        $query = $this->getFilteredAndSorted($query, $params);

        return $this->getAll($params['page'], $params['per_page'], $query);
    }

    /**
     * Obtiene una reserva por ID
     */
    public function getReservation(int $id): Reservation
    {
        return $this->getByIdWith($id, ['client', 'cabin', 'cabin.features', 'guests', 'payments']);
    }

    /**
     * Crea una nueva reserva
     *
     * @throws ValidationException
     */
    public function createReservation(array $data): Reservation
    {
        $checkIn = Carbon::parse($data['check_in_date']);
        $checkOut = Carbon::parse($data['check_out_date']);

        // Validar disponibilidad
        if (!$this->availabilityService->isAvailable($data['cabin_id'], $checkIn, $checkOut)) {
            throw ValidationException::withMessages([
                'cabin_id' => ['La cabaña no está disponible para las fechas seleccionadas'],
            ]);
        }

        // Calcular precios automáticamente (num_guests es obligatorio en el request)
        $numGuests = (int) $data['num_guests'];
        $priceDetails = $this->priceCalculator->calculatePrice($checkIn, $checkOut, $data['cabin_id'], $numGuests);

        return DB::transaction(function () use ($data, $priceDetails, $numGuests) {
            $tenantId = $data['tenant_id'] ?? Auth::user()->tenant_id;
            $client = $this->resolveClient(
                $tenantId,
                $data['client'] ?? null
            );

            $reservation = $this->create([
                'tenant_id' => $tenantId,
                'client_id' => $client->id,
                'cabin_id' => $data['cabin_id'],
                'num_guests' => $numGuests,
                'check_in_date' => $data['check_in_date'],
                'check_out_date' => $data['check_out_date'],
                'total_price' => $priceDetails['total'],
                'deposit_amount' => $priceDetails['deposit'],
                'balance_amount' => $priceDetails['balance'],
                'status' => Reservation::STATUS_PENDING_CONFIRMATION,
                'pending_until' => now()->addHours((int) ($data['pending_hours'] ?? 48)),
                'notes' => $data['notes'] ?? null,
            ]);

            // Crear huéspedes si se proporcionan
            if (!empty($data['guests'])) {
                $this->syncGuests($reservation, $data['guests']);
            }

            return $reservation->load(['client', 'cabin', 'guests']);
        });
    }

    /**
     * Actualiza una reserva existente
     *
     * @throws ValidationException
     */
    public function updateReservation(int $id, array $data): Reservation
    {
        $reservation = $this->getById($id);

        // No permitir editar reservas finalizadas o canceladas
        if ($reservation->isFinished() || $reservation->isCancelled()) {
            throw ValidationException::withMessages([
                'status' => ['No se puede modificar una reserva finalizada o cancelada'],
            ]);
        }

        // Si cambian las fechas o la cabaña, recalcular precio y validar disponibilidad
        $needsRecalculation = isset($data['check_in_date'])
            || isset($data['check_out_date'])
            || isset($data['cabin_id']);

        if ($needsRecalculation) {
            $checkIn = Carbon::parse($data['check_in_date'] ?? $reservation->check_in_date);
            $checkOut = Carbon::parse($data['check_out_date'] ?? $reservation->check_out_date);
            $cabinId = $data['cabin_id'] ?? $reservation->cabin_id;

            // Validar disponibilidad (excluyendo la reserva actual)
            if (!$this->availabilityService->isAvailable($cabinId, $checkIn, $checkOut, $reservation->id)) {
                throw ValidationException::withMessages([
                    'cabin_id' => ['La cabaña no está disponible para las fechas seleccionadas'],
                ]);
            }

            $numGuests = (int) ($data['num_guests'] ?? $reservation->num_guests ?? $reservation->guests()->count());
            // Fallback mínimo de 1 por seguridad, aunque debería estar en DB o request
            $numGuests = max(1, $numGuests);

            $priceDetails = $this->priceCalculator->calculatePrice($checkIn, $checkOut, (int) $cabinId, $numGuests);
            $data['total_price'] = $priceDetails['total'];
            $data['deposit_amount'] = $priceDetails['deposit'];
            $data['balance_amount'] = $priceDetails['balance'];
        }

        // Resolver cliente si se envía client
        if (isset($data['client'])) {
            $tenantId = $reservation->tenant_id ?? Auth::user()->tenant_id;
            $client = $this->resolveClient(
                $tenantId,
                $data['client']
            );
            $data['client_id'] = $client->id;
        }

        return DB::transaction(function () use ($reservation, $data) {
            // Actualizar campos permitidos
            $updateData = array_filter([
                'client_id' => $data['client_id'] ?? null,
                'cabin_id' => $data['cabin_id'] ?? null,
                'num_guests' => $data['num_guests'] ?? null,
                'check_in_date' => $data['check_in_date'] ?? null,
                'check_out_date' => $data['check_out_date'] ?? null,
                'total_price' => $data['total_price'] ?? null,
                'deposit_amount' => $data['deposit_amount'] ?? null,
                'balance_amount' => $data['balance_amount'] ?? null,
                'notes' => $data['notes'] ?? null,
            ], fn ($value) => $value !== null);

            if (!empty($updateData)) {
                $reservation->update($updateData);
            }

            // Actualizar huéspedes si se proporcionan
            if (isset($data['guests'])) {
                $this->syncGuests($reservation, $data['guests']);
            }

            return $reservation->fresh(['client', 'cabin', 'guests', 'payments']);
        });
    }

    /**
     * Confirma una reserva (pago de seña)
     *
     * @throws ValidationException
     */
    public function confirm(int $id, array $paymentData): Reservation
    {
        $reservation = $this->getById($id);

        if (!$reservation->isPendingConfirmation()) {
            throw ValidationException::withMessages([
                'status' => ['Solo se pueden confirmar reservas pendientes de confirmación'],
            ]);
        }

        if ($reservation->hasDepositPaid()) {
            throw ValidationException::withMessages([
                'payment' => ['La seña ya fue registrada'],
            ]);
        }

        return DB::transaction(function () use ($reservation, $paymentData) {
            // Registrar pago de seña
            ReservationPayment::create([
                'reservation_id' => $reservation->id,
                'amount' => $reservation->deposit_amount,
                'payment_type' => ReservationPayment::TYPE_DEPOSIT,
                'payment_method' => $paymentData['payment_method'] ?? null,
                'paid_at' => $paymentData['paid_at'] ?? now(),
            ]);

            // Actualizar estado
            $reservation->update([
                'status' => Reservation::STATUS_CONFIRMED,
                'pending_until' => null,
            ]);

            return $reservation->fresh(['client', 'cabin', 'guests', 'payments']);
        });
    }

    /**
     * Paga el saldo diferido de forma anticipada
     * El cliente paga antes de llegar, sin cambiar el estado de la reserva
     *
     * @throws ValidationException
     */
    public function payBalance(int $id, array $paymentData): Reservation
    {
        $reservation = $this->getById($id);

        // Solo se puede pagar el saldo si la reserva está confirmada
        if (!$reservation->isConfirmed()) {
            throw ValidationException::withMessages([
                'status' => ['Solo se puede pagar el saldo en reservas confirmadas'],
            ]);
        }

        if ($reservation->hasBalancePaid()) {
            throw ValidationException::withMessages([
                'payment' => ['El saldo ya fue registrado'],
            ]);
        }

        return DB::transaction(function () use ($reservation, $paymentData) {
            // Registrar pago de saldo (sin cambiar estado)
            ReservationPayment::create([
                'reservation_id' => $reservation->id,
                'amount' => $reservation->balance_amount,
                'payment_type' => ReservationPayment::TYPE_BALANCE,
                'payment_method' => $paymentData['payment_method'] ?? null,
                'paid_at' => $paymentData['paid_at'] ?? now(),
            ]);

            return $reservation->fresh(['client', 'cabin', 'guests', 'payments']);
        });
    }

    /**
     * Realiza el check-in (pago de saldo)
     * Flujo principal: cliente paga todo al llegar
     *
     * @throws ValidationException
     */
    public function checkIn(int $id, array $paymentData): Reservation
    {
        $reservation = $this->getById($id);

        if (!$reservation->isConfirmed()) {
            throw ValidationException::withMessages([
                'status' => ['Solo se puede hacer check-in en reservas confirmadas'],
            ]);
        }

        // Si el saldo ya fue pagado (pagó anticipadamente), solo cambiar estado
        if ($reservation->hasBalancePaid()) {
            $reservation->update([
                'status' => Reservation::STATUS_CHECKED_IN,
            ]);

            return $reservation->fresh(['client', 'cabin', 'guests', 'payments']);
        }

        // Si no, registrar el pago de saldo al momento del check-in
        return DB::transaction(function () use ($reservation, $paymentData) {
            // Registrar pago de saldo
            ReservationPayment::create([
                'reservation_id' => $reservation->id,
                'amount' => $reservation->balance_amount,
                'payment_type' => ReservationPayment::TYPE_BALANCE,
                'payment_method' => $paymentData['payment_method'] ?? null,
                'paid_at' => $paymentData['paid_at'] ?? now(),
            ]);

            // Actualizar estado
            $reservation->update([
                'status' => Reservation::STATUS_CHECKED_IN,
            ]);

            return $reservation->fresh(['client', 'cabin', 'guests', 'payments']);
        });
    }

    /**
     * Realiza el check-out
     *
     * @throws ValidationException
     */
    public function checkOut(int $id): Reservation
    {
        $reservation = $this->getById($id);

        if (!$reservation->isCheckedIn()) {
            throw ValidationException::withMessages([
                'status' => ['Solo se puede hacer check-out en reservas con check-in realizado'],
            ]);
        }

        $reservation->update([
            'status' => Reservation::STATUS_FINISHED,
        ]);

        return $reservation->fresh(['client', 'cabin', 'guests', 'payments']);
    }

    /**
     * Cancela una reserva
     *
     * @throws ValidationException
     */
    public function cancel(int $id): Reservation
    {
        $reservation = $this->getById($id);

        if ($reservation->isFinished()) {
            throw ValidationException::withMessages([
                'status' => ['No se puede cancelar una reserva finalizada'],
            ]);
        }

        if ($reservation->isCancelled()) {
            throw ValidationException::withMessages([
                'status' => ['La reserva ya está cancelada'],
            ]);
        }

        $reservation->update([
            'status' => Reservation::STATUS_CANCELLED,
            'pending_until' => null,
        ]);

        return $reservation->fresh(['client', 'cabin', 'guests', 'payments']);
    }

    /**
     * Elimina una reserva
     */
    public function deleteReservation(int $id): bool
    {
        return $this->delete($id);
    }

    /**
     * Genera una cotización
     */
    public function generateQuote(int $cabinId, string $checkIn, string $checkOut, int $numGuests): array
    {
        $checkInDate = Carbon::parse($checkIn);
        $checkOutDate = Carbon::parse($checkOut);

        // Verificar disponibilidad
        $isAvailable = $this->availabilityService->isAvailable($cabinId, $checkInDate, $checkOutDate);

        $quote = $this->priceCalculator->generateQuote($cabinId, $checkIn, $checkOut, $numGuests);
        $quote['is_available'] = $isAvailable;

        return $quote;
    }

    /**
     * Sincroniza huéspedes de una reserva
     */
    private function syncGuests(Reservation $reservation, array $guests): void
    {
        // Eliminar huéspedes existentes
        $reservation->guests()->delete();

        // Crear nuevos huéspedes
        foreach ($guests as $guest) {
            $reservation->guests()->create([
                'name' => $guest['name'],
                'dni' => $guest['dni'],
                'age' => $guest['age'] ?? null,
                'city' => $guest['city'] ?? null,
                'phone' => $guest['phone'] ?? null,
                'email' => $guest['email'] ?? null,
            ]);
        }
    }

    /**
     * Filtro por estado
     */
    protected function filterByStatus(Builder $query, string $value): Builder
    {
        return $query->where('status', $value);
    }

    /**
     * Filtro por cliente
     */
    protected function filterByClientId(Builder $query, int $value): Builder
    {
        return $query->where('client_id', $value);
    }

    /**
     * Filtro por cabaña
     */
    protected function filterByCabinId(Builder $query, int $value): Builder
    {
        return $query->where('cabin_id', $value);
    }

    /**
     * Filtro por fecha de check-in (desde X fecha)
     */
    protected function filterByCheckInDate(Builder $query, string $value): Builder
    {
        return $query->whereDate('check_in_date', $value);
    }

    /**
     * Filtro por fecha de check-out (hasta X fecha)
     */
    protected function filterByCheckOutDate(Builder $query, string $value): Builder
    {
        return $query->whereDate('check_out_date', $value);
    }

    /**
     * Campo de fecha para filtros de rango
     */
    protected function getDateColumn(): string
    {
        return 'check_in_date';
    }

    /**
     * Aplica un filtro de rango de fechas que devuelve reservas con días solapados
     *
     * Este método sobrescribe el del Service base para filtrar por intersección de fechas.
     * Una reserva se incluye si tiene al menos un día en común con el rango [start, end].
     *
     * @param  Builder  $query  La consulta a la que aplicar el filtro
     * @param  array  $dateRange  Array con las claves 'start' y 'end'
     * @param  string|null  $dateColumn  Ignorado en este contexto (se usan check_in_date y check_out_date)
     */
    protected function applyDateRangeFilter(Builder $query, array $dateRange, ?string $dateColumn = null): void
    {
        $start = $dateRange['start'] ?? null;
        $end = $dateRange['end'] ?? null;

        if (!empty($start) && !empty($end)) {
            // Ambas fechas: devolver reservas que se solapen con el rango
            // Una reserva se solapa si: check_in_date <= end AND check_out_date >= start
            $query->where(function ($q) use ($start, $end) {
                $q->whereDate('check_in_date', '<=', $end)
                    ->whereDate('check_out_date', '>=', $start);
            });
        } elseif (!empty($start)) {
            // Solo inicio: devolver reservas cuya check_out_date >= start
            $query->whereDate('check_out_date', '>=', $start);
        } elseif (!empty($end)) {
            // Solo fin: devolver reservas cuya check_in_date <= end
            $query->whereDate('check_in_date', '<=', $end);
        }
    }

    /**
     * Columnas para búsqueda global
     */
    protected function getGlobalSearchColumns(): array
    {
        return ['notes'];
    }

    /**
     * Relaciones para búsqueda global
     */
    protected function getGlobalSearchRelations(): array
    {
        return [
            'client' => ['name', 'dni'],
            'cabin' => ['name'],
        ];
    }

    /**
     * Obtiene o crea un cliente a partir de client_id o datos de cliente
     */
    private function resolveClient(int $tenantId, ?array $clientData): Client
    {
        if ($clientData === null) {
            throw ValidationException::withMessages([
                'client' => ['Los datos del cliente son obligatorios'],
            ]);
        }

        $existing = Client::where('tenant_id', $tenantId)
            ->where('dni', $clientData['dni'])
            ->first();

        if ($existing !== null) {
            // Sincronizar datos básicos cuando el cliente ya existe
            $updatableFields = ['name', 'age', 'city', 'phone', 'email'];
            $updateData = [];

            foreach ($updatableFields as $field) {
                if (!array_key_exists($field, $clientData)) {
                    continue;
                }

                $value = $clientData[$field];

                // No sobreescribimos con null ni con strings vacíos
                if ($value === null) {
                    continue;
                }
                if (is_string($value)) {
                    $value = trim($value);
                    if ($value === '') {
                        continue;
                    }
                }

                if ($existing->{$field} !== $value) {
                    $updateData[$field] = $value;
                }
            }

            if (!empty($updateData)) {
                $existing->update($updateData);
            }

            return $existing;
        }

        return Client::create([
            'tenant_id' => $tenantId,
            'name' => $clientData['name'],
            'dni' => $clientData['dni'],
            'age' => $clientData['age'] ?? null,
            'city' => $clientData['city'] ?? null,
            'phone' => $clientData['phone'] ?? null,
            'email' => $clientData['email'] ?? null,
        ]);
    }
}
