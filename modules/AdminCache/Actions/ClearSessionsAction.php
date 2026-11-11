<?php

namespace Modules\AdminCache\Actions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Schema;
use Modules\AdminCache\Actions\Contracts\CacheAction;
use RuntimeException;

class ClearSessionsAction implements CacheAction
{
    public function key(): string
    {
        return 'session';
    }

    public function title(): string
    {
        return 'Clear all sessions';
    }

    public function description(): string
    {
        return 'This will log out all users from the system. Use with caution!';
    }

    public function icon(): string
    {
        return 'fa-user-slash';
    }

    public function buttonLabel(): string
    {
        return 'Clear sessions';
    }

    public function buttonVariant(): string
    {
        return 'danger';
    }

    public function confirmMessage(): string
    {
        return 'This action cannot be undone. It will log out every active user session from the platform immediately.';
    }

    public function confirmTitle(): string
    {
        return 'Are you absolutely sure?';
    }

    public function handle(): string
    {
        $driver = (string) config('session.driver', 'file');

        match ($driver) {
            'file' => $this->clearFileSessions(),
            'database' => $this->clearDatabaseSessions(),
            'redis' => $this->clearRedisSessions(),
            default => throw new RuntimeException(__('Session clear is not supported for the current session driver: :driver', ['driver' => $driver])),
        };

        return __('All sessions cleared successfully. All users have been logged out.');
    }

    protected function clearFileSessions(): void
    {
        $path = storage_path('framework/sessions');

        if (! File::isDirectory($path)) {
            throw new RuntimeException(__('The session storage directory could not be found.'));
        }

        File::cleanDirectory($path);
    }

    protected function clearDatabaseSessions(): void
    {
        $table = (string) config('session.table', 'sessions');

        if (! Schema::hasTable($table)) {
            throw new RuntimeException(__('The session table [:table] does not exist.', ['table' => $table]));
        }

        DB::table($table)->delete();
    }

    protected function clearRedisSessions(): void
    {
        Redis::connection(config('session.connection'))->flushdb();
    }
}
