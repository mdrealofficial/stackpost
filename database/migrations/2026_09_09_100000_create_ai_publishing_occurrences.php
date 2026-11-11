<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_publishing_occurrences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('run_id')->index();
            $table->unsignedBigInteger('owner_user_id')->index();
            $table->unsignedBigInteger('prompt_id');
            $table->unsignedBigInteger('slot_at');
            $table->string('status', 30)->default('pending');
            $table->json('payload')->nullable();
            $table->unsignedInteger('credits_spent')->default(0);
            $table->unsignedInteger('credits_reserved')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['run_id', 'slot_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_publishing_occurrences');
    }
};
