<?php

namespace Modules\AdminAITemplate\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\AdminAITemplateCategories\Models\AiTemplateCategory;

class AiTemplate extends Model
{
    protected $table = 'ai_templates';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'cate_id' => 'integer',
            'status' => 'boolean',
            'created' => 'integer',
            'changed' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AiTemplateCategory::class, 'cate_id');
    }

    public function statusLabel(): string
    {
        return $this->status ? __('Enabled') : __('Disabled');
    }

    public function statusVariant(): string
    {
        return $this->status ? 'success' : 'neutral';
    }

    public function createdAtFormatted(string $format = 'Y-m-d H:i'): ?string
    {
        if (! $this->created) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $this->created)->format($format);
    }

    public function contentPreview(int $limit = 120): string
    {
        $content = $this->cleanContent();

        if ($content === '') {
            return __('No template content');
        }

        return mb_strimwidth($content, 0, $limit, '...');
    }

    public function cleanContent(): string
    {
        $content = trim((string) $this->content);

        if ($content === '') {
            return '';
        }

        return trim(str_replace(["\r\n", "\r"], "\n", stripcslashes($content)));
    }
}
