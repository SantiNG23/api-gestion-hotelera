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
        Schema::create('user_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();
            // Consistente con users.tenant_id, que hoy es nullable.
            $table->foreignId('tenant_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->string('locale')->default('es_AR');
            $table->string('timezone')->default('America/Argentina/Buenos_Aires');
            $table->boolean('marketing_emails')->default(false);
            $table->boolean('transactional_emails')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_settings');
    }
};
