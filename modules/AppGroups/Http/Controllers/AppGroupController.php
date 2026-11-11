<?php

namespace Modules\AppGroups\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\AdminUser\Models\Team;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppGroups\Models\AccountGroup;

class AppGroupController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($this->groupsEnabled($request), 404);

        $userId = (int) $request->user()->id;
        $search = trim((string) $request->query('q', ''));
        $statusFilter = trim((string) $request->query('status', ''));

        $groups = AccountGroup::query()
            ->ownedBy($userId)
            ->withCount('accounts')
            ->with('accounts')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($nested) use ($search) {
                    $nested
                        ->where('name', 'like', '%'.$search.'%')
                        ->orWhere('description', 'like', '%'.$search.'%')
                        ->orWhere('slug', 'like', '%'.$search.'%');
                });
            })
            ->when(in_array($statusFilter, ['active', 'inactive'], true), function ($query) use ($statusFilter) {
                if ($statusFilter === 'active') {
                    $query->where('status', 'active');

                    return;
                }

                $query->whereIn('status', ['inactive', 'draft', 'archived']);
            })
            ->orderBy('name')
            ->get();

        $editing = $request->integer('edit')
            ? AccountGroup::query()->ownedBy($userId)->with('accounts')->find($request->integer('edit'))
            : null;

        $accounts = SocialAccount::query()
            ->where('created_by_user_id', $userId)
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get();

        return view('appgroups::index', [
            'groups' => $groups,
            'accounts' => $accounts,
            'editing' => $editing,
            'form' => [
                'name' => old('name', $editing?->name ?? ''),
                'description' => old('description', $editing?->description ?? ''),
                'color' => old('color', $editing?->color ?? '#2563eb'),
                'status' => old('status', $editing?->status ?? 'active'),
                'account_ids' => old('account_ids', $editing?->accounts->pluck('id')->map(fn ($id) => (string) $id)->all() ?? []),
            ],
            'filters' => [
                'q' => $search,
                'status' => $statusFilter,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->groupsEnabled($request), 404);

        $user = $request->user();
        $validated = $this->validateForm($request);
        $group = AccountGroup::query()->create([
            'owner_user_id' => $user->id,
            'team_id' => $this->currentTeamId($user),
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($validated['name'], (int) $user->id),
            'description' => $validated['description'],
            'color' => $validated['color'],
            'status' => $validated['status'],
        ]);

        $group->accounts()->sync($validated['account_ids']);

        return redirect()->route('portal.groups')->with('status', __('Group created successfully.'));
    }

    public function update(Request $request, AccountGroup $group): RedirectResponse
    {
        abort_unless($this->groupsEnabled($request), 404);
        abort_unless((int) $group->owner_user_id === (int) $request->user()->id, 404);
        $validated = $this->validateForm($request);

        $group->update([
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($validated['name'], (int) $request->user()->id, $group->id),
            'description' => $validated['description'],
            'color' => $validated['color'],
            'status' => $validated['status'],
        ]);

        $group->accounts()->sync($validated['account_ids']);

        return redirect()->route('portal.groups', ['edit' => $group->id])->with('status', __('Group updated successfully.'));
    }

    public function destroy(Request $request, AccountGroup $group): RedirectResponse
    {
        abort_unless($this->groupsEnabled($request), 404);
        abort_unless((int) $group->owner_user_id === (int) $request->user()->id, 404);
        $group->delete();

        return redirect()->route('portal.groups')->with('status', __('Group deleted successfully.'));
    }

    protected function validateForm(Request $request): array
    {
        $userId = (int) $request->user()->id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['required', 'string', 'max:24'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'account_ids' => ['nullable', 'array'],
            'account_ids.*' => [
                'integer',
                Rule::exists('social_accounts', 'id')->where(fn ($query) => $query->where('created_by_user_id', $userId)),
            ],
        ]);

        $validated['account_ids'] = array_values($validated['account_ids'] ?? []);

        return $validated;
    }

    protected function uniqueSlug(string $value, int $userId, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($value) ?: 'group';
        $slug = $baseSlug;
        $counter = 2;

        while (AccountGroup::query()
            ->ownedBy($userId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $baseSlug.'-'.$counter++;
        }

        return $slug;
    }

    protected function currentTeamId($user): ?int
    {
        return Team::query()->where('owner_user_id', $user->id)->value('id')
            ?: $user->teams()->value('teams.id');
    }

    protected function groupsEnabled(Request $request): bool
    {
        $user = $request->user();

        return ! $user?->plan || $user->hasPlanFeature('groups');
    }
}
