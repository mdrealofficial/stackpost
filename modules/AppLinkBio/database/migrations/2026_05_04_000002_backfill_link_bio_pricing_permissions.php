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

                    if (! is_array($permissions) || empty($permissions['link_bio'])) {
                        continue;
                    }

                    $changed = false;

                    if (! array_key_exists('link_bio_ai_assistant', $permissions)) {
                        $permissions['link_bio_ai_assistant'] = true;
                        $changed = true;
                    }

                    if (! array_key_exists('link_bio_remove_branding', $permissions)) {
                        $permissions['link_bio_remove_branding'] = false;
                        $changed = true;
                    }

                    if ($changed) {
                        DB::table('plans')
                            ->where('id', $plan->id)
                            ->update(['permissions' => json_encode($permissions)]);
                    }
                }
            }, 'id');
    }

    public function down(): void
    {
        //
    }
};
