<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Feature extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'features';

    protected $fillable = [
        'tenant_id',
        'name',
        'icon',
        'is_active',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * Cabañas que tienen esta característica
     */
    public function cabins(): BelongsToMany
    {
        return $this->belongsToMany(Cabin::class, 'cabin_feature');
    }
}

