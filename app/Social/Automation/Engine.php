<?php
namespace App\Social\Automation;

use App\Social\Models\SocialInboxMessage as Message;
use App\Social\Services\SocialInboxMetaReplyService as Sender;
use Illuminate\Support\Facades\Cache;
use Modules\AdminUser\Models\User;

class Engine
{
    public function __construct(private Registry $registry, private Matcher $matcher, private Access $access) {}

    // Called inside the inbound transaction: a durable outbox, never a provider call.
    public function capture(Message $message): void
    {
        foreach ($this->registry->all() as $key=>$module) {
            if ($module['interaction'] !== $message->interaction_type) continue;
            $account = $message->conversation->account;
            $rules = $module['rule']::where('owner_user_id', $account->created_by_user_id)
                ->where('active', true)->orderBy('priority')->orderBy('id')->get();
            foreach ($rules as $rule) {
                $s = $rule->settings;
                if (!in_array((int)$account->id, array_map('intval', $s['account_ids'] ?? []), true)
                    || !$rule->activated_at || !$message->occurred_at || $message->occurred_at->lt($rule->activated_at)
                    || !$this->access->ruleAllowed($key, $rule, $account)
                    || !$this->matcher->withinHours($s)
                    || !$this->matcher->matches($s, (string)$message->body, data_get($message->payload,'post_id'))) continue;
                $module['run']::firstOrCreate(['inbound_message_id'=>$message->id], [
                    'rule_id'=>$rule->id,'owner_user_id'=>$rule->owner_user_id,'team_id'=>$rule->team_id,
                    'social_account_id'=>$account->id, 'status'=>'pending',
                    'snapshot'=>['name'=>$rule->name,'settings'=>$s,'fingerprint'=>$rule->fingerprint()],
                ]);
                break;
            }
        }
    }

    public function process(string $key, int $id): void
    {
        $module = $this->registry->get($key);
        if (!$module || !$run = $module['run']::find($id)) return;
        $lock = Cache::lock('social-auto:owner:'.$run->owner_user_id, 300);
        if (!$lock->get()) return;
        try {
            $run->refresh();
            if ($run->status !== 'pending') return;
            $rule = $module['rule']::find($run->rule_id);
            $message = Message::find($run->inbound_message_id);
            $reason = $this->blocked($key, $module, $run, $rule, $message);
            if ($reason) { $run->update(['status'=>'skipped','reason'=>$reason]); return; }
            $run->update(['status'=>'processing','started_at'=>now(),'reserved_at'=>now()]);
            $s = $run->snapshot['settings'];
            if (!$run->reply) {
                if (($s['response_type'] ?? 'template') === 'ai') {
                    $result = app(AiWriter::class)->write(User::findOrFail($run->owner_user_id), $key, $run, $message);
                    if ($result['handoff']) {
                        app(Handoff::class)->open($key, $run, $rule, $message);
                        return;
                    }
                    $reply = $result['answer'];
                } else {
                    $reply = trim((string)($s['response'] ?? ''));
                }
                if ($reply === '' || mb_strlen($reply)>1000) throw new \RuntimeException('The reply must contain 1–1000 characters.');
                $run->update(['reply'=>$reply]);
            }
            if (($s['delivery'] ?? 'review') === 'review' && !$run->reviewed_by_user_id) {
                $run->update(['status'=>'review','reason'=>'Review this response before sending.']);
                return;
            }
            // AI may take time: recheck the exact target, handoff, plan and rule before sending.
            $reason = $this->blocked($key, $module, $run, $rule->fresh(), $message->fresh());
            if ($reason) { $run->update(['status'=>'skipped','reason'=>$reason]); return; }
            $out = app(Sender::class)->send($message->conversation->fresh(), $run->reply,
                (int)($run->reviewed_by_user_id ?: $rule->created_by_user_id),
                hash('sha256', $key.':'.$run->id), $message->id, true);
            $run->update(['status'=>'sent','reason'=>null,'outbound_message_id'=>$out->id,'sent_at'=>$out->sent_at]);
        } catch (\Throwable $e) {
            report($e);
            $out = Message::where('request_key', hash('sha256', $key.':'.$id))->first();
            $run->update(['status'=>$out?->delivery_status === 'sent' ? 'sent' : (in_array($out?->delivery_status,['unknown','sending']) ? 'unknown' : 'failed'),
                'outbound_message_id'=>$out?->id, 'sent_at'=>$out?->delivery_status === 'sent' ? $out->sent_at : null, 'reason'=>$out?->delivery_status === 'sent' ? null : 'Response could not be completed. Check the connection and delivery history.']);
        } finally { $lock->release(); }
    }

    private function blocked(string $key, array $module, Run $run, ?Rule $rule, ?Message $message): ?string
    {
        if (!$rule || !$rule->active || $rule->fingerprint() !== ($run->snapshot['fingerprint'] ?? '')) return 'The rule was paused or changed.';
        if (!$message || !$account = $message->conversation?->account) return 'The original interaction is unavailable.';
        if (!$account->is_active || !$this->access->ruleAllowed($key,$rule,$account)) return 'Channel, plan or team access is unavailable.';
        if ($run->reviewed_by_user_id) {
            $reviewerRule = clone $rule;
            $reviewerRule->created_by_user_id = $run->reviewed_by_user_id;
            if (!$this->access->ruleAllowed($key,$reviewerRule,$account)) return 'The reviewer no longer has access to this channel.';
        }
        $c = $message->conversation;
        if ($c->assigned_user_id || ($c->automation_paused_until && now()->lt($c->automation_paused_until))) return 'A team member has taken over this conversation.';
        if ($message->interaction_type === 'message' && ((int)$c->last_inbound_message_id !== (int)$message->id || $message->occurred_at->lt(now()->subHours(24)))) return 'A newer message arrived or the messaging window expired.';
        $s = $run->snapshot['settings'];
        if (($s['response_type'] ?? '') === 'ai' && !User::find($run->owner_user_id)?->canUsePlanFeature($key.'_ai')) return 'AI responses are no longer available in this plan.';
        if (!$this->matcher->withinHours($s)) return 'Outside the rule’s operating hours.';
        // Approved drafts retain their reservation while waiting for the dispatcher.
        $sent = $module['run']::where('owner_user_id',$run->owner_user_id)->where('id','!=',$run->id)
            ->where(fn($q)=>$q->whereIn('status',['sent','review','processing','unknown'])
                ->orWhere(fn($q)=>$q->where('status','pending')->whereNotNull('reserved_at')));
        $owner = User::find($run->owner_user_id);
        $monthly = (int)$owner->planLimit($key.'_monthly_limit',1000);
        if ($monthly >= 0 && (clone $sent)->whereRaw('COALESCE(sent_at, reserved_at, created_at) >= ?', [now()->startOfMonth()])->count() >= $monthly) return 'The monthly response limit has been reached.';
        if ((clone $sent)->where('rule_id',$rule->id)->whereRaw('COALESCE(sent_at, reserved_at, created_at) >= ?', [now()->startOfDay()])->count() >= (int)($s['daily_limit'] ?? 100)) return 'The rule’s daily limit has been reached.';
        $cooldown = (int)($s['cooldown'] ?? 60);
        if (!empty($s['customer_cooldown_enabled']) && $cooldown > 0 && $message->sender_external_id) {
            $recent = Message::where('social_account_id',$account->id)->where('direction','outbound')
                ->where('delivery_status','sent')->where('sent_at','>',now()->subSeconds($cooldown))
                ->whereHas('conversation',fn($q)=>$q->where('participant_external_id',$message->sender_external_id));
            if ($recent->exists()) return 'This customer recently received a reply on this channel.';
        }
        if ($cooldown > 0 && $c->messages()->where('direction','outbound')->where('delivery_status','sent')->where('sent_at','>',now()->subSeconds($cooldown))->exists()) return 'The conversation is in its reply cooldown.';
        return null;
    }
}
