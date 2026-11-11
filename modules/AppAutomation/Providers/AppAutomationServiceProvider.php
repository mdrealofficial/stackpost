<?php

namespace Modules\AppAutomation\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Modules\AdminCrons\Support\SystemCronRegistry;
use Modules\AppAutomation\Console\Commands\DispatchDueAutomationRunsCommand;
use Modules\AppAutomation\Console\Commands\MaintainGoogleDriveWatchesCommand;
use Modules\AppAutomation\Console\Commands\ProbeAutomationQueueCommand;
use Modules\AppAutomation\Console\Commands\ReconcileAutomationApprovalsCommand;
use Modules\AppAutomation\Events\AutomationDriveFileChanged;
use Modules\AppAutomation\Models\AutomationWorkflow;
use Modules\AppAutomation\Observers\AutomationTeamPostReviewObserver;
use Modules\AppAutomation\Runtime\Handlers\AiWriteNodeHandler;
use Modules\AppAutomation\Runtime\Handlers\ApprovalNodeHandler;
use Modules\AppAutomation\Runtime\Handlers\CreateDraftNodeHandler;
use Modules\AppAutomation\Runtime\Handlers\DelayNodeHandler;
use Modules\AppAutomation\Runtime\Handlers\EndNodeHandler;
use Modules\AppAutomation\Runtime\Handlers\FilterNodeHandler;
use Modules\AppAutomation\Runtime\Handlers\ImportDriveFileNodeHandler;
use Modules\AppAutomation\Runtime\Handlers\PassThroughTriggerHandler;
use Modules\AppAutomation\Runtime\Handlers\SchedulePostNodeHandler;
use Modules\AppAutomation\Services\AutomationApiKeyService;
use Modules\AppAutomation\Services\AutomationApiIdempotencyService;
use Modules\AppAutomation\Services\AutomationQueueReadiness;
use Modules\AppAutomation\Services\AutomationWorkflowApprovalService;
use Modules\AppAutomation\Services\AutomationWorkflowApprovalReconciler;
use Modules\AppAutomation\Services\AutomationWorkflowCreditRunner;
use Modules\AppAutomation\Services\AutomationWorkflowDispatcher;
use Modules\AppAutomation\Services\AutomationWorkflowHandlerRegistry;
use Modules\AppAutomation\Services\AutomationWorkflowRunner;
use Modules\AppAutomation\Services\AutomationWebhookDispatcher;
use Modules\AppAutomation\Services\GoogleDrive\GoogleDriveApiService;
use Modules\AppAutomation\Services\GoogleDrive\GoogleDriveConnectionService;
use Modules\AppAutomation\Services\GoogleDrive\GoogleDriveWatchService;
use Modules\AppAutomation\Support\AutomationAccess;
use Modules\AppTeams\Support\TeamPermissionRegistry;
use Modules\AppTeams\Support\TeamWorkspaceAccess;
use Modules\AppTeams\Models\TeamPostReview;

class AppAutomationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'modules.appautomation');
        $this->app->singleton(AutomationAccess::class);
        $this->app->singleton(AutomationApiKeyService::class);
        $this->app->singleton(AutomationApiIdempotencyService::class);
        $this->app->singleton(AutomationQueueReadiness::class);
        $this->app->singleton(AutomationWorkflowCreditRunner::class);
        $this->app->singleton(AutomationWorkflowDispatcher::class);
        $this->app->singleton(AutomationWorkflowRunner::class);
        $this->app->singleton(AutomationWorkflowApprovalService::class);
        $this->app->singleton(AutomationWorkflowApprovalReconciler::class);
        $this->app->singleton(AutomationWorkflowHandlerRegistry::class, function (): AutomationWorkflowHandlerRegistry {
            $registry = new AutomationWorkflowHandlerRegistry();

            foreach (['trigger_google_drive', 'trigger_social_engagement', 'trigger_webhook'] as $trigger) {
                $registry->register($trigger, PassThroughTriggerHandler::class);
            }

            return $registry
                ->register('condition', FilterNodeHandler::class)
                ->register('delay', DelayNodeHandler::class)
                ->register('import_drive_file', ImportDriveFileNodeHandler::class)
                ->register('ai_write', AiWriteNodeHandler::class)
                ->register('create_draft', CreateDraftNodeHandler::class)
                ->register('approval', ApprovalNodeHandler::class)
                ->register('schedule_post', SchedulePostNodeHandler::class)
                ->register('end', EndNodeHandler::class);
        });
        $this->app->singleton(AutomationWebhookDispatcher::class);
        $this->app->singleton(GoogleDriveApiService::class);
        $this->app->singleton(GoogleDriveConnectionService::class);
        $this->app->singleton(GoogleDriveWatchService::class);
    }

    public function boot(): void
    {
        RateLimiter::for('automation-api-posts', function (Request $request): Limit {
            $credential = trim((string) ($request->bearerToken()
                ?: $request->header('X-Automation-Key', '')
                ?: $request->header('X-Stackposts-Key', '')));
            $identity = $credential !== ''
                ? hash('sha256', $credential)
                : 'ip:'.(string) $request->ip();

            return Limit::perMinute(max(1, (int) config('modules.appautomation.api.post_rate_limit_per_minute', 60)))
                ->by('automation-posts:'.$identity);
        });

        $this->loadRoutesFrom(__DIR__.'/../Routes/web.php');
        $this->loadRoutesFrom(__DIR__.'/../Routes/api.php');
        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'appautomation');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        TeamPostReview::observe(AutomationTeamPostReviewObserver::class);

        Event::listen(AutomationDriveFileChanged::class, function (AutomationDriveFileChanged $event): void {
            $payload = $event->toArray();
            $workflow = AutomationWorkflow::query()
                ->whereKey((int) ($payload['workflow_id'] ?? 0))
                ->where('owner_user_id', (int) ($payload['owner_user_id'] ?? 0))
                ->when(
                    $payload['team_id'] ?? null,
                    fn ($query, $teamId) => $query->where('team_id', (int) $teamId),
                    fn ($query) => $query->whereNull('team_id'),
                )
                ->where('status', 'active')
                ->whereNotNull('published_at')
                ->first();

            if (! $workflow) {
                return;
            }

            app(AutomationWorkflowDispatcher::class)->dispatchWorkflow(
                workflow: $workflow,
                input: $payload,
                externalEventId: (string) ($payload['external_event_id'] ?? ''),
                requestedByUserId: null,
                triggerKey: 'trigger_google_drive',
            );
        });

        register_integration_item('google_drive_automation', [
            'order' => 95,
            'label' => 'Google Drive Automation',
            'icon' => 'fa-brands fa-google-drive',
            'color' => '#2563eb',
            'description' => 'Persistent workspace OAuth and push notification settings for Google Drive workflow triggers.',
            'required_fields' => ['client_id', 'client_secret'],
            'account_types' => ['oauth'],
            'fields' => [
                ['key' => 'status', 'label' => 'Status', 'type' => 'toggle', 'default' => '0'],
                ['key' => 'client_id', 'label' => 'OAuth Client ID', 'type' => 'text', 'default' => ''],
                ['key' => 'client_secret', 'label' => 'OAuth Client Secret', 'type' => 'secret', 'default' => ''],
                ['key' => 'permissions', 'label' => 'OAuth scopes', 'type' => 'textarea', 'default' => implode(' ', (array) config('modules.appautomation.google_drive.scopes', []))],
                ['key' => 'callback_url', 'label' => 'OAuth callback URL', 'type' => 'text', 'default' => Route::has('portal.automation.connections.google-drive.callback')
                    ? route('portal.automation.connections.google-drive.callback')
                    : url(trim((string) config('modules.appautomation.route_prefix', 'portal/automation'), '/').'/connections/google-drive/callback'), 'readonly' => true],
                ['key' => 'webhook_url', 'label' => 'Push notification URL', 'type' => 'text', 'default' => Route::has('api.automation.google-drive.notifications')
                    ? route('api.automation.google-drive.notifications')
                    : url(trim((string) config('modules.appautomation.api_prefix', 'api/automation/v1'), '/').'/google-drive/notifications')],
            ],
            'module' => ['name' => 'AppAutomation', 'source' => 'module'],
        ]);

        $this->commands([
            DispatchDueAutomationRunsCommand::class,
            MaintainGoogleDriveWatchesCommand::class,
            ProbeAutomationQueueCommand::class,
            ReconcileAutomationApprovalsCommand::class,
        ]);

        if ($this->app->runningInConsole()) {
            $this->app->booted(function (): void {
                $this->app->make(Schedule::class)
                    ->command('automation:drive-maintain')
                    ->everyFiveMinutes()
                    ->withoutOverlapping();
                $this->app->make(Schedule::class)
                    ->command('automation:dispatch-due-runs')
                    ->everyMinute()
                    ->withoutOverlapping();
                $this->app->make(Schedule::class)
                    ->command('automation:queue-heartbeat')
                    ->everyMinute()
                    ->withoutOverlapping();
                $this->app->make(Schedule::class)
                    ->command('automation:reconcile-approvals')
                    ->everyMinute()
                    ->withoutOverlapping();
            });
        }

        $this->app->afterResolving(SystemCronRegistry::class, function (SystemCronRegistry $registry): void {
            $registry->register([
                'key' => 'automation-google-drive-maintain',
                'name' => __('Google Drive Workflow Watcher'),
                'icon' => 'fa-brands fa-google-drive',
                'description' => __('Renew Drive notification channels and queue change polling for connected workspaces.'),
                'command' => 'automation:drive-maintain',
                'recommended' => true,
                'cron_expression' => '*/5 * * * *',
            ]);
            $registry->register([
                'key' => 'automation-workflow-runtime',
                'name' => __('Workflow Runtime Dispatcher'),
                'icon' => 'fa-light fa-diagram-project',
                'description' => __('Resume queued, delayed, retrying, and stale workflow executions.'),
                'command' => 'automation:dispatch-due-runs',
                'recommended' => true,
                'cron_expression' => '* * * * *',
            ]);
            $registry->register([
                'key' => 'automation-queue-heartbeat',
                'name' => __('Automation Worker Heartbeat'),
                'icon' => 'fa-light fa-heart-pulse',
                'description' => __('Queue a probe that proves the dedicated Automation worker is processing jobs.'),
                'command' => 'automation:queue-heartbeat',
                'recommended' => true,
                'cron_expression' => '* * * * *',
            ]);
            $registry->register([
                'key' => 'automation-approval-reconciler',
                'name' => __('Workflow Approval Reconciler'),
                'icon' => 'fa-light fa-badge-check',
                'description' => __('Resume workflow runs after every related publishing review reaches a final decision.'),
                'command' => 'automation:reconcile-approvals',
                'recommended' => true,
                'cron_expression' => '* * * * *',
            ]);
        });

        TeamPermissionRegistry::registerModule('automation', [
            'label' => __('Developer & API'),
            'configurable' => true,
            'enabled_by_default' => true,
            'permissions' => [
                'automation.credentials.manage' => [
                    'label' => __('Manage automation connections and credentials'),
                    'defaults' => [
                        'admin' => ['automation.credentials.manage'],
                    ],
                ],
            ],
        ]);

        register_plan_permission([
            'key' => 'automation',
            'label' => __('Developer & API'),
            'type' => 'config',
            'order' => 190,
            'fields' => [
                [
                    'key' => 'max_automation_api_keys',
                    'label' => __('Max automation API keys'),
                    'type' => 'number',
                    'default' => 10,
                ],
                [
                    'key' => 'max_automation_webhooks',
                    'label' => __('Max automation webhooks'),
                    'type' => 'number',
                    'default' => 10,
                ],
            ],
        ]);

        $this->app->booted(function (): void {
            \Pricing::addSubFeatures([
                ['sort' => 135, 'parent' => 'features', 'tab_id' => 'workspace', 'tab_name' => __('Workspace'), 'key' => 'automation', 'label' => __('Developer & API'), 'check' => true, 'type' => 'boolean', 'raw' => 0],
                ['sort' => 136, 'parent' => 'features', 'tab_id' => 'workspace', 'tab_name' => __('Workspace'), 'key' => 'max_automation_api_keys', 'label' => __('Developer API keys'), 'check' => true, 'type' => 'number', 'raw' => 0],
                ['sort' => 137, 'parent' => 'features', 'tab_id' => 'workspace', 'tab_name' => __('Workspace'), 'key' => 'max_automation_webhooks', 'label' => __('Outbound webhooks'), 'check' => true, 'type' => 'number', 'raw' => 0],
            ]);
        });
    }
}
