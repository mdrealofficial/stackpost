<?php

namespace Modules\AdminSystemInformation\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('System information')]
class SystemInformationIndex extends Component
{
    /**
     * @return array<int, array{label:string,value:string}>
     */
    protected function overviewItems(): array
    {
        return [
            ['label' => 'Application name', 'value' => (string) config('app.name', 'Laravel')],
            ['label' => 'Environment', 'value' => (string) config('app.env', 'production')],
            ['label' => 'Debug mode', 'value' => config('app.debug') ? 'Enabled' : 'Disabled'],
            ['label' => 'Application URL', 'value' => (string) config('app.url', 'http://localhost')],
            ['label' => 'Application timezone', 'value' => (string) config('app.timezone', 'UTC')],
            ['label' => 'PHP version', 'value' => PHP_VERSION],
            ['label' => 'Laravel version', 'value' => app()->version()],
        ];
    }

    /**
     * @return array<int, array{label:string,value:string}>
     */
    protected function infrastructureItems(): array
    {
        return [
            ['label' => 'Database connection', 'value' => (string) config('database.default', 'unknown')],
            ['label' => 'Database driver', 'value' => (string) DB::connection()->getDriverName()],
            ['label' => 'Cache store', 'value' => (string) config('cache.default', 'unknown')],
            ['label' => 'Session driver', 'value' => (string) config('session.driver', 'unknown')],
            ['label' => 'Queue connection', 'value' => (string) config('queue.default', 'unknown')],
            ['label' => 'Mail mailer', 'value' => (string) config('mail.default', 'unknown')],
            ['label' => 'Broadcast connection', 'value' => (string) config('broadcasting.default', 'null')],
        ];
    }

    /**
     * @return array<int, array{label:string,detail:string,status:string,passed:bool}>
     */
    protected function requirementItems(): array
    {
        $phpVersion = PHP_VERSION;

        $rows = [
            [
                'label' => 'PHP runtime',
                'detail' => sprintf('Version %s (required: >= 8.2)', $phpVersion),
                'status' => version_compare($phpVersion, '8.2.0', '>=') ? 'Passed' : 'Failed',
                'passed' => version_compare($phpVersion, '8.2.0', '>='),
            ],
        ];

        $extensions = [
            'PDO' => extension_loaded('pdo'),
            'Mbstring' => extension_loaded('mbstring'),
            'Fileinfo' => extension_loaded('fileinfo'),
            'OpenSSL' => extension_loaded('openssl'),
            'Tokenizer' => extension_loaded('tokenizer'),
            'XML' => extension_loaded('xml'),
            'Ctype' => extension_loaded('ctype'),
            'JSON' => extension_loaded('json'),
            'BCMath' => extension_loaded('bcmath'),
            'GD' => extension_loaded('gd'),
            'Intl' => extension_loaded('intl'),
            'MySQL (pdo_mysql)' => extension_loaded('pdo_mysql'),
        ];

        foreach ($extensions as $label => $enabled) {
            $rows[] = [
                'label' => $label,
                'detail' => $label === 'MySQL (pdo_mysql)'
                    ? ($enabled ? 'Available' : 'Not available')
                    : ($enabled ? 'Enabled' : 'Missing'),
                'status' => $enabled ? 'Passed' : 'Failed',
                'passed' => $enabled,
            ];
        }

        $storageWritable = is_writable(storage_path());
        $cacheWritable = is_writable(base_path('bootstrap/cache'));

        $rows[] = [
            'label' => 'storage/ writable',
            'detail' => $storageWritable ? 'Writable' : 'Not writable',
            'status' => $storageWritable ? 'Passed' : 'Failed',
            'passed' => $storageWritable,
        ];

        $rows[] = [
            'label' => 'bootstrap/cache writable',
            'detail' => $cacheWritable ? 'Writable' : 'Not writable',
            'status' => $cacheWritable ? 'Passed' : 'Failed',
            'passed' => $cacheWritable,
        ];

        return $rows;
    }

    protected function requirementSummary(): array
    {
        $items = collect($this->requirementItems());
        $passedCount = $items->where('passed', true)->count();
        $totalCount = $items->count();

        return [
            'total' => $totalCount,
            'passed' => $passedCount,
            'failed' => $totalCount - $passedCount,
            'all_passed' => $passedCount === $totalCount,
        ];
    }

    public function render(): View
    {
        return view('adminsysteminformation::index', [
            'overviewItems' => $this->overviewItems(),
            'infrastructureItems' => $this->infrastructureItems(),
            'requirementItems' => $this->requirementItems(),
            'requirementSummary' => $this->requirementSummary(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('System information'),
        ]);
    }
}
