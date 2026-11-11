<?php

namespace App\Livewire;

use Livewire\ComponentHook;

class DemoModeActionGuard extends ComponentHook
{
    /**
     * @var array<int, string>
     */
    protected array $blockedMethodPrefixes = [
        '_finishupload',
        '_removeupload',
        '_startupload',
        'activate',
        'approve',
        'archive',
        'attach',
        'cancel',
        'clear',
        'clone',
        'connect',
        'create',
        'deactivate',
        'delete',
        'destroy',
        'detach',
        'disconnect',
        'duplicate',
        'import',
        'install',
        'invite',
        'mark',
        'move',
        'process',
        'remove',
        'reorder',
        'reject',
        'rescan',
        'resend',
        'reset',
        'restore',
        'revoke',
        'run',
        'save',
        'send',
        'set',
        'store',
        'submit',
        'sync',
        'toggle',
        'unlink',
        'unpublish',
        'update',
        'upload',
    ];

    public function call($method, $params, $returnEarly, $metadata, $componentContext): void
    {
        if (! config('app.demo_mode')) {
            return;
        }

        if (! $this->isWorkspaceRequest() || ! $this->isBlockedMethod((string) $method)) {
            return;
        }

        $this->component->dispatch(
            'app-toast',
            type: 'warning',
            title: __('Demo mode'),
            message: __('Demo mode is enabled. Add, edit, and delete actions are disabled.'),
        );

        $returnEarly(null);
    }

    protected function isBlockedMethod(string $method): bool
    {
        $normalizedMethod = strtolower($method);

        foreach ($this->blockedMethodPrefixes as $prefix) {
            if (str_starts_with($normalizedMethod, $prefix)) {
                return true;
            }
        }

        return false;
    }

    protected function isWorkspaceRequest(): bool
    {
        $path = trim((string) app('livewire')->originalPath(), '/');

        return $path === 'dashboard'
            || str_starts_with($path, 'dashboard/')
            || str_starts_with($path, 'portal/')
            || str_starts_with($path, 'admin/')
            || str_starts_with($path, 'admin/settings/');
    }
}
