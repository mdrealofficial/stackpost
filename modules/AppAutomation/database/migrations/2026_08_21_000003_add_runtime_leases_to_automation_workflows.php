<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('automation_workflow_runs', function (Blueprint $table): void {
            $table->unsignedSmallInteger('attempt')->default(0)->after('status');
            $table->unsignedSmallInteger('max_attempts')->default(3)->after('attempt');
            $table->timestamp('next_attempt_at')->nullable()->after('max_attempts');
            $table->timestamp('claimed_at')->nullable()->after('next_attempt_at');
            $table->timestamp('heartbeat_at')->nullable()->after('claimed_at');
            $table->string('claim_token', 64)->nullable()->after('heartbeat_at');
            $table->string('waiting_reason', 80)->nullable()->after('claim_token');

            $table->index(['status', 'next_attempt_at'], 'automation_runs_due_index');
            $table->index(['status', 'heartbeat_at'], 'automation_runs_lease_index');
        });

        Schema::table('automation_workflow_step_runs', function (Blueprint $table): void {
            $table->timestamp('available_at')->nullable()->after('status');
            $table->timestamp('claimed_at')->nullable()->after('available_at');
            $table->timestamp('heartbeat_at')->nullable()->after('claimed_at');
            $table->string('claim_token', 64)->nullable()->after('heartbeat_at');

            $table->index(['status', 'available_at'], 'automation_steps_due_index');
            $table->index(['status', 'heartbeat_at'], 'automation_steps_lease_index');
        });
    }

    public function down(): void
    {
        Schema::table('automation_workflow_step_runs', function (Blueprint $table): void {
            $table->dropIndex('automation_steps_due_index');
            $table->dropIndex('automation_steps_lease_index');
            $table->dropColumn(['available_at', 'claimed_at', 'heartbeat_at', 'claim_token']);
        });

        Schema::table('automation_workflow_runs', function (Blueprint $table): void {
            $table->dropIndex('automation_runs_due_index');
            $table->dropIndex('automation_runs_lease_index');
            $table->dropColumn([
                'attempt',
                'max_attempts',
                'next_attempt_at',
                'claimed_at',
                'heartbeat_at',
                'claim_token',
                'waiting_reason',
            ]);
        });
    }
};
