<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CabinPriceByGuests extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'cabin_price_by_guests';

    protected $fillable = [
        'tenant_id',
        'cabin_id',
        'price_group_id',
        'num_guests',
        'price_per_night',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'num_guests' => 'integer',
        'price_per_night' => 'decimal:2',
    ];

    /**
     * Cabaña a la que pertenece este precio
     */
    public function cabin(): BelongsTo
    {
        return $this->belongsTo(Cabin::class);
    }

    /**
     * Grupo de precio al que pertenece
     */
    public function priceGroup(): BelongsTo
    {
        return $this->belongsTo(PriceGroup::class);
    }

    /**
     * Scope para filtrar por grupo y cabaña
     */
    public function scopeForGroupAndCabin($query, int $priceGroupId, int $cabinId)
    {
        return $query->where('price_group_id', $priceGroupId)
                     ->where('cabin_id', $cabinId);
    }
}
