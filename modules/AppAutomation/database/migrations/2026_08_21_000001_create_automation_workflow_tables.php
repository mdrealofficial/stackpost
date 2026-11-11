<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('automation_connections')) {
            Schema::create('automation_connections', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('owner_user_id');
                $table->unsignedBigInteger('created_by_user_id');
                $table->unsignedBigInteger('team_id')->nullable();
                $table->string('provider', 80);
                $table->string('label', 160);
                $table->string('status', 24)->default('pending');
                $table->text('encrypted_credentials')->nullable();
                $table->json('scopes')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('last_verified_at')->nullable();
                $table->timestamps();

                $table->index(['owner_user_id', 'team_id', 'provider'], 'automation_connections_workspace_provider_index');
                $table->index(['owner_user_id', 'team_id', 'status'], 'automation_connections_workspace_status_index');
            });
        }

        if (! Schema::hasTable('automation_workflows')) {
            Schema::create('automation_workflows', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('owner_user_id');
                $table->unsignedBigInteger('created_by_user_id');
                $table->unsignedBigInteger('team_id')->nullable();
                $table->string('name', 160);
                $table->text('description')->nullable();
                $table->string('status', 24)->default('draft');
                $table->string('trigger_key', 80)->default('manual');
                $table->unsignedInteger('current_revision')->default(1);
                $table->json('definition');
                $table->json('settings')->nullable();
                $table->timestamp('published_at')->nullable();
                $table->timestamp('last_run_at')->nullable();
                $table->timestamps();

                $table->index(['owner_user_id', 'team_id', 'status'], 'automation_workflows_workspace_status_index');
                $table->index(['owner_user_id', 'team_id', 'trigger_key'], 'automation_workflows_workspace_trigger_index');
            });
        }

        if (! Schema::hasTable('automation_workflow_revisions')) {
            Schema::create('automation_workflow_revisions', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('workflow_id');
                $table->unsignedBigInteger('created_by_user_id');
                $table->unsignedInteger('revision');
                $table->json('definition');
                $table->string('status', 24)->default('draft');
                $table->timestamp('published_at')->nullable();
                $table->timestamps();

                $table->unique(['workflow_id', 'revision'], 'automation_workflow_revisions_unique');
                $table->index('created_by_user_id', 'automation_workflow_revisions_creator_index');
            });
        }

        if (! Schema::hasTable('automation_workflow_runs')) {
            Schema::create('automation_workflow_runs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('workflow_id');
                $table->unsignedBigInteger('owner_user_id');
                $table->unsignedBigInteger('requested_by_user_id')->nullable();
                $table->unsignedBigInteger('team_id')->nullable();
                $table->unsignedInteger('workflow_revision');
                $table->string('trigger_key', 80);
                $table->string('external_event_id', 160)->nullable();
                $table->string('status', 32)->default('queued');
                $table->json('input')->nullable();
                $table->json('trace')->nullable();
                $table->json('output')->nullable();
                $table->text('error')->nullable();
                $table->unsignedInteger('credits_reserved')->default(0);
                $table->unsignedInteger('credits_used')->default(0);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->unique(['workflow_id', 'external_event_id'], 'automation_workflow_runs_event_unique');
                $table->index(['owner_user_id', 'team_id', 'status'], 'automation_workflow_runs_workspace_status_index');
                $table->index(['workflow_id', 'created_at'], 'automation_workflow_runs_workflow_created_index');
            });
        }

        if (! Schema::hasTable('automation_workflow_step_runs')) {
            Schema::create('automation_workflow_step_runs', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('run_id');
                $table->string('node_id', 100);
                $table->string('node_type', 80);
                $table->unsignedSmallInteger('attempt')->default(1);
                $table->string('status', 32)->default('queued');
                $table->json('input')->nullable();
                $table->json('output')->nullable();
                $table->text('error')->nullable();
                $table->unsignedInteger('credits_reserved')->default(0);
                $table->unsignedInteger('credits_used')->default(0);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->unique(['run_id', 'node_id', 'attempt'], 'automation_workflow_step_runs_unique');
                $table->index(['run_id', 'status'], 'automation_workflow_step_runs_status_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_workflow_step_runs');
        Schema::dropIfExists('automation_workflow_runs');
        Schema::dropIfExists('automation_workflow_revisions');
        Schema::dropIfExists('automation_workflows');
        Schema::dropIfExists('automation_connections');
    }
};
