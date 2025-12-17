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
        Schema::create('cabin_price_by_guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('cabin_id')->constrained('cabins')->onDelete('cascade');
            $table->foreignId('price_group_id')->constrained('price_groups')->onDelete('cascade');
            $table->unsignedTinyInteger('num_guests');
            $table->decimal('price_per_night', 10, 2);
            $table->timestamps();
            $table->softDeletes();

            // Índices para búsquedas eficientes
            $table->unique(['tenant_id', 'cabin_id', 'price_group_id', 'num_guests'], 'unique_cabin_guest_price');
            $table->index(['tenant_id', 'cabin_id']);
            $table->index(['price_group_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cabin_price_by_guests');
    }
};
