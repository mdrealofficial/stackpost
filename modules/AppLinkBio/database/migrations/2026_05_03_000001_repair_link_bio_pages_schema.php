<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('link_bio_pages')) {
            return;
        }

        Schema::table('link_bio_pages', function (Blueprint $table): void {
            if (! Schema::hasColumn('link_bio_pages', 'template_key')) {
                $table->string('template_key', 60)->default('aurora')->after('cover_url');
            }

            if (! Schema::hasColumn('link_bio_pages', 'status')) {
                $table->string('status', 30)->default('draft')->index()->after('template_key');
            }

            if (! Schema::hasColumn('link_bio_pages', 'blocks')) {
                $table->json('blocks')->nullable()->after('is_published');
            }
        });

        if (Schema::hasColumn('link_bio_pages', 'links') && Schema::hasColumn('link_bio_pages', 'blocks')) {
            DB::table('link_bio_pages')
                ->whereNull('blocks')
                ->orderBy('id')
                ->chunkById(100, function ($pages): void {
                    foreach ($pages as $page) {
                        $links = json_decode((string) ($page->links ?? ''), true);

                        if (! is_array($links)) {
                            $links = [];
                        }

                        $items = collect($links)
                            ->map(function ($link): array {
                                if (! is_array($link)) {
                                    return [];
                                }

                                return [
                                    'label' => trim((string) data_get($link, 'label', data_get($link, 'title', ''))),
                                    'url' => trim((string) data_get($link, 'url', '')),
                                    'note' => trim((string) data_get($link, 'note', data_get($link, 'description', ''))),
                                    'icon' => trim((string) data_get($link, 'icon', 'fa-light fa-link')),
                                    'image' => trim((string) data_get($link, 'image', '')),
                                    'value' => '',
                                    'price' => '',
                                    'placeholder' => '',
                                    'answer' => '',
                                    'field_type' => 'text',
                                ];
                            })
                            ->filter(fn (array $item): bool => collect($item)->some(fn ($value): bool => $value !== ''))
                            ->values()
                            ->all();

                        DB::table('link_bio_pages')
                            ->where('id', $page->id)
                            ->update([
                                'template_key' => 'aurora',
                                'status' => ((bool) ($page->is_published ?? false)) ? 'published' : 'draft',
                                'blocks' => json_encode([[
                                    'type' => 'links',
                                    'title' => 'Links',
                                    'subtitle' => '',
                                    'content' => '',
                                    'url' => '',
                                    'button_label' => '',
                                    'button_url' => '',
                                    'enabled' => true,
                                    'items' => $items,
                                ]]),
                            ]);
                    }
                });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('link_bio_pages')) {
            return;
        }

        Schema::table('link_bio_pages', function (Blueprint $table): void {
            if (Schema::hasColumn('link_bio_pages', 'blocks')) {
                $table->dropColumn('blocks');
            }

            if (Schema::hasColumn('link_bio_pages', 'status')) {
                $table->dropColumn('status');
            }

            if (Schema::hasColumn('link_bio_pages', 'template_key')) {
                $table->dropColumn('template_key');
            }
        });
    }
};
