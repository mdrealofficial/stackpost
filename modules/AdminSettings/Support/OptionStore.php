<?php

namespace Modules\AdminSettings\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class OptionStore
{
    protected function cacheKey(string $key): string
    {
        return "options.v2.{$key}";
    }

    public function get(string $key, mixed $default = null): mixed
    {
        try {
            if (! Schema::hasTable('options')) {
                return $default;
            }

            $payload = Cache::rememberForever($this->cacheKey($key), function () use ($key) {
                $value = DB::table('options')->where('name', $key)->value('value');

                return [
                    'exists' => $value !== null,
                    'value' => $value,
                ];
            });

            if (! is_array($payload) || ! ($payload['exists'] ?? false)) {
                return $default;
            }

            return $payload['value'];
        } catch (Throwable) {
            return $default;
        }
    }

    public function set(string $key, mixed $value): void
    {
        try {
            if (! Schema::hasTable('options')) {
                return;
            }

            DB::table('options')->updateOrInsert(
                ['name' => $key],
                ['value' => is_scalar($value) || $value === null ? $value : json_encode($value)]
            );

            Cache::forget("options.{$key}");
            Cache::forget($this->cacheKey($key));
        } catch (Throwable) {
            //
        }
    }

    public function forget(string $key): void
    {
        try {
            if (! Schema::hasTable('options')) {
                return;
            }

            DB::table('options')->where('name', $key)->delete();
            Cache::forget("options.{$key}");
            Cache::forget($this->cacheKey($key));
        } catch (Throwable) {
            //
        }
    }
}
