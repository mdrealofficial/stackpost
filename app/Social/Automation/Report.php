<?php

namespace App\Social\Automation;

use Illuminate\Database\Eloquent\Builder;

class Report
{
    /** All outcomes belong to the same set of runs created during the selected period. */
    public function build(Builder $query, int $days): array
    {
        $days = in_array($days,[7,30,90],true) ? $days : 30;
        $labels = ['sent'=>__('Sent'),'pending'=>__('Processing'),'review'=>__('Awaiting review'),
            'handoff'=>__('Human support'),'failed'=>__('Failed'),'unknown'=>__('Unconfirmed'),'skipped'=>__('Skipped')];
        $colors = ['sent'=>'#147d78','pending'=>'#6592ba','review'=>'#c99836','handoff'=>'#9380b8',
            'failed'=>'#cc6262','unknown'=>'#d59665','skipped'=>'#a3adb2'];
        $counts = (clone $query)->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total','status')->all();
        $total = array_sum($counts);
        $table = $query->getModel()->getTable();
        $timing = (clone $query)->where('status','sent')->whereNotNull('sent_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, (SELECT occurred_at FROM social_inbox_messages WHERE id = '.$table.'.inbound_message_id), sent_at)) as seconds')
            ->whereRaw('sent_at >= (SELECT occurred_at FROM social_inbox_messages WHERE id = '.$table.'.inbound_message_id)')->value('seconds');
        $daily = (clone $query)->selectRaw("DATE(created_at) as day, COUNT(*) as total, SUM(status = 'sent') as sent, SUM(status IN ('failed','unknown')) as problems")
            ->groupBy('day')->get()->keyBy('day');
        $dates = collect(range($days-1,0))->map(fn($day)=>now()->startOfDay()->subDays($day));
        $series = [];
        foreach (['total'=>[__('Runs created'),'#a3adb2'],'sent'=>[__('Sent'),'#147d78'],'problems'=>[__('Failed or unconfirmed'),'#cc6262']] as $key=>[$label,$color]) {
            $series[] = ['name'=>$label,'color'=>$color,'data'=>$dates->map(fn($date)=>(int)($daily[$date->format('Y-m-d')]->$key ?? 0))->all()];
        }
        $groups = function(string $column) use ($query) {
            return (clone $query)->select($column)->selectRaw("COUNT(*) as total, SUM(status = 'sent') as sent, SUM(status IN ('pending','review')) as waiting, SUM(status IN ('failed','unknown','handoff')) as attention")
                ->groupBy($column)->orderByDesc('total')->limit(10)->get();
        };
        return [
            'days'=>$days,'from'=>now()->startOfDay()->subDays($days-1)->format('M j'),'to'=>now()->format('M j, Y'),
            'timezone'=>config('app.timezone','UTC'),'counts'=>$counts,'total'=>$total,'labels'=>$labels,'colors'=>$colors,
            'waiting'=>($counts['pending'] ?? 0)+($counts['review'] ?? 0),
            'attention'=>($counts['failed'] ?? 0)+($counts['unknown'] ?? 0)+($counts['handoff'] ?? 0),
            'responseSeconds'=>$timing,
            'responseTime'=>$timing === null ? '—' : ($timing < 60 ? number_format($timing).' s' : ($timing < 3600 ? number_format($timing/60,1).' min' : number_format($timing/3600,1).' h')),
            'failureRate'=>(($counts['sent'] ?? 0)+($counts['failed'] ?? 0)) ? round(100*($counts['failed'] ?? 0)/(($counts['sent'] ?? 0)+($counts['failed'] ?? 0)),1) : null,
            'categories'=>$dates->map(fn($d)=>$d->format('M j'))->all(),'series'=>$series,
            'outcomes'=>[['name'=>__('Runs'),'data'=>collect($labels)->map(fn($label,$state)=>['name'=>$label,'y'=>(int)($counts[$state] ?? 0),'color'=>$colors[$state]])->filter(fn($point)=>$point['y']>0)->values()->all()]],
            'channels'=>$groups('social_account_id'),'rules'=>$groups('rule_id'),
            'reasons'=>(clone $query)->whereIn('status',['skipped','handoff','failed','unknown'])->whereNotNull('reason')
                ->selectRaw('reason, COUNT(*) as total')->groupBy('reason')->orderByDesc('total')->limit(6)->get(),
        ];
    }
}
