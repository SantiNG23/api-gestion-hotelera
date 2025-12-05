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
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->onDelete('cascade');
            $table->foreignId('client_id')->constrained()->onDelete('restrict');
            $table->foreignId('cabin_id')->constrained()->onDelete('restrict');
            $table->date('check_in_date');
            $table->date('check_out_date');
            $table->decimal('total_price', 10, 2);
            $table->decimal('deposit_amount', 10, 2);
            $table->decimal('balance_amount', 10, 2);
            $table->enum('status', [
                'pending_confirmation',
                'confirmed',
                'checked_in',
                'finished',
                'cancelled',
            ])->default('pending_confirmation');
            $table->datetime('pending_until')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['cabin_id', 'check_in_date', 'check_out_date']);
            $table->index(['client_id']);
            $table->index(['pending_until']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};

