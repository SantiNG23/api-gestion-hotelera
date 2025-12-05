<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cabin extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'cabins';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'capacity',
        'is_active',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'capacity' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Características de la cabaña
     */
    public function features(): BelongsToMany
    {
        return $this->belongsToMany(Feature::class, 'cabin_feature');
    }

    /**
     * Reservas de la cabaña
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}

