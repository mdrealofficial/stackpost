<?php

namespace Modules\AdminFaqs\Models;

use App\Concerns\HasLocalizedAttributes;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    use HasLocalizedAttributes;

    protected $table = 'faqs';

    protected $guarded = [];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'title_translations' => 'array',
            'content_translations' => 'array',
            'status' => 'boolean',
            'changed' => 'integer',
            'created' => 'integer',
        ];
    }

    public function titleForLocale(?string $locale = null): string
    {
        return $this->localized('title', $locale);
    }

    public function contentForLocale(?string $locale = null): string
    {
        return $this->localized('content', $locale);
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

    public function contentPreview(int $limit = 160, ?string $locale = null): string
    {
        $content = trim(strip_tags($this->contentForLocale($locale)));

        if ($content === '') {
            return __('No content');
        }

        return mb_strimwidth($content, 0, $limit, '...');
    }

    public static function cleanTranslations(array $translations, int $max = 65535): array
    {
        return self::sanitizeTranslations($translations, $max);
    }
}
