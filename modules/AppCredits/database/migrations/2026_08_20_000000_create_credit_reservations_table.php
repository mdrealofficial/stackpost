<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('credit_reservations')) {
            return;
        }

        Schema::create('credit_reservations', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->unsignedBigInteger('usage_log_id')->nullable();
            $table->string('action_key', 120);
            $table->string('idempotency_key', 120);
            $table->string('status', 24)->default('reserved');
            $table->unsignedInteger('unit_cost')->default(0);
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedInteger('amount')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'idempotency_key'], 'credit_reservations_user_idempotency_unique');
            $table->index(['user_id', 'status', 'expires_at'], 'credit_reservations_user_status_expiry_index');
            $table->index('usage_log_id', 'credit_reservations_usage_log_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credit_reservations');
    }
};
