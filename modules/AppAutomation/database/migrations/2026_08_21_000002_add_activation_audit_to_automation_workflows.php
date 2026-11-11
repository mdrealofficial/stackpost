<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('automation_workflows') || Schema::hasColumn('automation_workflows', 'activated_by_user_id')) {
            return;
        }

        Schema::table('automation_workflows', function (Blueprint $table): void {
            $table->unsignedBigInteger('activated_by_user_id')->nullable()->after('created_by_user_id');
            $table->index('activated_by_user_id', 'automation_workflows_activator_index');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('automation_workflows') || ! Schema::hasColumn('automation_workflows', 'activated_by_user_id')) {
            return;
        }

        Schema::table('automation_workflows', function (Blueprint $table): void {
            $table->dropIndex('automation_workflows_activator_index');
            $table->dropColumn('activated_by_user_id');
        });
    }
};
