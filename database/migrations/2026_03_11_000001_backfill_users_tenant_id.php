<?php

declare(strict_types=1);

use App\Support\UsersTenantOwnershipBackfill;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(UsersTenantOwnershipBackfill::class)->backfillOrFail();
    }

    public function down(): void
    {
        // No-op: el backfill es irreversible sin snapshot previo.
    }
};
