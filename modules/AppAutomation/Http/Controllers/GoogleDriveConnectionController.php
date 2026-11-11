<?php

namespace Modules\AppAutomation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\AppAutomation\Services\GoogleDrive\GoogleDriveApiService;
use Modules\AppAutomation\Services\GoogleDrive\GoogleDriveConnectionService;
use Modules\AppAutomation\Services\GoogleDrive\GoogleDriveException;
use Modules\AppAutomation\Services\GoogleDrive\GoogleDriveWatchService;
use Modules\AppAutomation\Support\AutomationAccess;
use Modules\AppTeams\Support\TeamWorkspaceAccess;
use Throwable;

class GoogleDriveConnectionController extends Controller
{
    public function __construct(
        protected AutomationAccess $access,
        protected GoogleDriveApiService $api,
        protected GoogleDriveConnectionService $connections,
        protected GoogleDriveWatchService $watcher,
    ) {}

    public function connect(Request $request): RedirectResponse
    {
        $this->authorizeConnectionManagement($request);

        try {
            $this->api->ensureReady();
        } catch (GoogleDriveException $exception) {
            return $this->returnWith('warning', $exception->getMessage());
        }

        $state = Str::random(48);
        $request->session()->put('automation_google_drive_oauth', [
            'state' => $state,
            'user_id' => (int) $request->user()->id,
            'owner_user_id' => $this->access->workspaceOwnerUserId($request->user()),
            'team_id' => $this->access->teamId($request->user()),
            'created_at' => now()->timestamp,
        ]);

        return redirect()->away($this->api->authorizationUrl($state));
    }

    public function callback(Request $request): RedirectResponse
    {
        $this->authorizeConnectionManagement($request);
        $context = (array) $request->session()->pull('automation_google_drive_oauth', []);

        if ($context === []
            || (int) ($context['user_id'] ?? 0) !== (int) $request->user()->id
            || (int) ($context['owner_user_id'] ?? 0) !== $this->access->workspaceOwnerUserId($request->user())
            || (int) ($context['team_id'] ?? 0) !== (int) ($this->access->teamId($request->user()) ?? 0)
            || now()->timestamp - (int) ($context['created_at'] ?? 0) > 900) {
            return $this->returnWith('danger', __('The Google Drive connection session expired or the active workspace changed. Please try again.'));
        }
        if (! hash_equals((string) ($context['state'] ?? ''), $request->string('state')->toString())) {
            return $this->returnWith('danger', __('The Google Drive authorization state was invalid. Please try again.'));
        }
        if ($request->filled('error')) {
            return $this->returnWith('warning', $request->string('error_description')->toString() ?: __('Google Drive authorization was cancelled.'));
        }

        $code = $request->string('code')->trim()->toString();
        if ($code === '') {
            return $this->returnWith('danger', __('Google did not return an authorization code.'));
        }

        try {
            $token = $this->api->exchangeCode($code);
            $identity = $this->api->identity((string) $token['access_token']);
            $connection = $this->connections->connect($request->user(), $token, $identity);
            $channel = $this->watcher->ensureChannel($connection);
        } catch (GoogleDriveException $exception) {
            return $this->returnWith('danger', $exception->getMessage());
        }

        if ($channel->status !== 'active') {
            return $this->returnWith('warning', __('Google Drive is connected, but background notifications are not ready: :reason', [
                'reason' => $channel->last_error ?: __('unknown watcher error'),
            ]));
        }

        return $this->returnWith('success', __('Google Drive is connected to this workspace and its background watcher is active.'));
    }

    public function verify(Request $request, int $connection): RedirectResponse
    {
        $this->authorizeConnectionManagement($request);

        try {
            $record = $this->connections->ownedConnection($request->user(), $connection);
            $record = $this->connections->verify($record);
            $channel = $this->watcher->ensureChannel($record);
        } catch (GoogleDriveException $exception) {
            return $this->returnWith('danger', $exception->getMessage());
        }

        return $channel->status === 'active'
            ? $this->returnWith('success', __('Google Drive access and the notification channel were verified.'))
            : $this->returnWith('warning', __('Google Drive access is valid, but its notification channel still needs attention.'));
    }

    public function disconnect(Request $request, int $connection): RedirectResponse
    {
        $this->authorizeConnectionManagement($request);
        $record = $this->connections->ownedConnection($request->user(), $connection);

        try {
            $this->watcher->stopChannel($record);
        } catch (Throwable) {
            // Revocation still proceeds when Google has already expired the remote channel.
        }
        $this->connections->disconnect($record);

        return $this->returnWith('success', __('Google Drive was disconnected from this workspace. Existing workflow drafts were preserved.'));
    }

    protected function authorizeConnectionManagement(Request $request): void
    {
        abort_unless($this->access->canManageCredentials($request->user()), 403);
        $team = TeamWorkspaceAccess::activeTeam($request->user());
        $planOwner = $team?->owner ?: $request->user();
        abort_if($planOwner?->plan && ! $planOwner->canUsePlanFeature('workflow_google_drive'), 403);
    }

    protected function returnWith(string $tone, string $message): RedirectResponse
    {
        return redirect(route('portal.automation.index').'#workflow-readiness')
            ->with('automation_connection_flash', ['tone' => $tone, 'message' => $message]);
    }
}
