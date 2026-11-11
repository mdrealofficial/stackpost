<?php
namespace App\Social\Automation;

use App\Social\Models\SocialInboxMessage as Message;

class Matcher
{
    public function matches(array $s, string $text, ?string $postId = null): bool
    {
        $text = mb_strtolower(trim($text));
        foreach ($this->lines($s['exclude'] ?? '') as $word) {
            if (str_contains($text, mb_strtolower($word))) return false;
        }
        $posts = $this->lines($s['post_ids'] ?? '');
        if ($posts && !in_array($postId, $posts, true)) return false;
        if (($s['match'] ?? 'contains') === 'all') return true;
        foreach ($this->lines($s['keywords'] ?? '') as $word) {
            $word = mb_strtolower($word);
            if (($s['match'] ?? '') === 'exact' ? $text === $word : str_contains($text, $word)) return true;
        }
        return false;
    }

    public function lines(string $text): array
    {
        return array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/u', $text) ?: []))));
    }

    public function withinHours(array $s): bool
    {
        if (empty($s['hours_enabled'])) return true;
        $hour = now()->timezone($s['timezone'] ?? 'UTC')->format('H:i');
        $start = $s['start'] ?? '09:00'; $end = $s['end'] ?? '18:00';
        return $start <= $end ? $hour >= $start && $hour < $end : $hour >= $start || $hour < $end;
    }
}
