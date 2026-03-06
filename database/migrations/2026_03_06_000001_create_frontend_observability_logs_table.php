<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('frontend_observability_logs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('level', 10)->index();
            $table->string('scope', 100)->index();
            $table->json('context')->nullable();
            $table->string('event_name', 150)->nullable()->index();
            $table->json('meta')->nullable();
            $table->json('args')->nullable();
            $table->timestamp('occurred_at')->index();
            $table->timestamp('ingested_at')->index();
            $table->string('ip', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('request_id', 100)->nullable();

            $table->foreign('tenant_id', 'frontend_logs_tenant_fk')
                ->references('id')
                ->on('tenants')
                ->nullOnDelete();

            $table->foreign('user_id', 'frontend_logs_user_fk')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['tenant_id', 'occurred_at'], 'frontend_logs_tenant_occurred_idx');
            $table->index(['tenant_id', 'level', 'occurred_at'], 'frontend_logs_tenant_level_occurred_idx');
            $table->index(['event_name', 'occurred_at'], 'frontend_logs_event_occurred_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('frontend_observability_logs');
    }
};
