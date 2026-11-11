<?php

namespace Modules\AppAIVideo\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Throwable;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AppAIStudio\Models\AIPromptHistory;
use Modules\AppAIStudio\Support\AIStudioAccess;
use Modules\AppAIStudio\Support\AiContentStudioService;
use Modules\AppAIVideo\Models\AIVideoJob;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

#[Title('AI Video')]
class AIVideoIndex extends Component
{
    use AIStudioAccess;

    public string $prompt = '';
    public string $duration = '8';
    public string $format = 'vertical-short';
    public array $result = [];
    public ?int $jobId = null;

    public function mount(AiContentStudioService $studio): void
    {
        abort_unless($this->aiStudioFeatureEnabled('ai_studio_video'), 404);
        $this->format = (string) $this->aiStudioSetting('video_format', $this->format);
        $this->duration = (string) $this->aiStudioSetting('video_duration', $this->duration);
        $this->restoreLatestJob();
        $this->syncDurationSelection($studio);
    }

    public function generate(AiContentStudioService $studio): void
    {
        abort_unless($this->aiStudioFeatureEnabled('ai_studio_video'), 404);

        $allowedDurations = $this->allowedDurations($studio);

        if ($allowedDurations === []) {
            abort(404);
        }

        $validated = $this->validate([
            'prompt' => ['required', 'string', 'max:5000'],
            'duration' => ['required', Rule::in(array_map('strval', $allowedDurations))],
        ]);

        $this->resetErrorBag();

        try {
            $this->result = $studio->startWorkspaceVideoGeneration(
                user: auth()->user(),
                prompt: trim($validated['prompt']),
                duration: (string) $validated['duration'],
                format: $this->format,
            );
            $job = AIVideoJob::query()->create([
                'owner_user_id' => $this->workspaceOwnerUserId(),
                'requested_by_user_id' => auth()->id(),
                'team_id' => TeamWorkspaceAccess::activeTeam(auth()->user())?->id,
                'external_video_id' => (string) ($this->result['id'] ?? ''),
                'provider' => (string) ($this->result['provider'] ?? ''),
                'model' => (string) ($this->result['model'] ?? ''),
                'status' => (string) ($this->result['status'] ?? 'queued'),
                'progress' => (int) ($this->result['progress'] ?? 0),
                'duration' => (string) ($this->result['duration'] ?? $this->duration),
                'format' => (string) ($this->result['format'] ?? $this->format),
                'size' => (string) ($this->result['size'] ?? ''),
                'prompt' => (string) ($this->result['prompt'] ?? trim($validated['prompt'])),
                'metadata' => $this->jobMetadata($this->result),
                'last_polled_at' => now(),
            ]);

            $this->jobId = (int) $job->id;

            $studio->recordPromptHistory(
                auth()->user(),
                'video',
                trim($validated['prompt']),
                [
                    'duration' => (string) $validated['duration'],
                    'format' => $this->format,
                ],
                [
                    'status' => (string) ($this->result['status'] ?? 'queued'),
                    'provider' => (string) ($this->result['provider'] ?? ''),
                    'model' => (string) ($this->result['model'] ?? ''),
                    'video_id' => (string) ($this->result['id'] ?? ''),
                ],
                [
                    'title' => __('Video - :duration sec - :format', ['duration' => (string) $validated['duration'], 'format' => str($this->format)->replace('-', ' ')->title()]),
                ],
            );

            $this->dispatch('app-toast',
                type: 'success',
                title: __('Video job started'),
                message: __('The video generation job has been queued. Refresh the status until it completes.')
            );
        } catch (Throwable $exception) {
            $message = $exception->getMessage() ?: __('The video could not be generated.');
            $this->addError('prompt', $message);
            $this->dispatch('app-toast',
                type: 'error',
                title: __('Generation failed'),
                message: $message
            );
        }
    }

    public function refreshResult(AiContentStudioService $studio): void
    {
        abort_unless($this->aiStudioFeatureEnabled('ai_studio_video'), 404);

        $job = $this->currentJob();
        $videoId = (string) ($job?->external_video_id ?: ($this->result['id'] ?? ''));

        if ($videoId === '') {
            return;
        }

        if ($job && $job->status === 'completed' && $job->file_id) {
            $this->result = $this->resultFromJob($job->loadMissing('file'));
            return;
        }

        try {
            $refreshed = $studio->refreshWorkspaceVideoGeneration(auth()->user(), $videoId, [
                'provider' => (string) ($this->result['provider'] ?? ''),
                'model' => (string) ($this->result['model'] ?? ''),
                'prompt' => (string) ($this->result['prompt'] ?? $this->prompt),
            ]);

            $this->result = [
                ...$this->result,
                ...$refreshed,
            ];

            if (!empty($refreshed['file'])) {
                $file = $refreshed['file'];
                $this->result = [
                    ...$this->result,
                    'file_name' => (string) $file->name,
                    'file_size' => $file->humanSize(),
                    'preview_url' => route('portal.files.preview', $file),
                    'download_url' => route('portal.files.download', $file),
                    'files_url' => route('portal.files.index'),
                ];

                $this->dispatch('app-toast',
                    type: 'success',
                    title: __('Video ready'),
                    message: __('The rendered MP4 has been saved to your Files library.')
                );
            } elseif (($refreshed['status'] ?? '') === 'failed') {
                $message = (string) ($refreshed['error_message'] ?? __('The video generation job failed.'));
                $this->dispatch('app-toast',
                    type: 'error',
                    title: __('Video failed'),
                    message: $message
                );
            } else {
                $this->dispatch('app-toast',
                    type: 'success',
                    title: __('Status refreshed'),
                    message: __('Current video job status: :status.', ['status' => strtoupper((string) ($refreshed['status'] ?? 'queued'))])
                );
            }
            if ($job) {
                $job->update([
                    'file_id' => !empty($refreshed['file']) ? $refreshed['file']->id : $job->file_id,
                    'status' => (string) ($this->result['status'] ?? $job->status),
                    'progress' => (int) ($this->result['progress'] ?? $job->progress),
                    'duration' => (string) ($this->result['duration'] ?? $job->duration),
                    'format' => (string) ($this->result['format'] ?? $job->format),
                    'size' => (string) ($this->result['size'] ?? $job->size),
                    'prompt' => (string) ($this->result['prompt'] ?? $job->prompt),
                    'metadata' => $this->jobMetadata($this->result),
                    'last_polled_at' => now(),
                    'completed_at' => ($this->result['status'] ?? '') === 'completed' ? now() : $job->completed_at,
                    'failed_at' => ($this->result['status'] ?? '') === 'failed' ? now() : $job->failed_at,
                ]);
            }
        } catch (Throwable $exception) {
            $message = $exception->getMessage() ?: __('Could not refresh the video status.');

            // A provider-side missing job cannot become a downloadable MP4 by polling again.
            // Persist an honest terminal state so the workspace does not keep presenting it as active.
            if ($job && str_contains(mb_strtolower($message), 'not found')) {
                $job->update([
                    'status' => 'failed',
                    'metadata' => [
                        ...((array) $job->metadata),
                        'error_message' => $message,
                    ],
                    'last_polled_at' => now(),
                    'failed_at' => now(),
                ]);

                $this->result = $this->resultFromJob($job->fresh());
            }

            $this->dispatch('app-toast',
                type: 'error',
                title: __('Refresh failed'),
                message: $message
            );
        }
    }

    public function deleteCompletedJob(): void
    {
        abort_unless($this->aiStudioFeatureEnabled('ai_studio_video'), 404);

        $job = $this->currentJob();

        if (! $job || $job->status !== 'completed') {
            return;
        }

        $job->delete();

        $deletedJobId = $this->jobId;
        $this->result = [];
        $this->jobId = null;

        if ($deletedJobId) {
            $this->restoreLatestJob();
        }

        $this->dispatch('app-toast',
            type: 'success',
            title: __('Video job removed'),
            message: __('The completed video job was removed from AI Studio. The saved file remains in Files.')
        );
    }

    public function render(): View
    {
        abort_unless($this->aiStudioFeatureEnabled('ai_studio_video'), 404);

        $studio = app(AiContentStudioService::class);
        $allowedDurations = $this->allowedDurations($studio);
        $this->syncDurationSelection($studio);
        $selectedDuration = $allowedDurations !== [] ? (int) $this->duration : 0;

        return view('appaivideo::index', [
            'formatOptions' => [
                ['value' => 'vertical-short', 'label' => __('Vertical Short')],
                ['value' => 'landscape', 'label' => __('Landscape')],
                ['value' => 'square', 'label' => __('Square')],
            ],
            'durationOptions' => collect($allowedDurations)
                ->map(fn (int $seconds): array => [
                    'value' => (string) $seconds,
                    'label' => __(':seconds sec', ['seconds' => $seconds]),
                    'credits' => $studio->videoCreditCostForUser(auth()->user(), $seconds),
                ])
                ->all(),
            'maxVideoSeconds' => $studio->videoMaxSecondsForUser(auth()->user()),
            'selectedDurationCredits' => $selectedDuration > 0 ? $studio->videoCreditCostForUser(auth()->user(), $selectedDuration) : 0,
            'creditPreview' => $selectedDuration > 0
                ? $this->aiStudioCreditPreview('ai_studio_generate_video', $studio->videoCreditCostForUser(auth()->user(), $selectedDuration))
                : $this->aiStudioCreditPreview('ai_studio_generate_video'),
            'recentJobs' => $this->baseJobQuery()->latest('id')->limit(8)->get(),
            'promptHistory' => $this->promptHistoryQuery()->latest('id')->limit(8)->get(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('AI Video'),
        ]);
    }

    public function loadJob(int $jobId): void
    {
        $job = $this->baseJobQuery()->whereKey($jobId)->first();

        if (! $job) {
            return;
        }

        $this->jobId = (int) $job->id;
        $this->prompt = (string) $job->prompt;
        $this->duration = (string) ($job->duration ?: $this->duration);
        $this->format = (string) ($job->format ?: $this->format);
        $this->result = $this->resultFromJob($job->loadMissing('file'));
    }

    public function loadPromptHistory(int $historyId, AiContentStudioService $studio): void
    {
        $history = $this->promptHistoryQuery()->whereKey($historyId)->first();

        if (! $history) {
            return;
        }

        $this->prompt = (string) $history->prompt;
        $this->duration = (string) data_get($history->input_payload, 'duration', $this->duration);
        $this->format = (string) data_get($history->input_payload, 'format', $this->format);
        $this->syncDurationSelection($studio);
    }

    protected function restoreLatestJob(): void
    {
        $job = $this->baseJobQuery()->latest('id')->first();

        if (! $job) {
            return;
        }

        $this->jobId = (int) $job->id;
        $this->prompt = (string) $job->prompt;
        $this->duration = (string) ($job->duration ?: $this->duration);
        $this->format = (string) ($job->format ?: $this->format);
        $this->result = $this->resultFromJob($job);
    }

    protected function currentJob(): ?AIVideoJob
    {
        if ($this->jobId) {
            return $this->baseJobQuery()->whereKey($this->jobId)->first();
        }

        return $this->baseJobQuery()->latest('id')->first();
    }

    protected function baseJobQuery()
    {
        return AIVideoJob::query()
            ->ownedBy($this->workspaceOwnerUserId());
    }

    protected function promptHistoryQuery()
    {
        return AIPromptHistory::query()
            ->ownedBy($this->workspaceOwnerUserId())
            ->where('module', 'video');
    }

    protected function resultFromJob(AIVideoJob $job): array
    {
        $result = [
            'id' => (string) $job->external_video_id,
            'status' => (string) $job->status,
            'progress' => (int) $job->progress,
            'seconds' => (string) ($job->duration ?? ''),
            'duration' => (string) ($job->duration ?? ''),
            'format' => (string) ($job->format ?? ''),
            'size' => (string) ($job->size ?? ''),
            'prompt' => (string) $job->prompt,
            'provider' => (string) $job->provider,
            'model' => (string) $job->model,
            'error_message' => (string) data_get($job->metadata, 'error_message', ''),
        ];

        if ($job->file) {
            $result['file_name'] = (string) $job->file->name;
            $result['file_size'] = $job->file->humanSize();
            $result['preview_url'] = route('portal.files.preview', $job->file);
            $result['download_url'] = route('portal.files.download', $job->file);
            $result['files_url'] = route('portal.files.index');
        }

        return $result;
    }

    protected function jobMetadata(array $result): array
    {
        return array_filter([
            'size' => (string) ($result['size'] ?? ''),
            'progress' => (int) ($result['progress'] ?? 0),
            'error_message' => (string) ($result['error_message'] ?? ''),
        ], fn ($value) => ! ($value === '' || $value === null));
    }

    protected function allowedDurations(AiContentStudioService $studio): array
    {
        return $studio->availableVideoDurationsForUser(auth()->user());
    }

    protected function syncDurationSelection(AiContentStudioService $studio): void
    {
        $allowedDurations = $this->allowedDurations($studio);

        if ($allowedDurations === []) {
            $this->duration = '';

            return;
        }

        $this->duration = (string) $studio->normalizeVideoDurationForUser(auth()->user(), $this->duration !== '' ? $this->duration : 8);
    }
}
