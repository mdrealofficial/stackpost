<?php

namespace Modules\AdminLanguages\Support;

use Stichoza\GoogleTranslate\GoogleTranslate;

class GoogleTranslationService
{
    public function translateArray(array $translations, string $targetLocale, bool $missingOnly = false): array
    {
        if ($translations === [] || $targetLocale === '') {
            return [];
        }

        $keys = array_keys($translations);
        $prepared = [];
        $placeholderMaps = [];

        foreach ($translations as $key => $value) {
            [$prepared[$key], $placeholderMaps[$key]] = $this->protectPlaceholders((string) $value);
        }

        $values = array_values($prepared);
        $batches = $this->makeBatches($keys, $values);
        $translated = [];

        foreach ($batches as $batch) {
            $translator = new GoogleTranslate;
            $translator->setSource(config('app.fallback_locale', 'en'));
            $translator->setTarget($targetLocale);

            $output = $translator->translate($batch['text']);
            $lines = $this->fixTranslate($output);

            foreach ($lines as $line) {
                if (! preg_match('/^\[\[I_(\d+)\]\](.*)$/u', $line, $matches)) {
                    continue;
                }

                $index = (int) $matches[1];
                $value = $this->capitalizeFirstLetter(trim($matches[2]));

                if (isset($keys[$index])) {
                    $key = $keys[$index];
                    $translated[$key] = $this->restorePlaceholders($value, $placeholderMaps[$key] ?? []);
                }
            }
        }

        foreach ($translations as $key => $value) {
            $translated[$key] ??= $value;
        }

        ksort($translated);

        return $translated;
    }

    protected function makeBatches(array $keys, array $values): array
    {
        $maxLength = 4000;
        $batches = [];
        $batchText = '';
        $batchKeys = [];

        foreach ($values as $index => $text) {
            $line = "[[I_{$index}]]".$text;
            $candidate = $batchText === '' ? $line : $batchText."\n".$line;

            if (strlen($candidate) > $maxLength) {
                $batches[] = ['keys' => $batchKeys, 'text' => $batchText];
                $batchText = $line;
                $batchKeys = [$keys[$index]];

                continue;
            }

            $batchText = $candidate;
            $batchKeys[] = $keys[$index];
        }

        if ($batchText !== '') {
            $batches[] = ['keys' => $batchKeys, 'text' => $batchText];
        }

        return $batches;
    }

    protected function fixTranslate(string $translatedText): array
    {
        $translatedText = str_replace(['HH: MM', 'Ã‚Â© ', '# ', "\r"], ['HH:MM', 'Ã‚Â©', '#', ''], $translatedText);
        $translatedText = preg_replace_callback('/%(\d+\$)?[A-Z]/', function ($matches) {
            $number = $matches[1] ?? '';
            $letter = strtolower(substr($matches[0], -1));

            return '%'.$number.$letter;
        }, $translatedText);

        return explode("\n", trim((string) $translatedText));
    }

    protected function capitalizeFirstLetter(string $text): string
    {
        $firstLetter = mb_substr($text, 0, 1, 'UTF-8');
        $rest = mb_substr($text, 1, null, 'UTF-8');

        return mb_strtoupper($firstLetter, 'UTF-8').$rest;
    }

    protected function protectPlaceholders(string $text): array
    {
        $placeholders = [];
        $index = 0;

        $protected = preg_replace_callback('/(?<!\w):[A-Za-z_][A-Za-z0-9_]*|%(?:\d+\$)?[A-Za-z]/u', function (array $matches) use (&$placeholders, &$index) {
            $token = "[[PH_{$index}]]";
            $placeholders[$index] = $matches[0];
            $index++;

            return $token;
        }, $text);

        return [$protected ?? $text, $placeholders];
    }

    protected function restorePlaceholders(string $text, array $placeholders): string
    {
        if ($placeholders === []) {
            return $text;
        }

        return preg_replace_callback('/\[\[\s*PH_(\d+)\s*\]\]/iu', function (array $matches) use ($placeholders) {
            $index = (int) $matches[1];

            return $placeholders[$index] ?? $matches[0];
        }, $text) ?? $text;
    }
}
