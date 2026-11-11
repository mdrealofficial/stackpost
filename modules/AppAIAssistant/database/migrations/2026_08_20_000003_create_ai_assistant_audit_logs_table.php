<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_assistant_audit_logs')) {
            return;
        }

        Schema::create('ai_assistant_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('owner_user_id');
            $table->unsignedBigInteger('requested_by_user_id');
            $table->unsignedBigInteger('team_id')->nullable();
            $table->unsignedBigInteger('idea_id')->nullable();
            $table->string('action_key', 120);
            $table->string('status', 32);
            $table->string('request_key', 120)->nullable();
            $table->string('credit_action', 120)->nullable();
            $table->unsignedInteger('credit_cost')->default(0);
            $table->integer('credits_before')->nullable();
            $table->integer('credits_after')->nullable();
            $table->json('payload')->nullable();
            $table->json('result')->nullable();
            $table->text('error_message')->nullable();
            $table->string('ip_address', 64)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();

            $table->index(['owner_user_id', 'team_id', 'created_at'], 'ai_assistant_audit_workspace_created_index');
            $table->index(['request_key', 'status'], 'ai_assistant_audit_request_status_index');
            $table->index(['idea_id', 'action_key'], 'ai_assistant_audit_idea_action_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_assistant_audit_logs');
    }
};
