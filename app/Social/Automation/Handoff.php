<?php
namespace App\Social\Automation;

use App\Social\Models\SocialInboxMessage;
use Illuminate\Support\Facades\DB;
use Modules\AdminNotifications\Services\NotificationService;

class Handoff
{
    public function open(string $key, Run $run, Rule $rule, SocialInboxMessage $message): void
    {
        DB::transaction(function () use ($key, $run, $rule, $message) {
            $conversation = $message->conversation()->lockForUpdate()->firstOrFail();
            $settings = $run->snapshot['settings'];
            $changes = ['automation_paused_until'=>now()->addDay()];
            if (!empty($settings['assign_handoff_enabled']) && !$conversation->assigned_user_id) {
                $changes['assigned_user_id'] = $rule->created_by_user_id;
            }
            $conversation->update($changes);
            $run->update(['status'=>'handoff','reason'=>'Business information is missing or human support is needed.']);
            if (!empty($settings['notify_handoff_enabled']) && class_exists(NotificationService::class)) {
                app(NotificationService::class)->sendAuto((int)$rule->created_by_user_id,
                    __('An interaction needs your attention. Open Activity & review in the rule’s workspace.'),
                    route('portal.'.str_replace('_','-',$key),['tab'=>'activity']), __('Human support needed'));
            }
        });
    }
}
