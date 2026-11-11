<?php

namespace Modules\AppAutomation\Livewire;

use App\Support\Http\OutboundUrlPolicy;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AppAutomation\Models\AutomationApiKey;
use Modules\AppAutomation\Models\AutomationLog;
use Modules\AppAutomation\Models\AutomationWebhook;
use Modules\AppAutomation\Services\AutomationApiKeyService;
use Modules\AppAutomation\Support\AutomationAccess;
use Modules\AppAutomation\Support\AutomationWorkspaceScope;

#[Title('Automation Developer')]
class AutomationIndex extends Component
{
    public string $keyName = '';

    public string $webhookName = '';

    public string $webhookUrl = '';

    public string $webhookEvents = 'post.published,post.failed';

    public string $revealedApiKey = '';

    public bool $allowImmediatePublishing = false;

    protected AutomationAccess $access;

    protected AutomationApiKeyService $keys;

    protected AutomationWorkspaceScope $workspaces;

    protected OutboundUrlPolicy $outboundUrls;

    public function boot(
        AutomationAccess $access,
        AutomationApiKeyService $keys,
        AutomationWorkspaceScope $workspaces,
        OutboundUrlPolicy $outboundUrls,
    ): void
    {
        $this->access = $access;
        $this->keys = $keys;
        $this->workspaces = $workspaces;
        $this->outboundUrls = $outboundUrls;
    }

    public function mount(): void
    {
        abort_unless($this->access->canManageCredentials(auth()->user()), 404);
    }

    public function createApiKey(): void
    {
        abort_unless($this->access->canManageCredentials(auth()->user()), 404);

        $validated = $this->validate([
            'keyName' => ['required', 'string', 'max:120'],
            'allowImmediatePublishing' => ['boolean'],
        ]);

        $permissions = AutomationApiKeyService::DEFAULT_PERMISSIONS;

        if ((bool) $validated['allowImmediatePublishing']) {
            $permissions[] = 'posts:publish';
        }

        [, $plain] = $this->keys->create(auth()->user(), (string) $validated['keyName'], $permissions);

        $this->reset('keyName', 'allowImmediatePublishing');
        $this->revealedApiKey = $plain;
        $this->notify(__('Saved'), __('Automation API key created successfully.'));
    }

    public function dismissRevealedApiKey(): void
    {
        $this->revealedApiKey = '';
    }

    public function revokeApiKey(int $apiKeyId): void
    {
        $apiKey = $this->findOwnedApiKey($apiKeyId);

        $apiKey->forceFill(['revoked_at' => now()])->save();

        $this->notify(__('Saved'), __('Automation API key revoked.'));
    }

    public function deleteApiKey(int $apiKeyId): void
    {
        $apiKey = $this->findOwnedApiKey($apiKeyId);

        $apiKey->delete();

        $this->notify(__('Saved'), __('Automation API key deleted.'));
    }

    public function createWebhook(): void
    {
        abort_unless($this->access->canManageCredentials(auth()->user()), 404);

        $validated = $this->validate([
            'webhookName' => ['required', 'string', 'max:120'],
            'webhookUrl' => ['required', 'url', 'max:1000'],
            'webhookEvents' => ['nullable', 'string', 'max:500'],
        ]);

        $events = collect(preg_split('/[\s,]+/', (string) ($validated['webhookEvents'] ?? '')) ?: [])
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values()
            ->all();

        try {
            $webhookTarget = $this->outboundUrls->inspect((string) $validated['webhookUrl']);
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages(['webhookUrl' => $exception->getMessage()]);
        }

        AutomationWebhook::query()->create([
            'user_id' => $this->access->workspaceOwnerUserId(auth()->user()),
            'team_id' => $this->access->teamId(auth()->user()),
            'name' => trim((string) $validated['webhookName']),
            'url' => $webhookTarget->url,
            'signing_secret' => Str::random(40),
            'events' => $events,
            'is_active' => true,
            'last_sent_at' => null,
        ]);

        $this->reset('webhookName', 'webhookUrl');
        $this->webhookEvents = 'post.published,post.failed';
        $this->notify(__('Saved'), __('Automation webhook created.'));
    }

    public function toggleWebhook(int $webhookId): void
    {
        $webhook = $this->findOwnedWebhook($webhookId);

        $webhook->forceFill(['is_active' => ! $webhook->is_active])->save();

        $message = $webhook->is_active
            ? __('Automation webhook enabled.')
            : __('Automation webhook paused.');
        $this->notify(__('Saved'), $message);
    }

    public function deleteWebhook(int $webhookId): void
    {
        $webhook = $this->findOwnedWebhook($webhookId);

        $webhook->delete();

        $this->notify(__('Saved'), __('Automation webhook deleted.'));
    }

    public function render(): View
    {
        abort_unless($this->access->canManageCredentials(auth()->user()), 404);

        $workspaceOwnerUserId = $this->access->workspaceOwnerUserId(auth()->user());
        $teamId = $this->access->teamId(auth()->user());
        $apiKeys = $this->workspaces->apply(AutomationApiKey::query(), $workspaceOwnerUserId, $teamId)
            ->latest('id')
            ->get();
        $webhooks = $this->workspaces->apply(AutomationWebhook::query(), $workspaceOwnerUserId, $teamId)
            ->latest('id')
            ->get();
        $logs = $this->workspaces->apply(AutomationLog::query(), $workspaceOwnerUserId, $teamId)
            ->latest('id')
            ->limit(25)
            ->get();

        return view($this->viewName(), [
            'apiKeys' => $apiKeys,
            'webhooks' => $webhooks,
            'logs' => $logs,
            'apiBase' => url(config('modules.appautomation.api_prefix', 'api/automation/v1')),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => $this->pageTitle(),
        ]);
    }

    protected function viewName(): string
    {
        return 'appautomation::livewire.index';
    }

    protected function pageTitle(): string
    {
        return __('Developer & API');
    }

    protected function findOwnedApiKey(int $apiKeyId): AutomationApiKey
    {
        abort_unless($this->access->canManageCredentials(auth()->user()), 404);

        $apiKey = $this->workspaces->apply(
            AutomationApiKey::query(),
            $this->access->workspaceOwnerUserId(auth()->user()),
            $this->access->teamId(auth()->user()),
        )->findOrFail($apiKeyId);

        return $apiKey;
    }

    protected function findOwnedWebhook(int $webhookId): AutomationWebhook
    {
        abort_unless($this->access->canManageCredentials(auth()->user()), 404);

        $webhook = $this->workspaces->apply(
            AutomationWebhook::query(),
            $this->access->workspaceOwnerUserId(auth()->user()),
            $this->access->teamId(auth()->user()),
        )->findOrFail($webhookId);

        return $webhook;
    }

    protected function notify(string $title, string $message, string $type = 'success'): void
    {
        $this->dispatch('app-toast',
            type: $type,
            title: $title,
            message: $message,
        );
    }
}
