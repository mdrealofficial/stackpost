<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('automation_api_idempotency_keys')) {
            return;
        }

        Schema::create('automation_api_idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('api_key_id')->constrained('automation_api_keys')->cascadeOnDelete();
            $table->string('idempotency_key', 255);
            $table->string('request_hash', 64);
            $table->string('status', 20)->default('processing');
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->json('response')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['api_key_id', 'idempotency_key'], 'automation_api_idempotency_unique');
            $table->index(['status', 'expires_at'], 'automation_api_idempotency_status_expiry');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_api_idempotency_keys');
    }
};
