<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Client extends Model
{
    use BelongsToTenant;
    use HasFactory;

    // DNI para reservas de bloqueo
    public const DNI_BLOCK = '00000000';

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

    protected function beforeUpdate(): void
    {
        if ($this->isSystemClient()) {
            throw ValidationException::withMessages([
                'dni' => ['El cliente técnico de bloqueos no puede modificarse'],
            ]);
        }
    }

    protected function beforeDelete(): void
    {
        if ($this->isSystemClient()) {
            throw ValidationException::withMessages([
                'dni' => ['El cliente técnico de bloqueos no puede eliminarse'],
            ]);
        }
    }

    private function isSystemClient(): bool
    {
        return $this->dni === self::DNI_BLOCK;
    }
}
