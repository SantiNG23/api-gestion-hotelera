<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pago de una reserva
 *
 * NO usa BelongsToTenant - accede al tenant vía Reservation
 * NO usa SoftDeletes - los pagos no se eliminan
 */
class ReservationPayment extends EloquentModel
{
    use HasFactory;

    public const TYPE_DEPOSIT = 'deposit';
    public const TYPE_BALANCE = 'balance';

    protected $table = 'reservation_payments';

    protected $fillable = [
        'reservation_id',
        'amount',
        'payment_type',
        'payment_method',
        'paid_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    /**
     * Reserva a la que pertenece el pago
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }

    /**
     * Verifica si es un pago de seña
     */
    public function isDeposit(): bool
    {
        return $this->payment_type === self::TYPE_DEPOSIT;
    }

    /**
     * Verifica si es un pago de saldo
     */
    public function isBalance(): bool
    {
        return $this->payment_type === self::TYPE_BALANCE;
    }
}

