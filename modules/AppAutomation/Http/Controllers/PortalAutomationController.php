<?php

namespace Modules\AppAutomation\Http\Controllers;

use App\Support\Http\OutboundUrlPolicy;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\AppAutomation\Models\AutomationApiKey;
use Modules\AppAutomation\Models\AutomationLog;
use Modules\AppAutomation\Models\AutomationWebhook;
use Modules\AppAutomation\Services\AutomationApiKeyService;
use Modules\AppAutomation\Support\AutomationAccess;
use Modules\AppAutomation\Support\AutomationWorkspaceScope;

class PortalAutomationController extends Controller
{
    public function __construct(
        protected AutomationAccess $access,
        protected AutomationApiKeyService $keys,
        protected AutomationWorkspaceScope $workspaces,
        protected OutboundUrlPolicy $outboundUrls,
    ) {}

    public function index(Request $request): View
    {
        abort_unless($this->access->canManageCredentials($request->user()), 404);

        $workspaceOwnerUserId = $this->access->workspaceOwnerUserId($request->user());
        $teamId = $this->access->teamId($request->user());
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

        return view('appautomation::index', [
            'apiKeys' => $apiKeys,
            'webhooks' => $webhooks,
            'logs' => $logs,
            'apiBase' => url(config('modules.appautomation.api_prefix', 'api/automation/v1')),
            'revealedApiKey' => (string) session('automation_new_api_key', ''),
        ]);
    }

    public function createApiKey(Request $request): RedirectResponse
    {
        abort_unless($this->access->canManageCredentials($request->user()), 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
        ]);

        [, $plain] = $this->keys->create($request->user(), (string) $validated['name']);

        return redirect()
            ->route('portal.automation.index')
            ->with('status', __('Automation API key created successfully.'))
            ->with('automation_new_api_key', $plain);
    }

    public function revokeApiKey(Request $request, AutomationApiKey $apiKey): RedirectResponse
    {
        abort_unless($this->ownsApiKey($request, $apiKey), 404);

        $apiKey->forceFill(['revoked_at' => now()])->save();

        return redirect()
            ->route('portal.automation.index')
            ->with('status', __('Automation API key revoked.'));
    }

    public function deleteApiKey(Request $request, AutomationApiKey $apiKey): RedirectResponse
    {
        abort_unless($this->ownsApiKey($request, $apiKey), 404);

        $apiKey->delete();

        return redirect()
            ->route('portal.automation.index')
            ->with('status', __('Automation API key deleted.'));
    }

    public function createWebhook(Request $request): RedirectResponse
    {
        abort_unless($this->access->canManageCredentials($request->user()), 404);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'url', 'max:1000'],
            'events' => ['nullable', 'string', 'max:500'],
        ]);

        $workspaceOwnerUserId = $this->access->workspaceOwnerUserId($request->user());
        $teamId = $this->access->teamId($request->user());
        $events = collect(preg_split('/[\s,]+/', (string) ($validated['events'] ?? '')) ?: [])
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values()
            ->all();

        try {
            $webhookTarget = $this->outboundUrls->inspect((string) $validated['url']);
        } catch (\RuntimeException $exception) {
            throw ValidationException::withMessages(['url' => $exception->getMessage()]);
        }

        AutomationWebhook::query()->create([
            'user_id' => $workspaceOwnerUserId,
            'team_id' => $teamId,
            'name' => trim((string) $validated['name']),
            'url' => $webhookTarget->url,
            'signing_secret' => Str::random(40),
            'events' => $events,
            'is_active' => true,
            'last_sent_at' => null,
        ]);

        return redirect()
            ->route('portal.automation.index')
            ->with('status', __('Automation webhook created.'));
    }

    public function toggleWebhook(Request $request, AutomationWebhook $webhook): RedirectResponse
    {
        abort_unless($this->ownsWebhook($request, $webhook), 404);

        $webhook->forceFill(['is_active' => ! $webhook->is_active])->save();

        return redirect()
            ->route('portal.automation.index')
            ->with('status', $webhook->is_active ? __('Automation webhook enabled.') : __('Automation webhook paused.'));
    }

    public function deleteWebhook(Request $request, AutomationWebhook $webhook): RedirectResponse
    {
        abort_unless($this->ownsWebhook($request, $webhook), 404);

        $webhook->delete();

        return redirect()
            ->route('portal.automation.index')
            ->with('status', __('Automation webhook deleted.'));
    }

    protected function ownsApiKey(Request $request, AutomationApiKey $apiKey): bool
    {
        return $this->access->canManageCredentials($request->user())
            && $this->workspaces->matches(
                $apiKey,
                $this->access->workspaceOwnerUserId($request->user()),
                $this->access->teamId($request->user()),
            );
    }

    protected function ownsWebhook(Request $request, AutomationWebhook $webhook): bool
    {
        return $this->access->canManageCredentials($request->user())
            && $this->workspaces->matches(
                $webhook,
                $this->access->workspaceOwnerUserId($request->user()),
                $this->access->teamId($request->user()),
            );
    }
}
