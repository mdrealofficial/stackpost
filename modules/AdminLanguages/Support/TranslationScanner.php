<?php

namespace Modules\AdminLanguages\Support;

class TranslationScanner
{
    public function scan(): array
    {
        $translations = [];

        foreach ($this->paths() as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $contents = file_get_contents($file->getRealPath());

                if ($contents === false) {
                    continue;
                }

                preg_match_all('/__\(\s*([\'"])(.+?)\1/s', $contents, $matches);

                foreach ($matches[2] ?? [] as $match) {
                    $translations[trim($match)] = trim($match);
                }
            }
        }

        $translations = array_filter($translations);
        ksort($translations);

        return $translations;
    }

    public function syncFallbackFile(string $locale): array
    {
        $existing = $this->readJson(lang_path("{$locale}.json"));
        $scanned = $this->scan();
        $merged = array_replace($existing, $scanned);

        ksort($merged);

        $this->writeJson(lang_path("{$locale}.json"), $merged);

        return $merged;
    }

    public function readJson(string $path): array
    {
        if (! file_exists($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return [];
        }

        $decoded = json_decode($contents, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function writeJson(string $path, array $lines): void
    {
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0755, true);
        }

        file_put_contents(
            $path,
            json_encode($lines, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    protected function paths(): array
    {
        return [
            app_path(),
            base_path('routes'),
            resource_path('views'),
            resource_path('themes'),
            base_path('modules'),
        ];
    }
}
