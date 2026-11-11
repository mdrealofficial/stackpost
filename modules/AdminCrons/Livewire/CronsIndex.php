<?php

namespace Modules\AdminCrons\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AdminCrons\Support\SystemCronRegistry;
use Modules\AdminSettings\Support\OptionStore;

#[Title('System Crons')]
class CronsIndex extends Component
{
    protected OptionStore $options;

    protected SystemCronRegistry $registry;

    public string $secureKey = '';

    public ?string $statusMessage = null;

    public string $statusVariant = 'success';

    public function boot(OptionStore $options, SystemCronRegistry $registry): void
    {
        $this->options = $options;
        $this->registry = $registry;
    }

    public function mount(): void
    {
        $this->secureKey = trim((string) $this->options->get('system_cron_secure_key', ''));

        if ($this->secureKey === '') {
            $this->regenerateKey(false);
        }
    }

    public function regenerateKey(bool $announce = true): void
    {
        $this->secureKey = Str::random(16);
        $this->options->set('system_cron_secure_key', $this->secureKey);

        if ($announce) {
            $this->statusVariant = 'success';
            $this->statusMessage = __('The secure cron key has been regenerated.');
        }
    }

    public function render(): View
    {
        $artisan = config('app.demo_mode') ? 'artisan' : str_replace('\\', '/', base_path('artisan'));
        $tasks = collect($this->registry->tasks())->map(function (array $task) use ($artisan) {
            $url = route('admin-crons.execute', ['task' => $task['key'], 'key' => $this->secureKey]);
            $expression = (string) ($task['cron_expression'] ?? '* * * * *');

            return array_merge($task, [
                'url' => $url,
                'linux_cron' => sprintf('%s curl -fsS "%s" >/dev/null 2>&1', $expression, $url),
                'artisan_cron' => sprintf('%s php "%s" %s >/dev/null 2>&1', $expression, $artisan, $task['command']),
            ]);
        })->values();

        return view('admincrons::index', [
            'tasks' => $tasks,
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('System Crons'),
        ]);
    }
}
