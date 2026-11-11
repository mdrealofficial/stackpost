<?php

namespace Modules\AppDeveloperApi\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Modules\AppAutomation\Livewire\AutomationIndex;
use Modules\AppAutomation\Models\AutomationApiKey;
use Modules\AppAutomation\Models\AutomationLog;
use Modules\AppAutomation\Models\AutomationWebhook;
use Modules\AppAutomation\Services\AutomationApiKeyService;
use Modules\AppAutomation\Services\AutomationQueueReadiness;
use Modules\AppAutomation\Services\AutomationWebhookDispatcher;

#[Title('Developer & API')]
class DeveloperApiIndex extends AutomationIndex
{
    public array $keyPermissions = ['accounts:read', 'posts:write'];
    public array $selectedEvents = ['post.published', 'post.failed'];
    public string $activityStatus = '';
    public string $activityDirection = '';
    public int $activityLimit = 25;
    #[Locked] public string $revealedWebhookSecret = '';
    #[Locked] public ?int $detailLogId = null;

    public function createApiKey(): void
    {
        abort_unless($this->access->canManageCredentials(auth()->user()), 404);
        $this->validate(['keyName' => ['required','string','max:120'], 'keyPermissions' => ['required','array','min:1'], 'keyPermissions.*' => ['required',Rule::in(AutomationApiKeyService::ALLOWED_PERMISSIONS)]]);
        if (in_array('posts:publish',$this->keyPermissions,true) && !in_array('posts:write',$this->keyPermissions,true)) {
            throw ValidationException::withMessages(['keyPermissions' => __('Immediate publishing also requires Create posts permission.')]);
        }
        [, $plain] = $this->keys->create(auth()->user(), $this->keyName, $this->keyPermissions);
        $this->reset('keyName','keyPermissions'); $this->revealedApiKey = $plain;
        $this->dispatch('developer-key-created');
    }

    public function createWebhook(): void
    {
        abort_unless($this->access->canManageCredentials(auth()->user()),404);
        $this->validate(['webhookName'=>['required','string','max:120'], 'webhookUrl'=>['required','url:http,https','max:1000'],
            'selectedEvents'=>['required','array','min:1'], 'selectedEvents.*'=>['required',Rule::in(AutomationWebhookDispatcher::EVENTS)]]);
        try { $target=$this->outboundUrls->inspect($this->webhookUrl); }
        catch (\RuntimeException $e) { throw ValidationException::withMessages(['webhookUrl'=>$e->getMessage()]); }
        $secret=Str::random(40);
        AutomationWebhook::create(['user_id'=>$this->access->workspaceOwnerUserId(auth()->user()),'team_id'=>$this->access->teamId(auth()->user()),
            'name'=>trim($this->webhookName),'url'=>$target->url,'signing_secret'=>$secret,'events'=>array_values(array_unique($this->selectedEvents)),'is_active'=>true]);
        $this->reset('webhookName','webhookUrl','selectedEvents'); $this->revealedWebhookSecret=$secret;
        $this->dispatch('developer-webhook-created');
    }

    public function revealWebhookSecret(int $id): void { $this->revealedWebhookSecret=$this->findOwnedWebhook($id)->signing_secret; }
    public function dismissSecrets(): void { $this->revealedApiKey='';$this->revealedWebhookSecret=''; }
    public function testWebhook(int $id): void
    {
        $hook=$this->findOwnedWebhook($id);
        if (!$hook->is_active) { $this->notify(__('Paused'),__('Enable this webhook before testing.'),'warning');return; }
        $log=app(AutomationWebhookDispatcher::class)->enqueue($hook,'webhook.test',['event'=>'webhook.test','test'=>true,'occurred_at'=>now()->toIso8601String()]);
        $this->notify(__('Webhook test'),$log->fresh()->status==='failed'?__('Test could not be queued. Check Activity for details.'):__('Test queued. Check Activity for the delivery result.'),$log->fresh()->status==='failed'?'error':'success');
    }
    protected function ownedLog(int $id): AutomationLog
    {
        abort_unless($this->access->canManageCredentials(auth()->user()),404);
        return $this->workspaces->apply(AutomationLog::query(),$this->access->workspaceOwnerUserId(auth()->user()),$this->access->teamId(auth()->user()))->findOrFail($id);
    }
    public function retryDelivery(int $id): void
    {
        $log=$this->ownedLog($id);
        abort_unless($log->direction==='outbound',404);
        $hook=$log->webhook_id ? $this->findOwnedWebhook($log->webhook_id) : null;
        if (!$hook || !$hook->is_active) { $this->notify(__('Unavailable'),__('The webhook must be active before retrying.'),'warning');return; }
        $changed=app(AutomationWebhookDispatcher::class)->retry($log);
        $queued=$changed && $log->fresh()->status==='queued';
        $this->notify(__('Delivery'),$queued?__('Delivery queued for retry.'):__('Delivery could not be queued. Refresh Activity for its current status.'),$queued?'success':'warning');
    }
    public function showLog(int $id): void { $this->detailLogId=$this->ownedLog($id)->id; }
    public function closeLog(): void { $this->detailLogId=null; }
    public function loadMoreActivity(): void { $this->activityLimit=min(250,max(25,$this->activityLimit)+25); }
    public function updatedActivityStatus(): void { $this->activityLimit=25; }
    public function updatedActivityDirection(): void { $this->activityLimit=25; }

    public function render(): View
    {
        abort_unless($this->access->canManageCredentials(auth()->user()),404);
        $owner=$this->access->workspaceOwnerUserId(auth()->user());$team=$this->access->teamId(auth()->user());
        $scope=fn($query)=>$this->workspaces->apply($query,$owner,$team);
        $query=$scope(AutomationLog::query())->with('webhook');
        if(in_array($this->activityStatus,['queued','sending','retrying','failed','delivered','accepted','cancelled'],true))$query->where('status',$this->activityStatus);
        if(in_array($this->activityDirection,['inbound','outbound'],true))$query->where('direction',$this->activityDirection);
        $limit=min(250,max(25,$this->activityLimit));$total=(clone $query)->count();
        return view('appdeveloperapi::livewire.index',[
            'apiKeys'=>$scope(AutomationApiKey::query())->latest('id')->get(),
            'webhooks'=>$scope(AutomationWebhook::query())->latest('id')->get(),
            'logs'=>$query->latest('id')->limit($limit)->get(),'activityHasMore'=>$total>$limit && $limit<250,'activityTotal'=>$total,
            'detailLog'=>$this->detailLogId ? $this->ownedLog($this->detailLogId) : null,
            'workerReady'=>app(AutomationQueueReadiness::class)->ready(),
            'apiBase'=>url(config('modules.appautomation.api_prefix','api/automation/v1')),
            'exampleSchedule'=>now()->addDay()->startOfHour()->toIso8601String(),
        ])->layout(theme_view('layouts.app','app'),['title'=>__('Developer & API')]);
    }
}
