<?php

namespace Modules\AdminBlogs\Console\Commands;

use Illuminate\Console\Command;
use Modules\AdminBlogs\Models\BlogRssSource;
use Modules\AdminBlogs\Support\RssImportService;

class ImportRssBlogsCommand extends Command
{
    protected $signature = 'blogs:rss-import {--source=} {--force}';

    protected $description = 'Import blog posts from configured RSS sources';

    public function handle(RssImportService $service): int
    {
        $query = BlogRssSource::query()->where('status', true);

        if ($source = $this->option('source')) {
            $query->where(function ($builder) use ($source): void {
                $builder->where('id', $source)->orWhere('id_secure', $source);
            });
        }

        $sources = $query->get();

        if ($sources->isEmpty()) {
            $this->warn('No active RSS sources found.');
            return self::SUCCESS;
        }

        foreach ($sources as $rssSource) {
            $result = $service->importSource($rssSource, (bool) $this->option('force'));
            $this->line(sprintf(
                '%s: created %d, skipped %d. %s',
                $rssSource->name,
                $result['created'] ?? 0,
                $result['skipped'] ?? 0,
                $result['message'] ?? ''
            ));
        }

        return self::SUCCESS;
    }
}
