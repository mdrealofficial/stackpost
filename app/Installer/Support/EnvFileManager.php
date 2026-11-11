<?php

namespace App\Installer\Support;

use Illuminate\Support\Facades\File;

class EnvFileManager
{
    public function write(array $values, ?string $path = null): void
    {
        $path ??= base_path('.env');

        $lines = File::exists($path)
            ? preg_split("/(\r\n|\n|\r)/", (string) File::get($path))
            : [];

        $lines = is_array($lines) ? $lines : [];
        $updated = [];

        foreach ($lines as $index => $line) {
            if (! is_string($line) || trim($line) === '' || str_starts_with(ltrim($line), '#')) {
                continue;
            }

            if (! preg_match('/^([A-Z0-9_]+)\s*=\s*(.*)$/i', $line, $matches)) {
                continue;
            }

            $key = (string) $matches[1];

            if (! array_key_exists($key, $values)) {
                continue;
            }

            $lines[$index] = $key.'='.$this->escape($values[$key]);
            $updated[$key] = true;
        }

        foreach ($values as $key => $value) {
            if (isset($updated[$key])) {
                continue;
            }

            $lines[] = $key.'='.$this->escape($value);
        }

        File::put($path, implode(PHP_EOL, $lines).PHP_EOL);
    }

    protected function escape(mixed $value): string
    {
        if ($value === null) {
            return 'null';
        }

        $value = (string) $value;

        if ($value === '') {
            return '""';
        }

        if (preg_match('/^[A-Za-z0-9_\-.:\/\\\\]+$/', $value) === 1) {
            return $value;
        }

        return '"'.str_replace('"', '\"', $value).'"';
    }
}
