<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Huésped de una reserva
 *
 * NO usa BelongsToTenant - accede al tenant vía Reservation
 * NO usa SoftDeletes - se elimina con la reserva
 */
class ReservationGuest extends EloquentModel
{
    use HasFactory;

    protected $table = 'reservation_guests';

    protected $fillable = [
        'reservation_id',
        'name',
        'dni',
        'age',
        'city',
        'phone',
        'email',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'age' => 'integer',
    ];

    /**
     * Reserva a la que pertenece el huésped
     */
    public function reservation(): BelongsTo
    {
        return $this->belongsTo(Reservation::class);
    }
}

