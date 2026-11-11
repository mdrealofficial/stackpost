<?php

namespace App\Social\Automation;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class DispatchCommand extends Command
{
    protected $signature = 'social:automations {--limit=50}';
    protected $description = 'Process durable DM Automation and Auto Comment runs outside webhook requests';

    public function handle(Registry $registry, Engine $engine): int
    {
        Cache::put('social-automation:heartbeat', now()->toIso8601String(), 300);
        foreach ($registry->all() as $key => $module) {
            $module['run']::query()->where('status', 'processing')->where('started_at', '<', now()->subMinutes(10))
                ->update(['status'=>'unknown','reason'=>'Processing was interrupted. Check delivery before any further response.']);
            $module['run']::query()->where('status', 'pending')->orderBy('id')
                ->limit(max(1, min(200, (int) $this->option('limit'))))->pluck('id')
                ->each(fn ($id) => $engine->process($key, $id));
        }
        $this->info('Automation processing completed.');
        return self::SUCCESS;
    }
}
