<?php

namespace Modules\AppAIImage\Livewire;

use Illuminate\Contracts\View\View;
use Throwable;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AppAIStudio\Support\AIStudioAccess;
use Modules\AppAIStudio\Support\AiContentStudioService;
use Modules\AppAIImage\Models\AIImageJob;
use Modules\AppAIStudio\Models\AIPromptHistory;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

#[Title('AI Image')]
class AIImageIndex extends Component
{
    use AIStudioAccess;

    public string $prompt = '';
    public string $style = 'product';
    public string $ratio = '1:1';
    public array $result = [];
    public ?int $jobId = null;

    public function mount(): void
    {
        abort_unless($this->aiStudioFeatureEnabled('ai_studio_image'), 404);
        $this->style = (string) $this->aiStudioSetting('image_style', $this->style);
        $this->ratio = (string) $this->aiStudioSetting('image_ratio', $this->ratio);
        $this->restoreLatestJob();
    }

    public function generate(AiContentStudioService $studio): void
    {
        abort_unless($this->aiStudioFeatureEnabled('ai_studio_image'), 404);

        $validated = $this->validate([
            'prompt' => ['required', 'string', 'max:4000'],
        ]);
        $userPrompt = trim($validated['prompt']);

        $this->resetErrorBag();
        $this->result = [];

        try {
            $generated = $studio->generateWorkspaceImage(
                user: auth()->user(),
                prompt: trim($validated['prompt']),
                style: $this->style,
                ratio: $this->ratio,
            );

            $file = $generated['file'];

            $this->result = [
                'status' => 'generated',
                'prompt' => $userPrompt,
                'generation_prompt' => (string) ($generated['prompt'] ?? $userPrompt),
                'style' => $this->style,
                'ratio' => $this->ratio,
                'provider' => (string) ($generated['provider'] ?? ''),
                'model' => (string) ($generated['model'] ?? ''),
                'file_name' => (string) $file->name,
                'file_size' => $file->humanSize(),
                'dimensions' => trim(implode(' x ', array_filter([(string) $file->width, (string) $file->height]))),
                'preview_url' => route('portal.files.preview', $file),
                'download_url' => route('portal.files.download', $file),
                'files_url' => route('portal.files.index'),
            ];

            $job = AIImageJob::query()->create([
                'owner_user_id' => $this->workspaceOwnerUserId(),
                'requested_by_user_id' => (int) auth()->id(),
                'team_id' => TeamWorkspaceAccess::activeTeam(auth()->user())?->id,
                'file_id' => $file->id,
                'provider' => (string) ($generated['provider'] ?? ''),
                'model' => (string) ($generated['model'] ?? ''),
                'status' => 'generated',
                'style' => $this->style,
                'ratio' => $this->ratio,
                'prompt' => $userPrompt,
                'metadata' => [
                    'file_name' => (string) $file->name,
                    'file_size' => $file->humanSize(),
                    'dimensions' => trim(implode(' x ', array_filter([(string) $file->width, (string) $file->height]))),
                    'generation_prompt' => (string) ($generated['prompt'] ?? $userPrompt),
                ],
            ]);

            $this->jobId = (int) $job->id;

            $studio->recordPromptHistory(
                auth()->user(),
                'image',
                $userPrompt,
                [
                    'style' => $this->style,
                    'ratio' => $this->ratio,
                    'generation_prompt' => (string) ($generated['prompt'] ?? $userPrompt),
                ],
                [
                    'status' => 'generated',
                    'provider' => (string) ($generated['provider'] ?? ''),
                    'model' => (string) ($generated['model'] ?? ''),
                    'file_name' => (string) $file->name,
                ],
                [
                    'title' => __('Image - :style - :ratio', ['style' => ucfirst($this->style), 'ratio' => $this->ratio]),
                ],
            );

            $this->dispatch('app-toast',
                type: 'success',
                title: __('Image generated'),
                message: __('The new AI image has been saved to your media library.')
            );
        } catch (Throwable $exception) {
            $message = $exception->getMessage() ?: __('The image could not be generated.');

            $this->addError('prompt', $message);
            $this->dispatch('app-toast',
                type: 'error',
                title: __('Generation failed'),
                message: $message
            );
        }
    }

    public function loadJob(int $jobId): void
    {
        $job = $this->baseJobQuery()->whereKey($jobId)->first();

        if (! $job) {
            return;
        }

        $this->jobId = (int) $job->id;
        $this->prompt = (string) $job->prompt;
        $this->style = (string) ($job->style ?: $this->style);
        $this->ratio = (string) ($job->ratio ?: $this->ratio);
        $this->result = $this->resultFromJob($job->loadMissing('file'));
    }

    public function loadPromptHistory(int $historyId): void
    {
        $history = $this->promptHistoryQuery()->whereKey($historyId)->first();

        if (! $history) {
            return;
        }

        $this->prompt = (string) $history->prompt;
        $this->style = (string) data_get($history->input_payload, 'style', $this->style);
        $this->ratio = (string) data_get($history->input_payload, 'ratio', $this->ratio);
    }

    public function render(): View
    {
        abort_unless($this->aiStudioFeatureEnabled('ai_studio_image'), 404);

        return view('appaiimage::index', [
            'styleOptions' => [
                ['value' => 'product', 'label' => __('Product')],
                ['value' => 'lifestyle', 'label' => __('Lifestyle')],
                ['value' => 'editorial', 'label' => __('Editorial')],
                ['value' => 'minimal', 'label' => __('Minimal')],
                ['value' => 'cinematic', 'label' => __('Cinematic')],
            ],
            'ratioOptions' => [
                ['value' => '1:1', 'label' => '1:1'],
                ['value' => '4:5', 'label' => '4:5'],
                ['value' => '16:9', 'label' => '16:9'],
                ['value' => '9:16', 'label' => '9:16'],
            ],
            'creditPreview' => $this->aiStudioCreditPreview('ai_studio_generate_image'),
            'recentJobs' => $this->baseJobQuery()->latest('id')->limit(8)->get(),
            'promptHistory' => $this->promptHistoryQuery()->latest('id')->limit(8)->get(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('AI Image'),
        ]);
    }

    protected function restoreLatestJob(): void
    {
        $job = $this->baseJobQuery()->latest('id')->first();

        if (! $job) {
            return;
        }

        $this->loadJob((int) $job->id);
    }

    protected function baseJobQuery()
    {
        return AIImageJob::query()
            ->ownedBy($this->workspaceOwnerUserId());
    }

    protected function promptHistoryQuery()
    {
        return AIPromptHistory::query()
            ->ownedBy($this->workspaceOwnerUserId())
            ->where('module', 'image');
    }

    protected function resultFromJob(AIImageJob $job): array
    {
        return [
            'status' => (string) ($job->status ?? 'generated'),
            'prompt' => (string) $job->prompt,
            'style' => (string) ($job->style ?? ''),
            'ratio' => (string) ($job->ratio ?? ''),
            'provider' => (string) ($job->provider ?? ''),
            'model' => (string) ($job->model ?? ''),
            'file_name' => (string) data_get($job->metadata, 'file_name', $job->file?->name ?? ''),
            'file_size' => (string) data_get($job->metadata, 'file_size', $job->file?->humanSize() ?? ''),
            'dimensions' => (string) data_get($job->metadata, 'dimensions', ''),
            'preview_url' => $job->file ? route('portal.files.preview', $job->file) : null,
            'download_url' => $job->file ? route('portal.files.download', $job->file) : null,
            'files_url' => $job->file ? route('portal.files.index') : null,
        ];
    }
}
