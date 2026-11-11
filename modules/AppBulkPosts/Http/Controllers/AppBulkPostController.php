<?php

namespace Modules\AppBulkPosts\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\AppBulkPosts\Support\BulkPostAccess;
use Modules\AppBulkPosts\Support\BulkPostCsvService;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

class AppBulkPostController extends Controller
{
    public function __construct(protected BulkPostCsvService $csvService) {}

    public function template(): Response
    {
        abort_unless(BulkPostAccess::allowed(auth()->user(), TeamWorkspaceAccess::activeTeam(auth()->user())), 403);
        $stream = fopen('php://temp', 'r+');
        foreach ($this->csvService->templateRows() as $row) {
            fputcsv($stream, $row, ',', '"', '');
        }
        rewind($stream);
        $content = stream_get_contents($stream);
        fclose($stream);

        return response($content, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="bulk-posts-template.csv"']);
    }
}
