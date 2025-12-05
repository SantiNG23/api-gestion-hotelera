<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'clients';

    protected $fillable = [
        'tenant_id',
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
        'deleted_at' => 'datetime',
        'age' => 'integer',
    ];

    /**
     * Reservas del cliente
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}

