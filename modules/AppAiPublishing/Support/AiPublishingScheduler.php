<?php

namespace Modules\AppAiPublishing\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;
use Modules\AppAiPublishing\Models\AiPublishingRun;
use RuntimeException;

class AiPublishingScheduler
{
    public function nextForRun(AiPublishingRun $run): ?CarbonInterface
    {
        if ($run->isDemo() || $run->hasEnded()) {
            return null;
        }
        $timezone = $run->timezone();
        $config = (array) $run->schedule_config;
        $today = now($timezone)->startOfDay();
        $start = filled($config['start_at'] ?? null) ? Carbon::parse($config['start_at'], $timezone)->startOfDay() : $today;
        if ($start->lt($today)) {
            $config['start_at'] = $today->toDateString();
        }
        $last = (int) data_get($run->stats, 'last_slot_at', 0);
        $cursor = $last > 0 ? Carbon::createFromTimestamp($last, $timezone) : $run->last_processed_at;
        if (! $cursor && data_get($run->stats, 'schedule_anchor_at')) {
            $cursor = Carbon::createFromTimestamp((int) data_get($run->stats, 'schedule_anchor_at'), $timezone);
        }

        if ($cursor && $cursor->lt($today)) {
            $cursor = $today->copy()->subSecond();
        }

        return $this->nextDueSlot($config, $timezone, $cursor);
    }

    public function nextDueSlot(array $config, string $timezone, ?CarbonInterface $lastProcessedAt = null): ?CarbonInterface
    {
        $timeSlots = collect((array) ($config['time_slots'] ?? []))
            ->map(fn ($slot) => trim((string) $slot))
            ->filter(fn ($slot) => preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $slot) === 1)
            ->sort()
            ->values();
        $weekdayMap = [
            'sun' => Carbon::SUNDAY,
            'mon' => Carbon::MONDAY,
            'tue' => Carbon::TUESDAY,
            'wed' => Carbon::WEDNESDAY,
            'thu' => Carbon::THURSDAY,
            'fri' => Carbon::FRIDAY,
            'sat' => Carbon::SATURDAY,
        ];
        $allowedWeekdays = collect((array) ($config['weekdays'] ?? []))
            ->map(fn ($day) => $weekdayMap[strtolower(trim((string) $day))] ?? null)
            ->filter(fn ($day) => $day !== null)
            ->values()
            ->all();

        if ($timeSlots->isEmpty() || $allowedWeekdays === []) {
            return null;
        }

        $configuredStartAt = filled($config['start_at'] ?? null)
            ? Carbon::parse((string) $config['start_at'], $timezone)->startOfDay()
            : now($timezone)->startOfDay();
        $endAt = filled($config['end_at'] ?? null)
            ? Carbon::parse((string) $config['end_at'], $timezone)->endOfDay()
            : $configuredStartAt->copy()->addMonths(3)->endOfDay();

        if ($endAt->lt($configuredStartAt)) {
            return null;
        }

        $cursor = ($lastProcessedAt
            ? Carbon::parse($lastProcessedAt)->timezone($timezone)
            : $configuredStartAt
        )->copy()->startOfDay();

        for ($dayOffset = 0; $dayOffset <= 370; $dayOffset++) {
            $date = $cursor->copy()->addDays($dayOffset);

            if ($date->gt($endAt)) {
                break;
            }

            if (! in_array($date->dayOfWeek, $allowedWeekdays, true)) {
                continue;
            }

            foreach ($timeSlots as $timeSlot) {
                [$hour, $minute] = array_pad(explode(':', $timeSlot), 2, '0');
                $slot = $date->copy()->setTime((int) $hour, (int) $minute);

                if ($slot->lt($configuredStartAt) || $slot->gt($endAt)) {
                    continue;
                }

                if ($lastProcessedAt && $slot->lessThanOrEqualTo(Carbon::parse($lastProcessedAt)->timezone($timezone))) {
                    continue;
                }

                return $slot;
            }
        }

        return null;
    }

    /**
     * @return array<int, CarbonInterface>
     */
    public function buildSlots(array $config, int $needed, string $timezone): array
    {
        $timeSlots = collect((array) ($config['time_slots'] ?? []))
            ->map(fn ($slot) => trim((string) $slot))
            ->filter(fn ($slot) => preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $slot) === 1)
            ->unique()->sort()
            ->values();
        $weekdays = collect((array) ($config['weekdays'] ?? []))
            ->map(fn ($day) => strtolower(trim((string) $day)))
            ->filter()
            ->values()
            ->all();

        if ($timeSlots->isEmpty()) {
            throw new RuntimeException(__('At least one schedule time is required.'));
        }

        if ($weekdays === []) {
            throw new RuntimeException(__('At least one weekday is required.'));
        }

        $configuredStartAt = filled($config['start_at'] ?? null)
            ? Carbon::parse((string) $config['start_at'], $timezone)
            : now($timezone);
        $now = now($timezone);
        $startAt = $configuredStartAt->lt($now)
            ? $now->copy()
            : $configuredStartAt->copy();
        $endAt = filled($config['end_at'] ?? null)
            ? Carbon::parse((string) $config['end_at'], $timezone)->endOfDay()
            : $startAt->copy()->addMonths(3)->endOfDay();

        if ($endAt->lt($startAt)) {
            throw new RuntimeException(__('End date must be after the start date.'));
        }

        $weekdayMap = [
            'sun' => Carbon::SUNDAY,
            'mon' => Carbon::MONDAY,
            'tue' => Carbon::TUESDAY,
            'wed' => Carbon::WEDNESDAY,
            'thu' => Carbon::THURSDAY,
            'fri' => Carbon::FRIDAY,
            'sat' => Carbon::SATURDAY,
        ];

        $allowedWeekdays = collect($weekdays)
            ->map(fn ($day) => $weekdayMap[$day] ?? null)
            ->filter(fn ($value) => $value !== null)
            ->values()
            ->all();

        $slots = [];
        $cursor = $startAt->copy()->startOfDay();

        while (count($slots) < $needed && $cursor->lte($endAt)) {
            if (in_array($cursor->dayOfWeek, $allowedWeekdays, true)) {
                foreach ($timeSlots as $timeSlot) {
                    [$hour, $minute] = array_pad(explode(':', $timeSlot), 2, '0');
                    $scheduled = $cursor->copy()->setTime((int) $hour, (int) $minute);

                    if ($scheduled->lt($startAt) || $scheduled->gt($endAt)) {
                        continue;
                    }

                    $slots[] = $scheduled->copy();

                    if (count($slots) >= $needed) {
                        break;
                    }
                }
            }

            $cursor = $cursor->copy()->addDay();
        }

        if (count($slots) < $needed) {
            throw new RuntimeException(__('Not enough schedule slots are available for the selected date range and weekdays.'));
        }

        return $slots;
    }
}
