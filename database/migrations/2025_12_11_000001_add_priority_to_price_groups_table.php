<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('price_groups', function (Blueprint $table) {
            $table->integer('priority')->default(0)->after('price_per_night');
            $table->index(['tenant_id', 'priority']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('price_groups', function (Blueprint $table) {
            $table->dropIndex(['tenant_id', 'priority']);
            $table->dropColumn('priority');
        });
    }
};
