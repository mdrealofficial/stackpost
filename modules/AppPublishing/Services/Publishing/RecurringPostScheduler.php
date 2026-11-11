<?php

namespace Modules\AppPublishing\Services\Publishing;

use Carbon\Carbon;
use Illuminate\Support\Str;
use Modules\AppPublishing\Models\PublishingPost;

class RecurringPostScheduler
{
    protected const CUSTOM_MARKER = 'publishing-repeat';

    public function createNextAfterSuccessfulPublish(PublishingPost $post): ?PublishingPost
    {
        $data = is_array($post->data) ? $post->data : [];
        $options = is_array($data['options'] ?? null) ? $data['options'] : [];
        $repeatRule = (string) ($options['repeat_rule'] ?? 'none');

        if (! in_array($repeatRule, ['weekday', 'weekly_custom'], true)) {
            return null;
        }

        $currentTimestamp = (int) ($post->time_post ?: now()->timestamp);
        $timezone = (string) ($options['timezone'] ?? config('app.timezone', 'UTC'));
        $current = Carbon::createFromTimestamp($currentTimestamp, $timezone);
        $until = $this->repeatUntil($options, $timezone);

        if (! $until) {
            return null;
        }

        $next = $this->nextOccurrence($current, $repeatRule, (array) ($options['repeat_days'] ?? []), $until);

        if (! $next) {
            return null;
        }

        $originId = $this->originId($post, $options);

        $exists = PublishingPost::query()
            ->where('function', 'post')
            ->where('account_id', (int) $post->account_id)
            ->where('custom_data_3', self::CUSTOM_MARKER)
            ->where('custom_data_1', (string) $originId)
            ->where('custom_data_2', (string) $next->timestamp)
            ->exists();

        if ($exists) {
            return null;
        }

        $nextOptions = [
            ...$options,
            'repeat_origin_id' => $originId,
            'repeat_previous_id' => (int) $post->id,
            'repeat_created_after_publish' => true,
        ];
        $data['options'] = $nextOptions;

        $now = now()->timestamp;

        return PublishingPost::query()->create([
            'id_secure' => Str::random(32),
            'user_id' => (int) $post->user_id,
            'team_id' => $post->team_id,
            'campaign' => $post->campaign,
            'labels' => is_array($post->labels) ? $post->labels : [],
            'account_id' => $post->account_id,
            'social_network' => (string) $post->social_network,
            'category' => (string) $post->category,
            'module' => (string) $post->module,
            'function' => 'post',
            'api_type' => (int) ($post->api_type ?: 1),
            'type' => (string) $post->type,
            'method' => (string) ($post->method ?: 'basic'),
            'query_id' => null,
            'data' => $data,
            'time_post' => $next->timestamp,
            'delay' => 0,
            'repost_frequency' => 0,
            'repost_until' => $until->timestamp,
            'result' => null,
            'tmp' => null,
            'custom_data_1' => (string) $originId,
            'custom_data_2' => (string) $next->timestamp,
            'custom_data_3' => self::CUSTOM_MARKER,
            'status' => PublishingPost::STATUS_SCHEDULED,
            'changed' => $now,
            'created' => $now,
        ]);
    }

    protected function repeatUntil(array $options, string $timezone): ?Carbon
    {
        $value = trim((string) ($options['repeat_until'] ?? ''));

        if ($value === '') {
            return null;
        }

        try {
            return Carbon::parse($value, $timezone)->endOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function nextOccurrence(Carbon $current, string $repeatRule, array $repeatDays, Carbon $until): ?Carbon
    {
        $selectedWeekdays = $this->selectedWeekdays($repeatDays);
        $cursor = $current->copy()->addDay();

        while ($cursor->lte($until)) {
            $include = match ($repeatRule) {
                'weekday' => $cursor->isWeekday(),
                'weekly_custom' => in_array($cursor->dayOfWeekIso, $selectedWeekdays, true),
                default => false,
            };

            if ($include) {
                return $cursor;
            }

            $cursor->addDay();
        }

        return null;
    }

    protected function selectedWeekdays(array $repeatDays): array
    {
        $dayMap = [
            'mon' => Carbon::MONDAY,
            'tue' => Carbon::TUESDAY,
            'wed' => Carbon::WEDNESDAY,
            'thu' => Carbon::THURSDAY,
            'fri' => Carbon::FRIDAY,
            'sat' => Carbon::SATURDAY,
            'sun' => Carbon::SUNDAY,
        ];

        return collect($repeatDays)
            ->map(fn ($day) => $dayMap[strtolower(trim((string) $day))] ?? null)
            ->filter(fn ($day) => $day !== null)
            ->unique()
            ->values()
            ->all();
    }

    protected function originId(PublishingPost $post, array $options): int
    {
        $optionOrigin = (int) ($options['repeat_origin_id'] ?? 0);

        if ($optionOrigin > 0) {
            return $optionOrigin;
        }

        if ((string) ($post->custom_data_3 ?? '') === self::CUSTOM_MARKER && is_numeric($post->custom_data_1)) {
            return (int) $post->custom_data_1;
        }

        return (int) $post->id;
    }
}
