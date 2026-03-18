<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\ReservationCreated;
use App\Models\Cabin;
use App\Models\Client;
use App\Models\Reservation;
use App\Models\ReservationPayment;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReservationService extends Service
{
    public function __construct(
        private readonly PriceCalculatorService $priceCalculator,
        private readonly AvailabilityService $availabilityService,
        private readonly ClientService $clientService
    ) {
        parent::__construct(new Reservation);
    }

    /**
     * Obtiene reservas con filtros aplicados
     */
    public function getReservations(array $params): LengthAwarePaginator
    {
        $query = $this->model->query()
            ->with([
                'client' => fn ($query) => $query->withTrashed(),
                'cabin' => fn ($query) => $query->withTrashed(),
                'guests',
                'payments',
            ]);
        $query = $this->getFilteredAndSorted($query, $params);

        return $this->getAll($params['page'], $params['per_page'], $query);
    }

    public function getReservationsReport(array $params): LengthAwarePaginator
    {
        $query = $this->model->query()
            ->with([
                'client' => fn ($query) => $query->withTrashed(),
                'cabin' => fn ($query) => $query->withTrashed(),
                'payments',
            ]);

        $query = $this->getFilteredAndSorted($query, $params);

        return $this->getAll($params['page'], $params['per_page'], $query);
    }

    /**
     * Obtiene una reserva por ID
     */
    public function getReservation(int $id): Reservation
    {
        /** @var Reservation $reservation */
        $reservation = $this->getByIdWith($id, [
            'client' => fn ($query) => $query->withTrashed(),
            'cabin' => fn ($query) => $query->withTrashed()->with('features'),
            'guests',
            'payments',
        ]);

        return $reservation;
    }

    /**
     * Crea una nueva reserva
     *
     * @throws ValidationException
     */
    public function createReservation(array $data): Reservation
    {
        $tenantId = $this->requireTenantId();
        $this->guardTenantOverride($data, $tenantId);

        $checkIn = Carbon::parse($data['check_in_date']);
        $checkOut = Carbon::parse($data['check_out_date']);
        $cabin = Cabin::findOrFail((int) $data['cabin_id']);

        // Validar disponibilidad
        if (! $this->availabilityService->isAvailable($data['cabin_id'], $checkIn, $checkOut)) {
            throw ValidationException::withMessages([
                'cabin_id' => ['La cabaña no está disponible para las fechas seleccionadas'],
            ]);
        }

        // Calcular precios automáticamente (num_guests es obligatorio en el request si no es bloqueo)
        $isBlocked = (bool) ($data['is_blocked'] ?? false);
        $numGuests = (int) ($data['num_guests'] ?? 2);

        $priceDetails = $isBlocked
            ? ['total' => 0, 'deposit' => 0, 'balance' => 0]
            : $this->priceCalculator->calculateReservablePrice($checkIn, $checkOut, $cabin, $numGuests);

        return DB::transaction(function () use ($data, $priceDetails, $isBlocked, $numGuests, $tenantId) {
            // Si es un bloqueo, forzar el cliente técnico de bloqueo
            if ($isBlocked) {
                $data['client'] = [
                    'name' => 'BLOQUEO DE FECHAS',
                    'dni' => Client::DNI_BLOCK,
                ];
            }

            $client = $this->resolveClient(
                $tenantId,
                $data['client'] ?? null
            );

            /** @var Reservation $reservation */
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
                'pending_until' => $isBlocked ? null : now()->addHours((int) ($data['pending_hours'] ?? 48)),
                'notes' => $data['notes'] ?? null,
                'is_blocked' => $isBlocked,
            ]);

            // Crear huéspedes si se proporcionan
            if (! empty($data['guests'])) {
                $this->syncGuests($reservation, $data['guests']);
            }

            $reservation = $reservation->load([
                'client' => fn ($query) => $query->withTrashed(),
                'cabin' => fn ($query) => $query->withTrashed(),
                'guests',
            ]);

            ReservationCreated::dispatch($reservation->id, $tenantId);

            return $reservation;
        });
    }

    /**
     * Actualiza una reserva existente
     *
     * @throws ValidationException
     */
    public function updateReservation(int $id, array $data): Reservation
    {
        /** @var Reservation $reservation */
        $reservation = $this->getById($id);
        $tenantId = $this->requireTenantId();
        $this->guardTenantOverride($data, $tenantId);

        // No permitir editar reservas finalizadas o canceladas
        if ($reservation->isFinished() || $reservation->isCancelled()) {
            throw ValidationException::withMessages([
                'status' => ['No se puede modificar una reserva finalizada o cancelada'],
            ]);
        }

        // Si cambian las fechas, la cabaña o el estado de bloqueo, recalcular precio y validar disponibilidad
        $needsRecalculation = isset($data['check_in_date'])
            || isset($data['check_out_date'])
            || isset($data['cabin_id'])
            || isset($data['is_blocked'])
            || isset($data['num_guests']);

        if ($needsRecalculation) {
            $checkIn = Carbon::parse($data['check_in_date'] ?? $reservation->check_in_date);
            $checkOut = Carbon::parse($data['check_out_date'] ?? $reservation->check_out_date);
            $cabinId = $data['cabin_id'] ?? $reservation->cabin_id;
            $isBlocked = (bool) ($data['is_blocked'] ?? $reservation->is_blocked);
            $numGuests = (int) ($data['num_guests'] ?? $reservation->num_guests ?? 2);
            $numGuests = max(1, $numGuests);
            $cabin = Cabin::findOrFail((int) $cabinId);

            // Validar disponibilidad (excluyendo la reserva actual)
            if (! $this->availabilityService->isAvailable((int) $cabinId, $checkIn, $checkOut, $reservation->id)) {
                throw ValidationException::withMessages([
                    'cabin_id' => ['La cabaña no está disponible para las fechas seleccionadas'],
                ]);
            }

            if (! $isBlocked) {
                $this->priceCalculator->ensureGuestCapacityFitsCabin($cabin, $numGuests);
            }

            // Si está bloqueada, precios en 0; si no, calcular normalmente
            $priceDetails = $isBlocked
                ? ['total' => 0, 'deposit' => 0, 'balance' => 0]
                : $this->priceCalculator->calculateReservablePrice($checkIn, $checkOut, $cabin, $numGuests);

            $data['total_price'] = $priceDetails['total'];
            $data['deposit_amount'] = $priceDetails['deposit'];
            $data['balance_amount'] = $priceDetails['balance'];
            $data['is_blocked'] = $isBlocked;
            $data['num_guests'] = $numGuests;
            $data['pending_until'] = $isBlocked
                ? null
                : Carbon::now()->addHours((int) ($data['pending_hours'] ?? 48));

            // Si es un bloqueo, forzar el cliente técnico de bloqueo
            if ($isBlocked) {
                $data['client'] = [
                    'name' => 'BLOQUEO DE FECHAS',
                    'dni' => Client::DNI_BLOCK,
                ];
            }
        }

        // Resolver cliente si se envía client (o si lo forzamos arriba por ser bloqueo)
        if (isset($data['client'])) {
            $client = $this->resolveClient(
                $tenantId,
                $data['client']
            );
            $data['client_id'] = $client->id;
        }

        return DB::transaction(function () use ($reservation, $data) {
            // Actualizar campos permitidos
            $rawUpdateData = [
                'client_id' => $data['client_id'] ?? null,
                'cabin_id' => $data['cabin_id'] ?? null,
                'num_guests' => $data['num_guests'] ?? null,
                'check_in_date' => $data['check_in_date'] ?? null,
                'check_out_date' => $data['check_out_date'] ?? null,
                'total_price' => $data['total_price'] ?? null,
                'deposit_amount' => $data['deposit_amount'] ?? null,
                'balance_amount' => $data['balance_amount'] ?? null,
                'pending_until' => array_key_exists('pending_until', $data) ? $data['pending_until'] : null,
                'notes' => $data['notes'] ?? null,
                'is_blocked' => $data['is_blocked'] ?? null,
            ];

            $updateData = [];
            foreach ($rawUpdateData as $field => $value) {
                if ($value !== null || ($field === 'pending_until' && array_key_exists('pending_until', $data))) {
                    $updateData[$field] = $value;
                }
            }

            if (! empty($updateData)) {
                $reservation->update($updateData);
            }

            // Actualizar huéspedes si se proporcionan
            if (isset($data['guests'])) {
                $this->syncGuests($reservation, $data['guests']);
            }

            return $reservation->fresh([
                'client' => fn ($query) => $query->withTrashed(),
                'cabin' => fn ($query) => $query->withTrashed(),
                'guests',
                'payments',
            ]);
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

        if (! $reservation->isPendingConfirmation()) {
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

            return $reservation->fresh([
                'client' => fn ($query) => $query->withTrashed(),
                'cabin' => fn ($query) => $query->withTrashed(),
                'guests',
                'payments',
            ]);
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
        if (! $reservation->isConfirmed()) {
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

            return $reservation->fresh([
                'client' => fn ($query) => $query->withTrashed(),
                'cabin' => fn ($query) => $query->withTrashed(),
                'guests',
                'payments',
            ]);
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

        if (! $reservation->isConfirmed()) {
            throw ValidationException::withMessages([
                'status' => ['Solo se puede hacer check-in en reservas confirmadas'],
            ]);
        }

        // Si el saldo ya fue pagado (pagó anticipadamente), solo cambiar estado
        if ($reservation->hasBalancePaid()) {
            $reservation->update([
                'status' => Reservation::STATUS_CHECKED_IN,
            ]);

            /** @var Reservation $freshReservation */
            $freshReservation = $reservation->fresh([
                'client' => fn ($query) => $query->withTrashed(),
                'cabin' => fn ($query) => $query->withTrashed(),
                'guests',
                'payments',
            ]);

            return $freshReservation;
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

            /** @var Reservation $freshReservation */
            $freshReservation = $reservation->fresh([
                'client' => fn ($query) => $query->withTrashed(),
                'cabin' => fn ($query) => $query->withTrashed(),
                'guests',
                'payments',
            ]);

            return $freshReservation;
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

        if (! $reservation->isCheckedIn()) {
            throw ValidationException::withMessages([
                'status' => ['Solo se puede hacer check-out en reservas con check-in realizado'],
            ]);
        }

        $reservation->update([
            'status' => Reservation::STATUS_FINISHED,
        ]);

        /** @var Reservation $freshReservation */
        $freshReservation = $reservation->fresh([
            'client' => fn ($query) => $query->withTrashed(),
            'cabin' => fn ($query) => $query->withTrashed(),
            'guests',
            'payments',
        ]);

        return $freshReservation;
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

        /** @var Reservation $freshReservation */
        $freshReservation = $reservation->fresh([
            'client' => fn ($query) => $query->withTrashed(),
            'cabin' => fn ($query) => $query->withTrashed(),
            'guests',
            'payments',
        ]);

        return $freshReservation;
    }

    /**
     * Cancela automáticamente las reservas pendientes que han excedido su límite de tiempo
     *
     * @return array{cancelled: int, failed: int} Array con cantidad de canceladas y fallidas
     */
    public function autoCalcellExpiredPending(): array
    {
        $expiredReservations = Reservation::where('status', Reservation::STATUS_PENDING_CONFIRMATION)
            ->whereNotNull('pending_until')
            ->where('pending_until', '<', now())
            ->get();

        $cancelled = 0;
        $failed = 0;

        foreach ($expiredReservations as $reservation) {
            try {
                $this->cancel($reservation->id);
                $cancelled++;
            } catch (\Exception $e) {
                $failed++;
            }
        }

        return [
            'cancelled' => $cancelled,
            'failed' => $failed,
        ];
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
        $cabin = Cabin::findOrFail($cabinId);

        // Verificar disponibilidad
        $isAvailable = $this->availabilityService->isAvailable($cabinId, $checkInDate, $checkOutDate);

        $quote = $this->priceCalculator->generateReservableQuote($cabin, $checkIn, $checkOut, $numGuests);
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
    protected function filterByStatus(Builder $query, string|array $value): Builder
    {
        return is_array($value)
            ? $query->whereIn('status', $value)
            : $query->where('status', $value);
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

        if (! empty($start) && ! empty($end)) {
            // Ambas fechas: devolver reservas que se solapen con el rango
            // Una reserva se solapa si: check_in_date <= end AND check_out_date >= start
            $query->where(function ($q) use ($start, $end) {
                $q->whereDate('check_in_date', '<=', $end)
                    ->whereDate('check_out_date', '>=', $start);
            });
        } elseif (! empty($start)) {
            // Solo inicio: devolver reservas cuya check_out_date >= start
            $query->whereDate('check_out_date', '>=', $start);
        } elseif (! empty($end)) {
            // Solo fin: devolver reservas cuya check_in_date <= end
            $query->whereDate('check_in_date', '<=', $end);
        }
    }

    protected function applySimpleSearch(Builder $query, string $value): Builder
    {
        $value = strtolower($value);

        $query->where(function (Builder $searchQuery) use ($value): void {
            $searchQuery->where('notes', 'like', "%{$value}%")
                ->orWhereHas('client', function (Builder $clientQuery) use ($value): void {
                    $clientQuery->withTrashed()
                        ->where(function (Builder $innerQuery) use ($value): void {
                            $innerQuery->where('name', 'like', "%{$value}%")
                                ->orWhere('dni', 'like', "%{$value}%");
                        });
                })
                ->orWhereHas('cabin', function (Builder $cabinQuery) use ($value): void {
                    $cabinQuery->withTrashed()->where('name', 'like', "%{$value}%");
                });
        });

        return $query;
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
     * Obtiene o crea un cliente a partir de datos de cliente usando el ClientService
     */
    private function resolveClient(int $tenantId, ?array $clientData): Client
    {
        if ($clientData === null || empty($clientData['dni'])) {
            throw ValidationException::withMessages([
                'client' => ['Los datos del cliente (incluyendo DNI) son obligatorios'],
            ]);
        }

        $client = $this->clientService->searchByDni($clientData['dni']);

        if ($client) {
            // Actualizar datos si el cliente existe (pero no el DNI)
            $updatableData = Arr::except($clientData, ['dni', 'tenant_id']);

            return $this->clientService->updateClient($client->id, $updatableData);
        }

        // Crear nuevo cliente
        $clientData['tenant_id'] = $tenantId;

        return $this->clientService->createClient($clientData);
    }

    private function guardTenantOverride(array $data, int $tenantId): void
    {
        if (array_key_exists('tenant_id', $data) && $data['tenant_id'] !== null && (int) $data['tenant_id'] !== $tenantId) {
            throw ValidationException::withMessages([
                'tenant_id' => ['El tenant_id no coincide con el contexto tenant activo.'],
            ]);
        }
    }
}
