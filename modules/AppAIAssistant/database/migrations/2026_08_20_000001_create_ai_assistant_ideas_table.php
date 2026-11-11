<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_assistant_ideas')) {
            return;
        }

        Schema::create('ai_assistant_ideas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('owner_user_id');
            $table->unsignedBigInteger('requested_by_user_id');
            $table->unsignedBigInteger('team_id')->nullable();
            $table->string('title', 180);
            $table->text('brief');
            $table->json('platforms')->nullable();
            $table->string('status', 40)->default('idea');
            $table->text('ai_summary')->nullable();
            $table->json('variants')->nullable();
            $table->string('generated_source', 30)->nullable();
            $table->json('selected_account_ids')->nullable();
            $table->json('publishing_post_ids')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['owner_user_id', 'team_id', 'status'], 'ai_assistant_workspace_status_index');
            $table->index(['requested_by_user_id', 'created_at'], 'ai_assistant_requester_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_assistant_ideas');
    }
};
