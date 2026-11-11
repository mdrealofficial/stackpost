<?php

namespace Modules\AdminAITemplateCategories\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\AdminAITemplate\Models\AiTemplate;

class AiTemplateCategory extends Model
{
    protected $table = 'ai_template_categories';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'status' => 'boolean',
            'created' => 'integer',
            'changed' => 'integer',
        ];
    }

    public function templates(): HasMany
    {
        return $this->hasMany(AiTemplate::class, 'cate_id');
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

    public function colorClasses(): array
    {
        return match ((string) $this->color) {
            'success' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-600', 'ring' => 'ring-emerald-200'],
            'danger' => ['bg' => 'bg-rose-100', 'text' => 'text-rose-600', 'ring' => 'ring-rose-200'],
            'warning' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-600', 'ring' => 'ring-amber-200'],
            'info' => ['bg' => 'bg-sky-100', 'text' => 'text-sky-600', 'ring' => 'ring-sky-200'],
            'dark' => ['bg' => 'bg-slate-200', 'text' => 'text-slate-700', 'ring' => 'ring-slate-300'],
            default => ['bg' => 'bg-violet-100', 'text' => 'text-violet-600', 'ring' => 'ring-violet-200'],
        };
    }
}
