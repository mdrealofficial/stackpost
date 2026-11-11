<?php

namespace Modules\AdminBlogs\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AdminBlogs\Models\BlogRssImport;

#[Title('RSS Import Logs')]
class RssLogsIndex extends Component
{
    public ?string $statusMessage = null;

    public function clearImportLogs(): void
    {
        $deleted = BlogRssImport::query()->count();

        if ($deleted === 0) {
            $this->statusMessage = __('Import logs are already empty.');

            return;
        }

        BlogRssImport::query()->delete();

        $this->statusMessage = trans_choice(
            '{1} :count import log deleted successfully.|[2,*] :count import logs deleted successfully.',
            $deleted,
            ['count' => $deleted]
        );
    }

    public function render(): View
    {
        $recentImports = BlogRssImport::query()
            ->with([
                'source:id,name,feed_url',
                'blog:id,title,slug,status,published_at',
            ])
            ->orderByDesc('created')
            ->paginate(20);

        return view('adminblogs::livewire.rss-logs', [
            'recentImports' => $recentImports,
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('RSS Import Logs'),
        ]);
    }
}
