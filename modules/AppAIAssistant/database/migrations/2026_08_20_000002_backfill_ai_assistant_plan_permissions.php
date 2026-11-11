<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('plans') || ! Schema::hasColumn('plans', 'permissions')) {
            return;
        }

        DB::table('plans')
            ->select(['id', 'permissions'])
            ->orderBy('id')
            ->chunkById(100, function ($plans): void {
                foreach ($plans as $plan) {
                    $permissions = json_decode((string) $plan->permissions, true);

                    if (! is_array($permissions) || empty($permissions['ai_studio']) || array_key_exists('ai_assistant', $permissions)) {
                        continue;
                    }

                    $permissions['ai_assistant'] = true;

                    DB::table('plans')
                        ->where('id', $plan->id)
                        ->update(['permissions' => json_encode($permissions)]);
                }
            }, 'id');
    }

    public function down(): void
    {
        // Keep plan configuration intact when the module is removed.
    }
};
