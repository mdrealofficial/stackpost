<?php

namespace Modules\AppAutomation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Modules\AppAutomation\Jobs\PollGoogleDriveChanges;
use Modules\AppAutomation\Services\AutomationQueueReadiness;
use Modules\AppAutomation\Services\GoogleDrive\GoogleDriveWatchService;

class GoogleDriveNotificationController extends Controller
{
    public function __invoke(Request $request, GoogleDriveWatchService $watcher, AutomationQueueReadiness $queue): Response
    {
        $channelId = trim((string) $request->header('X-Goog-Channel-ID', ''));
        $channelToken = trim((string) $request->header('X-Goog-Channel-Token', ''));

        if ($channelId === '' || $channelToken === '') {
            return response('', 204);
        }

        $channel = $watcher->receiveNotification(
            $channelId,
            $channelToken,
            trim((string) $request->header('X-Goog-Resource-ID', '')) ?: null,
            trim((string) $request->header('X-Goog-Resource-State', '')) ?: null,
            $request->header('X-Goog-Message-Number') !== null ? (int) $request->header('X-Goog-Message-Number') : null,
        );

        if ($channel) {
            if ($queue->configurationIssues() !== []) {
                return response('', 503);
            }
            PollGoogleDriveChanges::dispatch($channel->id)
                ->onConnection($queue->connectionName())
                ->onQueue($queue->queueName());
        }

        return response('', 204);
    }
}
