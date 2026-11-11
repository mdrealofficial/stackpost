<?php

namespace App\Support;

use DateTimeImmutable;
use DateTimeZone;

class TimezoneCatalog
{
    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        static $timezones = null;

        if ($timezones !== null) {
            return $timezones;
        }

        $identifiers = DateTimeZone::listIdentifiers(DateTimeZone::ALL);

        usort($identifiers, static function (string $left, string $right): int {
            if ($left === 'UTC') {
                return -1;
            }

            if ($right === 'UTC') {
                return 1;
            }

            return strcasecmp($left, $right);
        });

        return $timezones = array_values(array_unique($identifiers));
    }

    /**
     * @return array<int, array{value:string,label:string,offset:string,seconds:int}>
     */
    public static function options(): array
    {
        static $options = null;

        if ($options !== null) {
            return $options;
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $options = [];

        foreach (self::all() as $timezone) {
            $zone = new DateTimeZone($timezone);
            $seconds = $zone->getOffset($now);
            $offset = self::formatOffset($seconds);

            $options[] = [
                'value' => $timezone,
                'label' => sprintf('(UTC%s) %s', $offset, $timezone),
                'offset' => $offset,
                'seconds' => $seconds,
            ];
        }

        usort($options, static function (array $left, array $right): int {
            if ($left['value'] === 'UTC') {
                return -1;
            }

            if ($right['value'] === 'UTC') {
                return 1;
            }

            return [$left['seconds'], $left['value']] <=> [$right['seconds'], $right['value']];
        });

        return $options;
    }

    public static function label(?string $timezone): string
    {
        $timezone = (string) ($timezone ?: 'UTC');

        foreach (self::options() as $option) {
            if ($option['value'] === $timezone) {
                return $option['label'];
            }
        }

        return sprintf('(UTC%s) %s', self::formatOffset(0), $timezone);
    }

    protected static function formatOffset(int $seconds): string
    {
        $sign = $seconds < 0 ? '-' : '+';
        $seconds = abs($seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return sprintf('%s%02d:%02d', $sign, $hours, $minutes);
    }
}
