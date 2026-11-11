<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\AdminAITemplate\Models\AiTemplate;
use Modules\AdminAITemplateCategories\Models\AiTemplateCategory;

class AITemplateSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AITemplateCategorySeeder::class);

        $dataPath = database_path('seeders/data/ai_templates.json');

        if (! is_file($dataPath)) {
            throw new \RuntimeException("AI template seed data not found at: {$dataPath}");
        }

        $json = file_get_contents($dataPath);

        if ($json === false) {
            throw new \RuntimeException("Unable to read AI template seed data at: {$dataPath}");
        }

        $templates = json_decode($json, true);

        if (! is_array($templates) || $templates === []) {
            throw new \RuntimeException('No AI templates found in the packaged seed data.');
        }

        $categoryIdsByName = AiTemplateCategory::query()->pluck('id', 'name')->all();

        foreach ($templates as $item) {
            if (! is_array($item)) {
                continue;
            }

            $legacyId = (int) ($item['id'] ?? 0);
            $idSecure = $this->normalizeNullableString((string) ($item['id_secure'] ?? ''));

            if ($legacyId <= 0 || $idSecure === null) {
                continue;
            }

            $template = AiTemplate::query()->firstOrNew([
                'id_secure' => $idSecure,
            ]);

            if (! $template->exists) {
                $template->id = $legacyId;
            }

            $template->cate_id = $this->resolveCategoryIdByName(
                $this->normalizeNullableString((string) ($item['category_name'] ?? '')),
                $categoryIdsByName
            );
            $template->content = $this->normalizeNullableString((string) ($item['content'] ?? ''));
            $template->status = (bool) ($item['status'] ?? false);
            $template->changed = $this->normalizeNullableInt((string) ($item['changed'] ?? ''));
            $template->created = $this->normalizeNullableInt((string) ($item['created'] ?? ''));
            $template->save();
        }
    }

    private function resolveCategoryIdByName(?string $name, array $categoryIdsByName): ?int
    {
        if ($name === null || $name === '') {
            return null;
        }

        return isset($categoryIdsByName[$name]) ? (int) $categoryIdsByName[$name] : null;
    }

    private function normalizeNullableString(string $value): ?string
    {
        $value = trim($value);

        if (strcasecmp($value, 'NULL') === 0) {
            return null;
        }

        return $value;
    }

    private function normalizeNullableInt(string $value): ?int
    {
        $value = trim($value);

        if (strcasecmp($value, 'NULL') === 0 || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
