<?php

namespace Modules\AppBulkPosts\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Modules\AdminUser\Models\Team;
use Modules\AppBulkPosts\Jobs\ProcessBulkPostBatchJob;
use Modules\AppBulkPosts\Models\BulkPostBatch;
use Modules\AppBulkPosts\Support\BulkPostAccess;
use Modules\AppBulkPosts\Support\BulkPostCsvService;
use Modules\AppPublishing\Models\PublishingCampaign;
use Modules\AppPublishing\Models\PublishingLabel;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

#[Title('Bulk Import')]
class BulkPostsIndex extends Component
{
    use WithFileUploads;
    use WithPagination;

    #[Locked]
    public string $screen = 'new';

    #[Url(as: 'q', except: '')]
    public string $historySearch = '';

    #[Url(as: 'status', except: 'all')]
    public string $historyStatus = 'all';

    #[Url(as: 'period', except: 'all')]
    public string $historyPeriod = 'all';

    public string $delivery_mode = 'draft';

    public string $start_at = '';

    public bool $allow_invalid = false;

    public bool $allow_duplicate = false;

    public string $message = '';

    #[Locked]
    public ?int $previewId = null;

    #[Locked]
    public ?int $detailId = null;

    #[Locked]
    public ?int $activeBatchId = null;

    #[Locked]
    public string $previewSignature = '';

    /** @var array<int, string> */
    public array $account_ids = [];

    /** @var array<int, string> */
    public array $label_ids = [];

    public mixed $csv_file = null;

    public string $campaign_id = '';

    public int|string $interval_minutes = 60;

    protected BulkPostCsvService $csvService;

    public function boot(BulkPostCsvService $csvService): void
    {
        $this->csvService = $csvService;
    }

    public function mount(?int $batch = null): void
    {
        abort_unless($this->bulkPostsEnabled(), 404);
        if ($batch !== null) {
            $this->batches()->where('status', '!=', 'preview')->findOrFail($batch);
            $this->detailId = $batch;
            $this->screen = 'details';
        } elseif (request()->routeIs('portal.bulk-posts.history')) {
            $this->screen = 'history';
        }
        $owner = TeamWorkspaceAccess::activeTeam(auth()->user())?->owner ?: auth()->user();
        $this->start_at = now($owner->timezone ?: config('app.timezone'))->addDay()->setTime(9, 0)->format('Y-m-d\TH:i');
    }

    public function save(): mixed
    {
        // Retained as a safe compatibility action: saving now prepares a preview.
        $this->previewImport();

        return null;
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['historySearch', 'historyStatus', 'historyPeriod'], true)) {
            $this->resetPage('historyPage');
        }
        if (in_array(explode('.', $property)[0], ['csv_file', 'account_ids', 'label_ids', 'campaign_id', 'interval_minutes', 'delivery_mode', 'start_at'])) {
            $this->previewId = null;
            $this->previewSignature = '';
            $this->allow_invalid = $this->allow_duplicate = false;
            $this->resetValidation();
        }
    }

    protected function signature(): string
    {
        return hash('sha256', json_encode([$this->csv_file?->getFilename(), $this->account_ids, $this->label_ids, $this->campaign_id, $this->interval_minutes, $this->delivery_mode, $this->start_at]));
    }

    public function previewImport(): void
    {
        abort_unless($this->bulkPostsEnabled(), 403);
        $validated = $this->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'interval_minutes' => ['required', 'integer', 'min:1', 'max:43200'],
            'delivery_mode' => ['required', 'in:draft,schedule,csv'],
            'account_ids' => ['required', 'array', 'min:1'],
            'account_ids.*' => ['integer'],
            'campaign_id' => ['nullable', 'integer'],
            'label_ids' => ['nullable', 'array'],
            'label_ids.*' => ['integer'],
        ], ['csv_file.required' => __('Choose a CSV file.'), 'account_ids.required' => __('Choose at least one active channel.')]);

        $workspaceOwnerUserId = $this->workspaceOwnerUserId();
        $campaignId = filled($validated['campaign_id'] ?? null)
            ? PublishingCampaign::query()->ownedBy($workspaceOwnerUserId)->find((int) $validated['campaign_id'])?->id
            : null;
        $labelIds = PublishingLabel::query()
            ->ownedBy($workspaceOwnerUserId)
            ->whereIn('id', collect((array) ($validated['label_ids'] ?? []))->map(fn ($id) => (int) $id)->filter()->all())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        $accountIds = $this->publishableAccountsQuery()
            ->whereIn('id', collect((array) $validated['account_ids'])->map(fn ($id) => (int) $id)->all())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();

        if (count(array_unique($validated['account_ids'])) !== count($accountIds)) {
            throw ValidationException::withMessages(['account_ids' => __('Some channels are no longer available. Choose active channels again.')]);
        }
        if (filled($this->campaign_id) && ! $campaignId) {
            throw ValidationException::withMessages(['campaign_id' => __('Choose an available campaign.')]);
        }
        if (count(array_unique($this->label_ids)) !== count($labelIds)) {
            throw ValidationException::withMessages(['label_ids' => __('Some labels are no longer available.')]);
        }
        $batch = $this->csvService->import($validated['csv_file'], auth()->user(), [
            'account_ids' => $accountIds,
            'interval_minutes' => (int) ($validated['interval_minutes'] ?? 60),
            'campaign_id' => $campaignId,
            'label_ids' => $labelIds,
            'delivery_mode' => $this->delivery_mode,
            'start_at' => $this->start_at,
        ]);
        $this->previewId = $batch->id;
        $this->detailId = null;
        $this->previewSignature = $this->signature();
        $this->allow_invalid = $this->allow_duplicate = false;
        $this->message = '';
        $this->resetPage('rowsPage');
        $this->dispatch('bulk-review-ready');
    }

    protected function batches()
    {
        $team = TeamWorkspaceAccess::activeTeam(auth()->user());
        $query = BulkPostBatch::ownedBy((int) auth()->id());
        if ($team) {
            return $query->where('team_id', $team->id);
        }

        // The personal workspace also owns historical imports in its own teams.
        return $query->where(fn ($q) => $q->whereNull('team_id')->orWhereIn('team_id', Team::where('owner_user_id', auth()->id())->select('id')));
    }

    protected function duplicates(BulkPostBatch $batch)
    {
        return $this->batches()->whereKeyNot($batch->id)->where('meta->fingerprint', data_get($batch->meta, 'fingerprint'))->whereNotNull('meta->confirmed_at');
    }

    public function confirmImport(): void
    {
        abort_unless($this->bulkPostsEnabled(), 403);
        if (! $this->previewId || ! $this->previewSignature || ! hash_equals($this->previewSignature, $this->signature())) {
            throw ValidationException::withMessages(['preview' => __('Your settings changed. Review a new preview before importing.')]);
        }
        $lock = Cache::lock('bulk-posts:owner:'.$this->workspaceOwnerUserId(), 300);
        if (! $lock->get()) {
            $this->message = __('Another import is running. Try again shortly.');

            return;
        }
        try {
            DB::transaction(function () {
                $batch = $this->batches()->lockForUpdate()->findOrFail($this->previewId);
                if (data_get($batch->meta, 'confirmed_at')) {
                    return;
                }
                if (! data_get($batch->stats, 'valid_rows')) {
                    throw ValidationException::withMessages(['preview' => __('No valid rows to import. Correct the CSV and preview it again.')]);
                }
                if (data_get($batch->stats, 'invalid_rows') && ! $this->allow_invalid) {
                    throw ValidationException::withMessages(['preview' => __('Confirm that invalid rows will be excluded, or correct the file first.')]);
                }
                if ($this->duplicates($batch)->exists() && ! $this->allow_duplicate) {
                    throw ValidationException::withMessages(['preview' => __('This file was imported before. Confirm that you want another copy.')]);
                }
                $ids = (array) data_get($batch->meta, 'selected_account_ids', []);
                if ($this->publishableAccountsQuery()->whereIn('id', $ids)->count() !== count($ids)) {
                    throw ValidationException::withMessages(['preview' => __('Channel availability changed. Refresh your preview.')]);
                }
                $meta = $batch->meta;
                $meta['confirmed_at'] = now()->toIso8601String();
                $meta['excluded_rows_acknowledged'] = $this->allow_invalid;
                $meta['duplicate_acknowledged'] = $this->allow_duplicate;
                $batch->update(['status' => 'processing', 'meta' => $meta]);
            });
        } finally {
            $lock->release();
        }
        $this->activeBatchId = $this->previewId;
        $this->detailId = $this->previewId;
        $this->previewId = null;
        $this->previewSignature = '';
        $this->resetPage('historyPage');
        $this->continueImport();
    }

    public function continueImport(): void
    {
        abort_unless($this->bulkPostsEnabled(), 403);
        if (! $this->activeBatchId) {
            return;
        }
        $batch = $this->batches()->findOrFail($this->activeBatchId);
        $result = (new ProcessBulkPostBatchJob($batch->id))->handle();
        $this->message = $result['message'];
        if (! in_array($result['status'], ['processing', 'busy'])) {
            $this->activeBatchId = null;
            if ($this->screen === 'new') {
                $this->redirectRoute('portal.bulk-posts.show', ['batch' => $batch->id], navigate: true);
            }
        }
    }

    public function showBatch(int $id): void
    {
        abort_unless($this->bulkPostsEnabled(), 403);
        $this->batches()->where('status', '!=', 'preview')->findOrFail($id);
        $this->redirectRoute('portal.bulk-posts.show', ['batch' => $id] + $this->historyParameters(), navigate: true);
    }

    public function historyParameters(): array
    {
        return array_filter([
            'q' => $this->historySearch,
            'status' => $this->historyStatus !== 'all' ? $this->historyStatus : null,
            'period' => $this->historyPeriod !== 'all' ? $this->historyPeriod : null,
            'historyPage' => $this->getPage('historyPage') > 1 ? $this->getPage('historyPage') : null,
        ], fn ($value) => $value !== null && $value !== '');
    }

    public function resetHistoryFilters(): void
    {
        $this->reset('historySearch', 'historyStatus', 'historyPeriod');
        $this->resetPage('historyPage');
    }

    protected function historyQuery()
    {
        $query = $this->batches()->where('status', '!=', 'preview');
        $search = trim($this->historySearch);
        if ($search !== '') {
            // Treat filenames literally, including underscores and percent signs.
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
            $query->where(fn ($q) => $q->whereRaw("source_filename LIKE ? ESCAPE '!'", [$pattern])
                ->orWhereRaw("name LIKE ? ESCAPE '!'", [$pattern]));
        }
        if ($this->historyStatus === 'partial') {
            $query->where(fn ($q) => $q->where('status', 'partial')->orWhere(fn ($q) => $q->where('status', 'completed')
                ->where(fn ($q) => $q->where('stats->invalid_rows', '>', 0)->orWhere('stats->failed_rows', '>', 0))));
        } elseif ($this->historyStatus === 'completed') {
            $query->where('status', 'completed')
                ->where(fn ($q) => $q->whereNull('stats->invalid_rows')->orWhere('stats->invalid_rows', '<=', 0))
                ->where(fn ($q) => $q->whereNull('stats->failed_rows')->orWhere('stats->failed_rows', '<=', 0));
        } elseif ($this->historyStatus === 'ready') {
            $query->whereIn('status', ['ready', 'draft']);
        } elseif (in_array($this->historyStatus, ['processing', 'failed', 'blocked'], true)) {
            $query->where('status', $this->historyStatus);
        }
        if (in_array($this->historyPeriod, ['7', '30', '90'], true)) {
            $owner = TeamWorkspaceAccess::activeTeam(auth()->user())?->owner ?: auth()->user();
            $start = now($owner->timezone ?: config('app.timezone'))->startOfDay()->subDays((int) $this->historyPeriod - 1);
            $query->where('created_at', '>=', $start->timezone(config('app.timezone')));
        }

        return $query;
    }

    public function retryBatch(int $id): void
    {
        abort_unless($this->bulkPostsEnabled(), 403);
        $lock = Cache::lock('bulk-posts:batch:'.$id, 300);
        if (! $lock->get()) {
            $this->message = __('This import is processing.');

            return;
        }
        try {
            $batch = $this->batches()->findOrFail($id);
            if (data_get($batch->meta, 'version') !== 2 || ! data_get($batch->meta, 'confirmed_at')) {
                $this->message = __('For older imports, review the original CSV as a new import.');

                return;
            }
            foreach ($batch->rows()->where('status', '!=', 'invalid')->get() as $row) {
                $payload = $row->payload;
                foreach ((array) ($payload['targets'] ?? []) as $key => $target) {
                    if (($target['status'] ?? '') === 'failed') {
                        unset($payload['targets'][$key]);
                    }
                }
                $row->update(['payload' => $payload]);
            }
            $batch->update(['status' => 'processing']);
        } finally {
            $lock->release();
        }
        $this->activeBatchId = $this->detailId = $id;
        $this->continueImport();
    }

    public function downloadErrors(int $id)
    {
        abort_unless($this->bulkPostsEnabled(), 403);
        $batch = $this->batches()->findOrFail($id);

        return response()->streamDownload(function () use ($batch) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['CSV record', 'Caption', 'Channel', 'Error'], ',', '"', '');
            $safe = fn ($v) => preg_match('/^[\s]*[=+@-]/u', (string) $v) ? "'".$v : $v;
            foreach ($batch->rows()->orderBy('row_number')->cursor() as $row) {
                foreach ((array) $row->validation_errors as $error) {
                    fputcsv($out, array_map($safe, [$row->row_number, data_get($row->payload, 'caption'), '', $error]), ',', '"', '');
                }
                foreach ((array) data_get($row->payload, 'targets', []) as $target) {
                    if (($target['status'] ?? '') === 'failed') {
                        fputcsv($out, array_map($safe, [$row->row_number, data_get($row->payload, 'caption'), $target['channel'] ?? '', $target['error'] ?? '']), ',', '"', '');
                    }
                }
            }fclose($out);
        }, 'bulk-import-'.$batch->id.'-errors.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function render(): View
    {
        abort_unless($this->bulkPostsEnabled(), 404);

        $user = auth()->user();
        $accountQuery = $this->publishableAccountsQuery();
        $workspaceOwnerUserId = $this->workspaceOwnerUserId();
        $channelProviderRegistry = collect(channel_provider_cards())->keyBy('key');
        $accounts = (clone $accountQuery)
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'username', 'provider_key', 'category', 'avatar_url']);

        $bulkChannelOptions = $accounts
            ->map(function ($account) use ($channelProviderRegistry) {
                $provider = $channelProviderRegistry->get((string) $account->provider_key, []);
                $providerLabel = (string) data_get($provider, 'label', str($account->provider_key)->headline());
                $capabilityLabel = (string) ($account->category ?: __('Channel'));
                $providerTone = publishing_provider_tone((string) $account->provider_key);

                return [
                    'key' => (string) $account->id,
                    'label' => (string) ($account->display_name ?: ($account->username ? '@'.$account->username : strtoupper($account->provider_key))),
                    'subtitle' => trim($providerLabel.' '.str($capabilityLabel)->lower()),
                    'avatarUrl' => (string) ($account->avatar_url ?? ''),
                    'providerKey' => (string) $account->provider_key,
                    'providerLabel' => $providerLabel,
                    'providerIcon' => (string) data_get($provider, 'icon', ''),
                    'providerColor' => (string) data_get($provider, 'color', ''),
                    'providerToneSurface' => (string) ($providerTone['surface'] ?? ''),
                    'providerToneText' => (string) ($providerTone['text'] ?? ''),
                ];
            })
            ->values()
            ->all();

        $bulkChannelNetworks = $channelProviderRegistry
            ->only($accounts->pluck('provider_key')->filter()->unique()->values()->all())
            ->map(function ($provider) {
                $providerKey = (string) ($provider['key'] ?? '');
                $providerTone = publishing_provider_tone($providerKey);

                return [
                    'key' => $providerKey,
                    'label' => (string) ($provider['label'] ?? ''),
                    'icon' => (string) ($provider['icon'] ?? ''),
                    'color' => (string) ($provider['color'] ?? ''),
                    'toneSurface' => (string) ($providerTone['surface'] ?? ''),
                    'toneText' => (string) ($providerTone['text'] ?? ''),
                ];
            })
            ->filter(fn ($provider) => $provider['key'] !== '' && $provider['label'] !== '')
            ->values()
            ->all();

        $selected = $this->screen === 'details'
            ? $this->batches()->where('status', '!=', 'preview')->findOrFail($this->detailId)
            : (($this->previewId ?: $this->detailId) ? $this->batches()->find($this->previewId ?: $this->detailId) : null);
        $owner = TeamWorkspaceAccess::activeTeam($user)?->owner ?: $user;

        return view('appbulkposts::livewire.index', [
            'historyParameters' => $this->historyParameters(),
            'selectedBatch' => $selected,
            'previewRows' => $selected?->rows()->orderBy('row_number')->paginate(15, ['*'], 'rowsPage'),
            'previousImport' => $selected && data_get($selected->meta, 'fingerprint') ? $this->duplicates($selected)->latest('id')->first() : null,
            'batches' => $this->screen === 'history' ? $this->historyQuery()->latest('id')->paginate(10, ['*'], 'historyPage') : null,
            'rowLimit' => BulkPostAccess::rowLimit($user, TeamWorkspaceAccess::activeTeam($user)),
            'timezone' => $owner->timezone ?: config('app.timezone'),
            'bulkChannelOptions' => $bulkChannelOptions,
            'bulkChannelNetworks' => $bulkChannelNetworks,
            'campaigns' => PublishingCampaign::query()
                ->ownedBy($workspaceOwnerUserId)
                ->orderBy('name')
                ->get(['id', 'name', 'status']),
            'labels' => PublishingLabel::query()
                ->ownedBy($workspaceOwnerUserId)
                ->orderBy('name')
                ->get(['id', 'name']),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Bulk Import'),
        ]);
    }

    protected function workspaceOwnerUserId(): int
    {
        return TeamWorkspaceAccess::workspaceOwnerUserId(auth()->user());
    }

    protected function bulkPostsEnabled(): bool
    {
        return BulkPostAccess::allowed(auth()->user(), TeamWorkspaceAccess::activeTeam(auth()->user()));
    }

    protected function publishableAccountsQuery()
    {
        return BulkPostAccess::accounts(auth()->user(), TeamWorkspaceAccess::activeTeam(auth()->user()));
    }
}
