<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceRange extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'price_ranges';

    protected $fillable = [
        'tenant_id',
        'price_group_id',
        'start_date',
        'end_date',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    /**
     * Grupo de precio al que pertenece
     */
    public function priceGroup(): BelongsTo
    {
        return $this->belongsTo(PriceGroup::class);
    }
}
