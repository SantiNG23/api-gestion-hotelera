<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tenant extends Model
{
    use HasFactory;

    protected $table = 'tenants';

    protected $fillable = [
        'name',
        'slug',
        'is_active',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Usuarios del tenant
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Clientes del tenant
     */
    public function clients(): HasMany
    {
        return $this->hasMany(Client::class);
    }

    /**
     * Cabañas del tenant
     */
    public function cabins(): HasMany
    {
        return $this->hasMany(Cabin::class);
    }

    /**
     * Características del tenant
     */
    public function features(): HasMany
    {
        return $this->hasMany(Feature::class);
    }

    /**
     * Grupos de precios del tenant
     */
    public function priceGroups(): HasMany
    {
        return $this->hasMany(PriceGroup::class);
    }

    /**
     * Reservas del tenant
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }
}
