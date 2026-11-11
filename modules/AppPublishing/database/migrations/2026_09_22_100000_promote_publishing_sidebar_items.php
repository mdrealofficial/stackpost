<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('options')) {
            return;
        }

        $value = DB::table('options')
            ->where('name', 'admin_menu_sidebar_overrides')
            ->value('value');

        if (! is_string($value) || $value === '') {
            return;
        }

        $overrides = json_decode($value, true);

        if (! is_array($overrides)) {
            return;
        }

        foreach (($overrides['user']['sections'] ?? []) as &$section) {
            if (($section['key'] ?? null) !== 'workspace' || ! is_array($section['items'] ?? null)) {
                continue;
            }

            $labels = [
                'portal.publishing.calendar' => ['Calendar' => 'Publishing'],
                'portal.ai-publishing' => ['AI Publishing' => 'AI Schedules'],
                'portal.bulk-posts' => ['Bulk Posts' => 'Bulk Import'],
            ];
            $priority = array_flip([
                'portal.channels',
                'portal.publishing.calendar',
                'portal.rss-schedules.index',
                'portal.ai-publishing',
                'portal.bulk-posts',
            ]);

            foreach ($section['items'] as &$item) {
                $key = (string) ($item['key'] ?? '');
                $label = (string) ($item['label'] ?? '');

                if (isset($labels[$key][$label])) {
                    $item['label'] = $labels[$key][$label];
                }
            }
            unset($item);

            usort($section['items'], fn (array $a, array $b): int => ($priority[$a['key'] ?? ''] ?? 999) <=> ($priority[$b['key'] ?? ''] ?? 999));
        }
        unset($section);

        DB::table('options')
            ->where('name', 'admin_menu_sidebar_overrides')
            ->update(['value' => json_encode($overrides)]);

        Cache::forget('options.admin_menu_sidebar_overrides');
        Cache::forget('options.v2.admin_menu_sidebar_overrides');
    }

    public function down(): void
    {
        // Menu labels may have been customized after this update; do not reverse them automatically.
    }
};
