<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('automation_workflow_artifacts')) {
            return;
        }

        Schema::create('automation_workflow_artifacts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('workflow_id');
            $table->unsignedBigInteger('run_id');
            $table->unsignedBigInteger('owner_user_id');
            $table->unsignedBigInteger('team_id')->nullable();
            $table->string('node_id', 100);
            $table->string('artifact_type', 48);
            $table->char('artifact_key', 64)->unique();
            $table->string('external_id', 191);
            $table->unsignedBigInteger('app_file_id')->nullable();
            $table->string('status', 24)->default('reserved');
            $table->string('claim_token', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['run_id', 'node_id', 'artifact_type'], 'automation_artifacts_run_node_type_index');
            $table->index(['owner_user_id', 'team_id', 'status'], 'automation_artifacts_workspace_status_index');
            $table->index('app_file_id', 'automation_artifacts_app_file_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_workflow_artifacts');
    }
};
