<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Str;

class FrontendObservabilityLog extends EloquentModel
{
    use HasFactory;

    protected $table = 'frontend_observability_logs';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id',
        'tenant_id',
        'user_id',
        'level',
        'scope',
        'context',
        'event_name',
        'meta',
        'args',
        'occurred_at',
        'ingested_at',
        'ip',
        'user_agent',
        'request_id',
    ];

    protected $casts = [
        'context' => 'array',
        'meta' => 'array',
        'args' => 'array',
        'occurred_at' => 'datetime',
        'ingested_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (! $model->id) {
                $model->id = (string) Str::uuid();
            }
        });
    }
}
