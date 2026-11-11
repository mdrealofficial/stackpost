<?php

namespace Modules\AppBulkPosts\Support;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\AdminUser\Models\User;
use Modules\AppBulkPosts\Models\BulkPostBatch;
use Modules\AppPublishing\Support\PublishingContentValidationRegistry;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

class BulkPostCsvService
{
    public const COLUMNS = ['caption', 'schedule_time', 'link', 'media_url', 'status_mode'];

    public function import(UploadedFile $file, User $user, array $options = []): BulkPostBatch
    {
        $team = TeamWorkspaceAccess::activeTeam($user);
        abort_unless(BulkPostAccess::allowed($user, $team), 403);
        $owner = $team?->owner ?: $user;
        $timezone = $owner->timezone ?: config('app.timezone', 'UTC');
        $ids = array_values(array_unique(array_map('intval', (array) ($options['account_ids'] ?? []))));
        $accounts = BulkPostAccess::accounts($user, $team)->whereIn('id', $ids)->get();
        if (! $ids || count($ids) !== $accounts->count()) {
            $this->fail('Choose active channels you can access. Refresh your selection if a channel was disconnected.', 'account_ids');
        }
        $mode = $options['delivery_mode'] ?? 'draft';
        if (! in_array($mode, ['draft', 'schedule', 'csv'], true)) {
            $this->fail('Choose a valid delivery mode.');
        }
        if ($mode !== 'draft' && ! TeamWorkspaceAccess::hasPermission($user, 'post.publish', $team)) {
            $this->fail('You can import drafts. Publishing permission is required to schedule posts.', 'delivery_mode');
        }
        $interval = (int) ($options['interval_minutes'] ?? 60);
        if ($interval < 1 || $interval > 43200) {
            $this->fail('Choose an interval between 1 and 43,200 minutes.', 'interval_minutes');
        }
        $start = null;
        if ($mode !== 'draft') {
            $start = $this->parseDate((string) ($options['start_at'] ?? ''), $timezone);
            if (! $start || $start->lte(now())) {
                $this->fail('Choose a future start time for rows without a date.', 'start_at');
            }
        }
        if ($file->getSize() > 10 * 1024 * 1024) {
            $this->fail('CSV files must be 10 MB or smaller.');
        }
        $limit = BulkPostAccess::rowLimit($user, $team);
        if ($limit === 0) {
            $this->fail('Your plan does not allow CSV rows.');
        }
        $handle = fopen($file->getRealPath(), 'rb');
        if (! $handle) {
            $this->fail('The CSV file could not be read.');
        }
        $rows = [];
        $fallbackIndex = 0;
        try {
            $headers = fgetcsv($handle, null, ',', '"', '');
            if (! $headers) {
                $this->fail('Add a header and at least one data row.');
            }
            $headers = array_map(fn ($h) => Str::of(ltrim((string) $h, "\xEF\xBB\xBF"))->trim()->lower()->replace([' ', '-'], '_')->value(), $headers);
            if (! in_array('caption', $headers, true)) {
                $this->fail('Missing caption column. Download the template to see the supported columns.');
            }
            if (count(array_unique($headers)) !== count($headers)) {
                $this->fail('CSV headers must be unique.');
            }
            if (array_diff($headers, self::COLUMNS)) {
                $this->fail('Unsupported columns: '.implode(', ', array_diff($headers, self::COLUMNS)).'. Use the template; choose channels in the form.');
            }
            $record = 1;
            while (! feof($handle)) {
                $offset = ftell($handle);
                $values = fgetcsv($handle, null, ',', '"', '');
                if ($values === false) {
                    break;
                }
                $end = ftell($handle);
                if ($values === [null]) {
                    continue;
                }
                $record++;
                if (count($rows) >= $limit) {
                    $this->fail("This import allows up to {$limit} rows. Split the file into smaller imports.");
                }
                if ((count($rows) + 1) * count($ids) > 10000) {
                    $this->fail('An import can create at most 10,000 posts. Choose fewer rows or channels.');
                }
                $errors = [];
                if ($end - $offset > 100000) {
                    $this->fail('A CSV record is too large. Keep captions under 10,000 characters.');
                }
                fseek($handle, $offset);
                $raw = fread($handle, $end - $offset);
                fseek($handle, $end);
                if (preg_match('/\A(?:[^,"\r\n]*|"(?:[^"]|"")*")(?:,(?:[^,"\r\n]*|"(?:[^"]|"")*"))*(?:\r\n|\n|\r)?\z/s', $raw) !== 1) {
                    $errors[] = __('Malformed CSV quotes. Escape a quote as two quotes inside a quoted cell.');
                }
                if (count($values) !== count($headers)) {
                    $errors[] = __('The number of cells does not match the header.');
                }
                $data = [];
                foreach ($headers as $i => $header) {
                    $data[$header] = trim((string) ($values[$i] ?? ''));
                }
                $caption = $data['caption'] ?? '';
                if ($caption === '') {
                    $errors[] = __('Caption is required.');
                }
                if (! mb_check_encoding($caption, 'UTF-8')) {
                    $this->fail('Save the file as UTF-8 CSV.');
                }
                if (mb_strlen($caption) > 10000) {
                    $errors[] = __('Caption exceeds 10,000 characters.');
                }
                $csvMode = strtolower($data['status_mode'] ?? '') ?: 'draft';
                if (! in_array($csvMode, ['draft', 'schedule'], true)) {
                    $errors[] = __('Status mode must be draft or schedule.');
                }
                $resolvedMode = $mode === 'csv' ? $csvMode : $mode;
                $schedule = trim($data['schedule_time'] ?? '');
                $date = $schedule !== '' ? $this->parseDate($schedule, $timezone) : null;
                if ($schedule !== '' && ! $date) {
                    $errors[] = __('Use a real date in YYYY-MM-DD HH:MM or YYYY-MM-DD HH:MM:SS format.');
                }
                if ($resolvedMode === 'schedule') {
                    if ($schedule === '') {
                        $date = $start->copy()->addMinutes($fallbackIndex++ * $interval);
                    }
                    if ($date && $date->lte(now())) {
                        $errors[] = __('Scheduled time is in the past. Choose a future time or import as drafts.');
                    }
                }
                $link = $data['link'] ?? '';
                if ($link !== '' && ! $this->webUrl($link)) {
                    $errors[] = __('Link must be a valid HTTP or HTTPS URL.');
                }
                $media = $data['media_url'] ?? '';
                if ($media !== '') {
                    $errors[] = __('CSV media download is not supported yet. Remove media_url to import text drafts, then attach media in Publishing.');
                }
                if ($resolvedMode === 'schedule') {
                    foreach ($accounts as $account) {
                        $issue = $this->contentError($account->provider_key, $caption);
                        if ($issue) {
                            $errors[] = ($account->display_name ?: $account->provider_key).': '.$issue;
                        }
                    }
                }
                $rows[] = ['row_number' => $record, 'status' => $errors ? 'invalid' : 'valid', 'validation_errors' => array_values(array_unique($errors)),
                    'payload' => ['caption' => $caption, 'link' => $link ?: null, 'media_url' => $media ?: null, 'status_mode' => $resolvedMode, 'schedule_time' => $schedule, 'schedule_time_iso' => $date?->toIso8601String(), 'timezone' => $timezone, 'targets' => []]];
            }
        } finally {
            fclose($handle);
        }
        if (! $rows) {
            $this->fail('Add at least one data row below the header.');
        }
        $valid = count(array_filter($rows, fn ($r) => $r['status'] === 'valid'));

        return DB::transaction(function () use ($file, $user, $team, $owner, $timezone, $ids, $options, $mode, $interval, $rows, $valid) {
            $batch = BulkPostBatch::create(['owner_user_id' => $user->id, 'team_id' => $team?->id, 'name' => Str::limit(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME), 150, ''), 'source_filename' => Str::limit($file->getClientOriginalName(), 250, ''), 'status' => 'preview',
                'meta' => ['version' => 2, 'workspace_owner_user_id' => $owner->id, 'timezone' => $timezone, 'fingerprint' => hash_file('sha256', $file->getRealPath()), 'selected_account_ids' => $ids, 'campaign_id' => $options['campaign_id'] ?? null, 'label_ids' => $options['label_ids'] ?? [], 'delivery_mode' => $mode, 'interval_minutes' => $interval, 'start_at' => $options['start_at'] ?? null],
                'stats' => ['total_rows' => count($rows), 'valid_rows' => $valid, 'invalid_rows' => count($rows) - $valid, 'intended_posts' => $valid * count($ids), 'created_posts' => 0, 'failed_targets' => 0, 'pending_targets' => $valid * count($ids), 'failed_rows' => 0]]);
            $batch->rows()->createMany($rows);

            return $batch;
        });
    }

    public function contentError(string $provider, string $caption): ?string
    {
        $issue = app(PublishingContentValidationRegistry::class)->validate($provider, ['provider_key' => $provider, 'caption' => $caption, 'media_items' => [], 'options' => ['post_to' => 'feed']]);

        return $issue['message'] ?? null;
    }

    public function parseDate(string $value, string $timezone): ?Carbon
    {
        $value = str_replace('T', ' ', trim($value));
        if (! preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}(?::\d{2})?$/', $value)) {
            return null;
        }
        $format = strlen($value) === 16 ? 'Y-m-d H:i' : 'Y-m-d H:i:s';
        try {
            $date = Carbon::createFromFormat('!'.$format, $value, $timezone);

            return $date && $date->format($format) === $value ? $date : null;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function webUrl(string $url): bool
    {
        return filter_var($url, FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?: ''), ['http', 'https'], true);
    }

    protected function fail(string $message, string $field = 'csv_file'): never
    {
        throw ValidationException::withMessages([$field => __($message)]);
    }

    public function templateRows(): array
    {
        return [self::COLUMNS, ['A product tip for your audience', '', '', '', 'draft'], ['A second caption, with a comma', '', 'https://example.com', '', 'draft']];
    }
}
