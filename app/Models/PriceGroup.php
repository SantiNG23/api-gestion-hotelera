<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PriceGroup extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'price_groups';

    protected $fillable = [
        'tenant_id',
        'name',
        'price_per_night',
        'priority',
        'is_default',
    ];

    protected $attributes = [
        'priority' => 0,
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'price_per_night' => 'decimal:2',
        'is_default' => 'boolean',
        'priority' => 'integer',
    ];

    /**
     * Rangos de precio asociados a este grupo
     */
    public function priceRanges(): HasMany
    {
        return $this->hasMany(PriceRange::class);
    }

    /**
     * Precios de cabañas por cantidad de huéspedes asociados a este grupo
     */
    public function cabinPricesByGuests(): HasMany
    {
        return $this->hasMany(CabinPriceByGuests::class);
    }

    /**
     * Alias para cabinPricesByGuests (para los nuevos endpoints)
     */
    public function cabinPrices(): HasMany
    {
        return $this->cabinPricesByGuests();
    }

    /**
     * Accessor para obtener las cabañas únicas de este grupo
     */
    public function getCabinsAttribute()
    {
        return $this->cabinPrices()
            ->with('cabin:id,name,capacity')
            ->get()
            ->groupBy('cabin_id')
            ->map(function ($prices) {
                return $prices->first()->cabin;
            })
            ->values();
    }

    /**
     * Hook de inicialización del modelo
     */
    protected static function booted(): void
    {
        // Eliminar en cascada completa (hard delete) los rangos de precio y precios de cabaña
        static::deleting(function ($priceGroup) {
            $priceGroup->priceRanges()->forceDelete();
            $priceGroup->cabinPricesByGuests()->forceDelete();
        });
    }

    /**
     * Hook antes de crear: asegurar que solo un grupo sea default por tenant
     */
    protected function beforeCreate(): void
    {
        //
    }

    /**
     * Hook antes de actualizar: manejar cambio de is_default
     */
    protected function beforeUpdate(): void
    {
        //
    }
}
