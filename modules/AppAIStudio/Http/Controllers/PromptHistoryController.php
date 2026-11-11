<?php

namespace Modules\AppAIStudio\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\AppAIStudio\Models\AIPromptHistory;
use Modules\AppTeams\Support\TeamWorkspaceAccess;

class PromptHistoryController extends Controller
{
    public function index(Request $request): View
    {
        [$user, $team, $planOwner] = $this->context();

        $query = AIPromptHistory::query()
            ->ownedBy(TeamWorkspaceAccess::workspaceOwnerUserId($user))
            ->requestedBy((int) $user->id)
            ->when($request->string('module')->toString() !== '', fn ($builder) => $builder->where('module', $request->string('module')->toString()))
            ->when($request->string('q')->toString() !== '', function ($builder) use ($request) {
                $term = $request->string('q')->toString();
                $builder->where(function ($inner) use ($term) {
                    $inner->where('title', 'like', "%{$term}%")
                        ->orWhere('prompt', 'like', "%{$term}%");
                });
            });

        return view('appaistudio::prompt-history.index', [
            'histories' => $query->latest('id')->paginate(20)->withQueryString(),
            'modules' => [
                'caption_generator' => __('AI Content'),
                'repurpose' => __('Repurpose'),
                'content_planner' => __('Content Planner'),
                'review' => __('AI Review'),
                'image' => __('AI Image'),
                'video' => __('AI Video'),
            ],
            'creditSummary' => function_exists('credit_summary') ? credit_summary($planOwner) : ['remaining' => null, 'unlimited' => true],
            'workspaceOwner' => $planOwner,
        ]);
    }

    public function update(Request $request, int $historyId): RedirectResponse
    {
        $history = $this->historyQuery()->whereKey($historyId)->firstOrFail();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:120'],
        ]);

        $history->update([
            'title' => trim($validated['title']),
        ]);

        return back()->with('status', __('Prompt history renamed successfully.'));
    }

    public function destroy(int $historyId): RedirectResponse
    {
        $history = $this->historyQuery()->whereKey($historyId)->firstOrFail();
        $history->delete();

        return back()->with('status', __('Prompt history deleted successfully.'));
    }

    protected function historyQuery()
    {
        $user = auth()->user();

        return AIPromptHistory::query()
            ->ownedBy(TeamWorkspaceAccess::workspaceOwnerUserId($user))
            ->requestedBy((int) $user->id);
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
