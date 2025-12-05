<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reservation extends Model
{
    use BelongsToTenant;
    use HasFactory;

    // Estados de la reserva
    public const STATUS_PENDING_CONFIRMATION = 'pending_confirmation';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_CHECKED_IN = 'checked_in';
    public const STATUS_FINISHED = 'finished';
    public const STATUS_CANCELLED = 'cancelled';

    // Estados que bloquean disponibilidad
    public const BLOCKING_STATUSES = [
        self::STATUS_PENDING_CONFIRMATION,
        self::STATUS_CONFIRMED,
        self::STATUS_CHECKED_IN,
    ];

    protected $table = 'reservations';

    protected $fillable = [
        'tenant_id',
        'client_id',
        'cabin_id',
        'check_in_date',
        'check_out_date',
        'total_price',
        'deposit_amount',
        'balance_amount',
        'status',
        'pending_until',
        'notes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'check_in_date' => 'date',
        'check_out_date' => 'date',
        'total_price' => 'decimal:2',
        'deposit_amount' => 'decimal:2',
        'balance_amount' => 'decimal:2',
        'pending_until' => 'datetime',
    ];

    /**
     * Cliente de la reserva
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Cabaña de la reserva
     */
    public function cabin(): BelongsTo
    {
        return $this->belongsTo(Cabin::class);
    }

    /**
     * Huéspedes de la reserva
     */
    public function guests(): HasMany
    {
        return $this->hasMany(ReservationGuest::class);
    }

    /**
     * Pagos de la reserva
     */
    public function payments(): HasMany
    {
        return $this->hasMany(ReservationPayment::class);
    }

    /**
     * Verifica si la reserva está en estado pendiente de confirmación
     */
    public function isPendingConfirmation(): bool
    {
        return $this->status === self::STATUS_PENDING_CONFIRMATION;
    }

    /**
     * Verifica si la reserva está confirmada
     */
    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    /**
     * Verifica si se realizó el check-in
     */
    public function isCheckedIn(): bool
    {
        return $this->status === self::STATUS_CHECKED_IN;
    }

    /**
     * Verifica si la reserva está finalizada
     */
    public function isFinished(): bool
    {
        return $this->status === self::STATUS_FINISHED;
    }

    /**
     * Verifica si la reserva está cancelada
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Verifica si la reserva bloquea disponibilidad
     */
    public function blocksAvailability(): bool
    {
        // Pendientes solo bloquean si no han vencido
        if ($this->isPendingConfirmation()) {
            return $this->pending_until === null || $this->pending_until->isFuture();
        }

        return in_array($this->status, self::BLOCKING_STATUSES);
    }

    /**
     * Verifica si la reserva pendiente ha vencido
     */
    public function isPendingExpired(): bool
    {
        return $this->isPendingConfirmation()
            && $this->pending_until !== null
            && $this->pending_until->isPast();
    }

    /**
     * Obtiene el pago de seña
     */
    public function getDepositPayment(): ?ReservationPayment
    {
        return $this->payments()->where('payment_type', 'deposit')->first();
    }

    /**
     * Obtiene el pago de saldo
     */
    public function getBalancePayment(): ?ReservationPayment
    {
        return $this->payments()->where('payment_type', 'balance')->first();
    }

    /**
     * Verifica si tiene seña pagada
     */
    public function hasDepositPaid(): bool
    {
        return $this->getDepositPayment() !== null;
    }

    /**
     * Verifica si tiene saldo pagado
     */
    public function hasBalancePaid(): bool
    {
        return $this->getBalancePayment() !== null;
    }

    /**
     * Calcula el número de noches
     */
    public function getNightsAttribute(): int
    {
        if (!$this->check_in_date || !$this->check_out_date) {
            return 0;
        }
        return (int) $this->check_in_date->diffInDays($this->check_out_date);
    }
}
