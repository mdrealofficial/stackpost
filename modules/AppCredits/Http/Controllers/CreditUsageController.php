<?php

namespace Modules\AppCredits\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\AppCredits\Models\CreditUsageLog;
use Modules\AppCredits\Models\CreditTopupLedger;
use Modules\AppCredits\Support\CreditSettings;
use Modules\AppCredits\Support\CreditTopupService;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

class CreditUsageController extends Controller
{
    public function index(Request $request, CreditTopupService $topups, CreditSettings $settings): View
    {
        [$user, $team, $planOwner] = $this->context();

        $query = CreditUsageLog::query()
            ->where('user_id', $planOwner?->id)
            ->where('action_key', 'like', 'ai_studio_%')
            ->when($request->string('action')->toString() !== '', fn ($builder) => $builder->where('action_key', $request->string('action')->toString()));

        $summaryQuery = clone $query;
        $entriesCount = (clone $summaryQuery)->count();
        $creditsUsed = (int) (clone $summaryQuery)->sum('amount');
        $actionsCount = (clone $summaryQuery)->distinct('action_key')->count('action_key');
        $topupLedger = CreditTopupLedger::query()
            ->where('user_id', $planOwner?->id)
            ->latest('id')
            ->limit(12)
            ->get();

        $canBuyMoreCredits = $planOwner
            && $user
            && (int) $planOwner->id === (int) $user->id
            && $topups->canUserBuyTopup($planOwner);

        return view('appcredits::portal.index', [
            'logs' => $query->latest('id')->paginate(25)->withQueryString(),
            'entriesCount' => $entriesCount,
            'creditsUsed' => $creditsUsed,
            'actionsCount' => $actionsCount,
            'creditSummary' => function_exists('credit_summary') ? credit_summary($planOwner) : ['remaining' => null, 'unlimited' => true],
            'actionOptions' => [
                'ai_studio_generate_captions' => __('Caption Generator'),
                'ai_studio_rss_rewrite' => __('RSS AI Rewrite'),
                'ai_studio_repurpose_content' => __('Repurpose'),
                'ai_studio_plan_calendar' => __('Content Planner'),
                'ai_studio_best_time' => __('Best Time'),
                'ai_studio_generate_image' => __('AI Image'),
                'ai_studio_generate_video' => __('AI Video'),
                'ai_studio_review_content' => __('AI Review'),
                'ai_studio_semantic_search' => __('Semantic Search'),
            ],
            'workspaceOwner' => $planOwner,
            'canBuyMoreCredits' => $canBuyMoreCredits,
            'creditPacks' => $canBuyMoreCredits ? $topups->availablePacksFor($planOwner) : collect(),
            'creditTopupRemaining' => $topups->remaining($planOwner),
            'creditSettings' => $settings,
            'topupLedger' => $topupLedger,
        ]);
    }

    protected function context(): array
    {
        $user = auth()->user();
        $team = TeamWorkspaceAccess::activeTeam($user);
        $planOwner = $team?->owner ?: $user;

        abort_unless(TeamWorkspaceAccess::teamHasModule($team, 'publishing'), 404);
        abort_unless(! $planOwner?->plan || $planOwner->hasPlanFeature('ai_studio'), 404);

        return [$user, $team, $planOwner];
    }
}
