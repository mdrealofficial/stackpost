<?php
namespace App\Social\Automation;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Modules\AppTeams\Support\TeamWorkspaceAccess as Teams;
use App\Social\Models\SocialInboxMessage as Message;

abstract class Workspace extends Component
{
    use WithPagination;
    #[Locked] public ?int $editingId = null;
    public bool $editing = false;
    #[Url] public string $tab = 'rules';
    public string $search = '';
    public string $status = '';
    public array $form = [];
    public string $sample = '';
    public string $samplePostId = '';
    public ?bool $sampleMatches = null;
    public array $reviewEdits = [];
    public bool $showPostPicker = false;
    public bool $showConnectionDetails = false;
    public int $reportDays = 30;
    public string $reportProvider = '';
    public string $reportChannel = '';
    public string $reportRule = '';
    public bool $activityFromReport = false;
    public string $reportReason = '';

    abstract protected function moduleKey(): string;
    protected function definition(): array { return app(Registry::class)->get($this->moduleKey()); }
    protected function owner() { return Teams::activeTeam(auth()->user())?->owner ?: auth()->user(); }
    protected function teamId(): ?int { return Teams::activeTeam(auth()->user())?->id; }
    protected function assertAccess(string $action = 'view'): void { abort_unless(app(Access::class)->can($this->moduleKey(),$action),403); }
    public function mount(): void {
        $this->assertAccess();
        // Preserve old setup links without retaining a duplicate Channels page.
        if ($this->tab === 'connections') { $this->tab = 'rules'; $this->showConnectionDetails = true; }
    }
    public function updatedSearch(): void { $this->resetPage(); }
    public function updatedStatus(): void { $this->resetPage(); }
    public function updatedTab(): void { $this->resetPage(); }
    public function updatedReportProvider(): void { $this->reportChannel = ''; $this->resetPage(); }
    public function clearReportFilters(): void
    {
        $this->reset('reportDays','reportProvider','reportChannel','reportRule','reportReason','activityFromReport');
        $this->resetPage();
    }
    protected function reportQuery()
    {
        $days = in_array($this->reportDays,[7,30,90],true) ? $this->reportDays : 30;
        return $this->runs()->whereBetween('created_at',[now()->startOfDay()->subDays($days-1),now()])
            ->when($this->reportProvider !== '',fn($q)=>$q->whereIn('social_account_id',$this->accounts()->where('provider_key',$this->reportProvider)->pluck('id')))
            ->when($this->reportChannel !== '',fn($q)=>$q->where('social_account_id',$this->reportChannel))
            ->when($this->reportRule !== '',fn($q)=>$q->where('rule_id',$this->reportRule));
    }
    public function openReportActivity(string $status = '', string $reason = ''): void
    {
        $this->assertAccess();
        $this->status = in_array($status,['pending','review','sent','skipped','handoff','failed','unknown'],true) ? $status : '';
        $this->reportReason = mb_substr($reason,0,240);
        $this->activityFromReport = true; $this->tab = 'activity'; $this->resetPage();
    }
    public function clearActivityScope(): void
    {
        $this->activityFromReport = false; $this->reportReason = ''; $this->status = ''; $this->resetPage();
    }
    public function openReportGroup(string $kind, int $id): void
    {
        if ($kind === 'channel') $this->reportChannel = (string)$id;
        elseif ($kind === 'rule') $this->reportRule = (string)$id;
        else abort(422);
        $this->openReportActivity();
    }

    protected function accounts()
    {
        // Visibility follows authorization, not connection health.
        return Teams::accessibleAccountsQuery(auth()->user())->whereIn('provider_key',['facebook','instagram'])->get();
    }
    protected function ruleQuery()
    {
        $q = $this->definition()['rule']::where('owner_user_id',$this->owner()->id)->where('team_id',$this->teamId());
        $allowed = $this->accounts()->pluck('id')->map(fn($id)=>(int)$id)->all();
        $ids = (clone $q)->get()->filter(fn($r)=>!array_diff(array_map('intval',$r->settings['account_ids'] ?? []),$allowed))->pluck('id');
        return $q->whereIn('id',$ids);
    }
    protected function runs()
    {
        return $this->definition()['run']::where('owner_user_id',$this->owner()->id)->where('team_id',$this->teamId())
            ->whereIn('social_account_id',$this->accounts()->pluck('id'));
    }
    public function newRule(string $preset = ''): void
    {
        $this->assertAccess('manage');
        $this->resetValidation(); $this->editingId = null; $this->editing = true; $this->sampleMatches = null;
        $this->form = ['name'=>'','priority'=>10,'account_ids'=>[],'match'=>'contains','keywords'=>'','exclude'=>'',
            'post_ids'=>'','response_type'=>'template','response'=>'','knowledge'=>'','guidance'=>'',
            'delivery'=>'review','cooldown'=>60,'daily_limit'=>100,'hours_enabled'=>false,
            'timezone'=>config('app.timezone','UTC'),'start'=>'09:00','end'=>'18:00',
            'history_enabled'=>false,'editable_review_enabled'=>false,'customer_cooldown_enabled'=>false,
            'assign_handoff_enabled'=>false,'notify_handoff_enabled'=>false];
        $this->showPostPicker = false;
        if ($preset === 'question') {
            $this->form['name'] = __('Product questions');
            $this->form['keywords'] = "price\npricing\ngiá";
        } elseif ($preset === 'welcome') {
            $this->form['name'] = __('Welcome response');
            $this->form['match'] = 'all';
            $this->form['response'] = __('Thanks for reaching out. How can we help you?');
        }
    }
    public function editRule(int $id): void
    {
        $this->assertAccess('manage');
        $rule = $this->ruleQuery()->findOrFail($id);
        $this->newRule();
        $this->editingId = $id;
        $this->form = array_merge($this->form,$rule->settings,['name'=>$rule->name,'priority'=>$rule->priority]);
    }
    public function closeEditor(): void { $this->editing = false; $this->resetValidation(); }
    protected function validatedSettings(): array
    {
        $this->validate([
            'form.name'=>'required|string|max:120','form.priority'=>'required|integer|min:1|max:100',
            'form.account_ids'=>'required|array|min:1|max:30','form.account_ids.*'=>'required|integer',
            'form.match'=>'required|in:all,contains,exact','form.keywords'=>'nullable|string|max:2000',
            'form.exclude'=>'nullable|string|max:2000','form.post_ids'=>'nullable|string|max:2000',
            'form.response_type'=>'required|in:template,ai','form.response'=>'nullable|string|max:1000',
            'form.knowledge'=>'nullable|string|max:8000','form.guidance'=>'nullable|string|max:2000',
            'form.delivery'=>'required|in:auto,review','form.cooldown'=>'required|integer|min:0|max:86400',
            'form.daily_limit'=>'required|integer|min:1|max:10000','form.hours_enabled'=>'boolean',
            'form.timezone'=>'required|timezone','form.start'=>'required|date_format:H:i','form.end'=>'required|date_format:H:i',
            'form.history_enabled'=>'boolean','form.editable_review_enabled'=>'boolean','form.customer_cooldown_enabled'=>'boolean',
            'form.assign_handoff_enabled'=>'boolean','form.notify_handoff_enabled'=>'boolean',
        ]);
        $s = array_intersect_key($this->form,array_flip(['account_ids','match','keywords','exclude','post_ids','response_type','response','knowledge','guidance','delivery','cooldown','daily_limit','hours_enabled','timezone','start','end','history_enabled','editable_review_enabled','customer_cooldown_enabled','assign_handoff_enabled','notify_handoff_enabled']));
        if (!empty($s['hours_enabled']) && $s['start'] === $s['end']) {
            throw \Illuminate\Validation\ValidationException::withMessages(['form.end'=>__('Choose different start and end times, or turn operating hours off for all-day coverage.')]);
        }
        if (!empty($s['customer_cooldown_enabled']) && (int)$s['cooldown'] === 0) {
            throw \Illuminate\Validation\ValidationException::withMessages(['form.cooldown'=>__('Set a cooldown greater than zero to prevent repeated customer replies.')]);
        }
        $s['account_ids'] = array_values(array_unique(array_map('intval',$s['account_ids'])));
        if (array_diff($s['account_ids'],$this->accounts()->pluck('id')->all())) {
            throw \Illuminate\Validation\ValidationException::withMessages(['form.account_ids'=>__('Choose channels you can manage.')]);
        }
        if ($s['match'] !== 'all' && !app(Matcher::class)->lines($s['keywords'] ?? '')) {
            throw \Illuminate\Validation\ValidationException::withMessages(['form.keywords'=>__('Enter at least one keyword.')]);
        }
        $bodyField = $s['response_type'] === 'ai' ? 'knowledge' : 'response';
        if (trim($s[$bodyField] ?? '') === '') {
            throw \Illuminate\Validation\ValidationException::withMessages(['form.'.$bodyField=>__('This information is required.')]);
        }
        if ($s['response_type'] === 'ai' && (!$this->owner()->canUsePlanFeature($this->moduleKey().'_ai') || !class_exists(\Modules\AppAIStudio\Support\AiContentStudioService::class))) {
            throw \Illuminate\Validation\ValidationException::withMessages(['form.response_type'=>__('AI responses are unavailable. Choose a saved response or ask your administrator to enable AI.')]);
        }
        if ($this->definition()['interaction'] !== 'comment') $s['post_ids'] = '';
        return $s;
    }
    public function saveRule(): void
    {
        $this->assertAccess('manage'); $s = $this->validatedSettings();
        DB::transaction(function () use ($s): void {
            // Serialize package limits across simultaneous editors.
            \Modules\AdminUser\Models\User::whereKey($this->owner()->id)->lockForUpdate()->firstOrFail();
            $module = $this->definition();
            $limit = (int)$this->owner()->planLimit($this->moduleKey().'_rules_limit',10);
            if (!$this->editingId && $limit >= 0 && $module['rule']::where('owner_user_id',$this->owner()->id)->count() >= $limit) {
                throw \Illuminate\Validation\ValidationException::withMessages(['form.name'=>__('Your plan’s rule limit has been reached.')]);
            }
            $rule = $this->editingId ? $this->ruleQuery()->findOrFail($this->editingId) : new ($module['rule']);
            $rule->fill(['owner_user_id'=>$this->owner()->id,'team_id'=>$this->teamId(),'created_by_user_id'=>auth()->id(),
                'name'=>trim($this->form['name']),'priority'=>(int)$this->form['priority'],'settings'=>$s,'active'=>false,'activated_at'=>null])->save();
        });
        $this->editing = false;
        $this->toast(__('Rule saved as paused. Test it before activating.'));
    }
    public function toggleRule(int $id): void
    {
        $this->assertAccess('manage');
        $r = $this->ruleQuery()->findOrFail($id);
        if (!$r->active) {
            $readiness = $this->readiness();
            $selected = $this->accounts()->whereIn('id',$r->settings['account_ids']);
            if ($selected->contains(fn($a)=>!app(Access::class)->ruleAllowed($this->moduleKey(),$r,$a))) {
                $this->toast(__('Enable this module for the team and check the rule owner’s channel permissions before activating.'),'warning'); return;
            }
            $unready = $selected->contains(fn($a)=>!$a->is_active || !filled($a->access_token ?: data_get($a->auth_data,'page_payload.access_token'))
                || !filled(data_get(integration_item_state($a->provider_key),'values.webhook_verify_token'))
                || !filled(data_get(integration_item_state($a->provider_key),'values.app_secret')));
            if (!$readiness['worker'] || $unready) { $this->toast(__('Finish channel webhook setup and background processing before activating.'),'warning'); return; }
        }
        $r->update(['active'=>!$r->active,'activated_at'=>$r->active ? null : now()]);
        $this->toast($r->active ? __('Rule activated for new interactions.') : __('Rule paused.'));
    }
    public function deleteRule(int $id): void
    {
        $this->assertAccess('manage'); $this->ruleQuery()->findOrFail($id)->delete();
        $this->toast(__('Rule deleted. Delivery history was retained.'));
    }
    public function testRule(): void
    {
        $this->assertAccess('manage');
        $this->validate(['sample'=>'required|string|max:3000','samplePostId'=>'nullable|string|max:191']);
        $this->sampleMatches = app(Matcher::class)->matches($this->form,$this->sample,$this->samplePostId ?: null);
    }
    public function approve(int $id): void
    {
        $this->assertAccess('manage'); $run = $this->runs()->findOrFail($id);
        abort_unless($run->status === 'review',422);
        $changes = ['status'=>'pending','reviewed_by_user_id'=>auth()->id(),'reason'=>null,'updated_at'=>now()];
        if (array_key_exists($id,$this->reviewEdits)) {
            abort_unless(data_get($run->snapshot,'settings.editable_review_enabled',false),403);
            $this->validate(['reviewEdits.'.$id=>'required|string|max:1000']);
            $text = trim($this->reviewEdits[$id]);
            if ($text === '') throw \Illuminate\Validation\ValidationException::withMessages(['reviewEdits.'.$id=>__('Enter a reply before approving.')]);
            $changes['reply'] = $text;
            $changes['snapshot'] = array_merge($run->snapshot,['original_reply'=>$run->reply]);
        }
        // Use the model for JSON casts, and lock to reject duplicate approvals.
        DB::transaction(function () use ($id,$changes) {
            $run = $this->runs()->lockForUpdate()->findOrFail($id);
            abort_unless($run->status === 'review',422);
            $run->update($changes);
        });
        unset($this->reviewEdits[$id]);
        $this->toast(__('Approved. Delivery will recheck channel access and the conversation before sending.'));
    }
    public function editReply(int $id): void
    {
        $this->assertAccess('manage'); $run = $this->runs()->findOrFail($id);
        abort_unless($run->status === 'review' && data_get($run->snapshot,'settings.editable_review_enabled',false),403);
        $this->reviewEdits[$id] = $run->reply;
    }
    public function resumeConversation(int $id): void
    {
        $this->assertAccess('manage');
        DB::transaction(function () use ($id) {
            $run = $this->runs()->lockForUpdate()->findOrFail($id);
            abort_unless($run->status === 'handoff' && !$run->handoff_resolved_at,422);
            $message = Message::findOrFail($run->inbound_message_id);
            $conversation = $message->conversation()->lockForUpdate()->firstOrFail();
            abort_unless(!$conversation->assigned_user_id || (int)$conversation->assigned_user_id === (int)auth()->id(),403);
            $conversation->update(['assigned_user_id'=>null,'automation_paused_until'=>null]);
            $run->update(['handoff_resolved_at'=>now(),'reviewed_by_user_id'=>auth()->id()]);
        });
        $this->toast(__('Automation resumed for new interactions. The previous reply will not be sent.'));
    }
    protected function publishedPosts()
    {
        $class = \Modules\AppPublishing\Models\PublishingPost::class;
        if ($this->definition()['interaction'] !== 'comment' || !class_exists($class)) return collect();
        return $class::where('user_id',$this->owner()->id)->where('status',4)
            ->whereIn('account_id',$this->accounts()->pluck('id')->intersect($this->form['account_ids'] ?? []))
            ->latest('id')->limit(100)->get()->filter(fn($post)=>preg_match('/^\d+(?:_\d+)?$/',(string)data_get($post->result,'remote_post_id','')) === 1);
    }
    public function selectPost(int $id): void
    {
        $this->assertAccess('manage');
        $post = $this->publishedPosts()->firstWhere('id',$id); abort_unless($post,404);
        $ids = app(Matcher::class)->lines($this->form['post_ids'] ?? '');
        $remote = (string)data_get($post->result,'remote_post_id');
        $this->form['post_ids'] = implode("\n", in_array($remote,$ids,true) ? array_values(array_diff($ids,[$remote])) : array_merge($ids,[$remote]));
    }
    public function discard(int $id): void
    {
        $this->assertAccess('manage');
        $this->runs()->whereKey($id)->whereIn('status',['review','pending'])->update(['status'=>'skipped','reason'=>'Dismissed by a team member.','updated_at'=>now()]);
    }
    public function reconcile(int $id, string $outcome): void
    {
        $this->assertAccess('manage'); abort_unless(in_array($outcome,['sent','not_sent']),422);
        DB::transaction(function () use ($id,$outcome): void {
            $run = $this->runs()->lockForUpdate()->findOrFail($id);
            abort_unless($run->status === 'unknown',422);
            $sentAt = $run->started_at ?: $run->created_at;
            $run->update(['status'=>$outcome === 'sent' ? 'sent' : 'skipped','sent_at'=>$outcome === 'sent' ? $sentAt : null,'reason'=>'Delivery manually checked by a team member. Send time uses the original attempt.','reviewed_by_user_id'=>auth()->id()]);
            Message::where('request_key',hash('sha256',$this->moduleKey().':'.$run->id))
                ->whereIn('delivery_status',['unknown','sending'])
                ->update(['delivery_status'=>$outcome === 'sent' ? 'sent' : 'cancelled','sent_at'=>$outcome === 'sent' ? $sentAt : null]);
        });
    }
    protected function readiness(): array
    {
        $accounts = $this->accounts();
        $worker = Cache::has('social-automation:heartbeat') && config('cache.default') !== 'array';
        $issues = [];
        if (!$worker && $accounts->isNotEmpty()) $issues[] = ['label'=>__('Automatic processing has not been detected'),
            'detail'=>__('Ask your administrator to check the background service.')];
        $providers = [];
        foreach ($accounts->pluck('provider_key')->unique() as $provider) {
            $values = data_get(integration_item_state($provider),'values',[]);
            $providers[$provider] = filled($values['webhook_verify_token'] ?? null) && filled($values['app_secret'] ?? null);
            if (!$providers[$provider]) $issues[] = ['label'=>__(':provider automation setup is incomplete',['provider'=>ucfirst($provider)]),
                'detail'=>__('Ask your administrator to finish the integration for incoming interactions.')];
        }
        $channels = $accounts->map(function ($a) use ($providers) {
            $hasToken = filled($a->access_token ?: data_get($a->auth_data,'page_payload.access_token'));
            $issue = !$a->is_active ? __('Channel is paused') : (!$hasToken ? __('Reconnect required') : null);
            return ['id'=>$a->id,'name'=>$a->display_name ?: $a->username ?: __('Channel'),
                'username'=>$a->username,'provider'=>$a->provider_key,'avatar'=>$a->avatar_url,
                'issue'=>$issue,'ready'=>$a->is_active && $hasToken && ($providers[$a->provider_key] ?? false),
                'action'=>!$a->is_active ? __('Manage channel') : __('Manage connection'),
                'url'=>route('portal.channels',['provider'=>$a->provider_key,'q'=>$a->username ?: $a->display_name])];
        });
        return ['worker'=>$worker,'channels'=>$channels,'issues'=>$issues,
            'attention'=>$channels->filter(fn($channel)=>$channel['issue'] !== null)->values()];
    }
    protected function toast(string $message, string $type='success'): void
    {
        $this->dispatch('app-toast', type:$type, title:__($this->definition()['label']), message:$message);
    }
    public function render()
    {
        $this->assertAccess();
        $q = $this->runs()->where('created_at','>=',now()->subDays(30));
        $counts = (clone $q)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total','status')->all();
        $sent = $this->runs()->where('status','sent')->where('sent_at','>=',now()->subDays(30));
        $counts['sent'] = (clone $sent)->count();
        $reportData = $this->tab === 'reports' ? app(Report::class)->build($this->reportQuery(),$this->reportDays) : null;
        $allRules = $this->ruleQuery()->orderBy('priority')->orderBy('id')->get();
        $runs = ($this->activityFromReport ? $this->reportQuery() : $this->runs())
            ->when($this->activityFromReport && $this->reportReason !== '',fn($q)=>$q->where('reason',$this->reportReason))
            ->when($this->status,fn($q)=>$q->where('status',$this->status))->latest()->paginate(15);
        return view('social-automation::workspace',[
            'module'=>$this->definition(),'moduleKey'=>$this->moduleKey(),'accounts'=>$this->accounts(),
            'publishedPosts'=>$this->editing && $this->showPostPicker ? $this->publishedPosts() : collect(),
            'responseTime'=>$reportData['responseSeconds'] ?? null,
            'report'=>$reportData,
            'reportRules'=>$allRules,
            'rules'=>$allRules->filter(fn($r)=>$this->search === '' || str_contains(mb_strtolower($r->name),mb_strtolower($this->search))),
            'activeRules'=>$allRules->where('active',true)->count(),'totalRules'=>$allRules->count(),
            'runs'=>$runs,'inboundMessages'=>Message::whereIn('id',$runs->pluck('inbound_message_id'))->get()->keyBy('id'),
            'counts'=>$counts,'readiness'=>$this->readiness(),'canManage'=>app(Access::class)->can($this->moduleKey(),'manage'),
        ])->layout(theme_view('layouts.app', 'app'), ['title'=>__($this->definition()['label']), 'fullWorkspace'=>true, 'fullWorkspacePaddingBottom'=>false]);
    }
}
