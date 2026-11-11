<?php

namespace Modules\AppWatermark\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Modules\AdminUser\Models\Team;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppWatermark\Models\PublishingWatermark;

class AppWatermarkController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($this->watermarksEnabled($request), 404);

        $user = $request->user();
        $userId = (int) $user->id;

        $watermarks = PublishingWatermark::query()
            ->ownedBy($userId)
            ->with(['accounts', 'file'])
            ->orderByDesc('id')
            ->get();

        $accounts = SocialAccount::query()
            ->where('created_by_user_id', $userId)
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get();

        $selectedAccountId = (string) $request->query('account', '');
        $editing = $this->findWatermarkForTarget($userId, $selectedAccountId !== '' ? (int) $selectedAccountId : null);

        return view('appwatermark::index', [
            'watermarks' => $watermarks,
            'accounts' => $accounts,
            'editing' => $editing,
            'selectedAccountId' => $selectedAccountId,
            'form' => [
                'type' => old('type', $editing?->type ?? 'image'),
                'account_id' => old('account_id', $selectedAccountId),
                'file_id' => old('file_id', $editing?->file_id ? (string) $editing->file_id : ''),
                'file_preview' => old('file_id_preview', $editing?->file ? route('portal.files.preview', $editing->file) : ''),
                'text' => old('text', $editing?->text ?? ''),
                'text_preset' => old('text_preset', data_get($editing?->metadata, 'text_preset', 'glass')),
                'text_color' => old('text_color', data_get($editing?->metadata, 'text_color', 'brand-gradient')),
                'text_weight' => old('text_weight', data_get($editing?->metadata, 'text_weight', 'semibold')),
                'position' => old('position', $editing?->position ?? 'bottom-right'),
                'opacity_percent' => old('opacity_percent', $editing?->opacity_percent ?? 72),
                'scale_percent' => old('scale_percent', $editing?->scale_percent ?? 24),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->watermarksEnabled($request), 404);

        $user = $request->user();
        $validated = $this->validateForm($request);
        $targetAccountId = $validated['account_id'];
        $validated['name'] = $this->defaultWatermarkNameForRequest($request, $targetAccountId);
        $existing = $this->findWatermarkForTarget((int) $user->id, $targetAccountId);

        if ($existing) {
            $existing->update([
                'name' => $validated['name'],
                'slug' => $this->uniqueSlug($validated['name'], (int) $user->id, $existing->id),
                'type' => $validated['type'],
                'status' => 'active',
                'is_global' => $targetAccountId === null,
                'file_id' => $validated['file_id'],
                'text' => $validated['text'],
                'position' => $validated['position'],
                'opacity_percent' => $validated['opacity_percent'],
                'scale_percent' => $validated['scale_percent'],
                'metadata' => $validated['metadata'],
            ]);

            $existing->accounts()->sync($targetAccountId ? [$targetAccountId] : []);

            return redirect()->route('portal.watermarks', ['account' => $targetAccountId ?: null])
                ->with('status', __('Watermark updated successfully.'));
        }

        $watermark = PublishingWatermark::query()->create([
            'owner_user_id' => $user->id,
            'team_id' => $this->currentTeamId($user),
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($validated['name'], (int) $user->id),
            'type' => $validated['type'],
            'status' => 'active',
            'is_global' => $targetAccountId === null,
            'file_id' => $validated['file_id'],
            'text' => $validated['text'],
            'position' => $validated['position'],
            'opacity_percent' => $validated['opacity_percent'],
            'scale_percent' => $validated['scale_percent'],
            'metadata' => $validated['metadata'],
        ]);

        $watermark->accounts()->sync($targetAccountId ? [$targetAccountId] : []);

        return redirect()->route('portal.watermarks', ['account' => $targetAccountId ?: null])->with('status', __('Watermark created successfully.'));
    }

    public function update(Request $request, PublishingWatermark $watermark): RedirectResponse
    {
        abort_unless($this->watermarksEnabled($request), 404);
        abort_unless((int) $watermark->owner_user_id === (int) $request->user()->id, 404);
        $validated = $this->validateForm($request);
        $targetAccountId = $validated['account_id'];
        $validated['name'] = $this->defaultWatermarkNameForRequest($request, $targetAccountId);
        $existing = $this->findWatermarkForTarget((int) $request->user()->id, $targetAccountId);

        if ($existing && (int) $existing->id !== (int) $watermark->id) {
            $existing->update([
                'name' => $validated['name'],
                'slug' => $this->uniqueSlug($validated['name'], (int) $request->user()->id, $existing->id),
                'type' => $validated['type'],
                'status' => 'active',
                'is_global' => $targetAccountId === null,
                'file_id' => $validated['file_id'],
                'text' => $validated['text'],
                'position' => $validated['position'],
                'opacity_percent' => $validated['opacity_percent'],
                'scale_percent' => $validated['scale_percent'],
                'metadata' => $validated['metadata'],
            ]);

            $existing->accounts()->sync($targetAccountId ? [$targetAccountId] : []);
            $watermark->delete();

            return redirect()->route('portal.watermarks', ['account' => $targetAccountId ?: null])
                ->with('status', __('Watermark updated successfully.'));
        }

        $watermark->update([
            'name' => $validated['name'],
            'slug' => $this->uniqueSlug($validated['name'], (int) $request->user()->id, $watermark->id),
            'type' => $validated['type'],
            'status' => 'active',
            'is_global' => $targetAccountId === null,
            'file_id' => $validated['file_id'],
            'text' => $validated['text'],
            'position' => $validated['position'],
            'opacity_percent' => $validated['opacity_percent'],
            'scale_percent' => $validated['scale_percent'],
            'metadata' => $validated['metadata'],
        ]);

        $watermark->accounts()->sync($targetAccountId ? [$targetAccountId] : []);

        return redirect()->route('portal.watermarks', ['account' => $targetAccountId ?: null])->with('status', __('Watermark updated successfully.'));
    }

    public function destroy(Request $request, PublishingWatermark $watermark): RedirectResponse
    {
        abort_unless($this->watermarksEnabled($request), 404);
        abort_unless((int) $watermark->owner_user_id === (int) $request->user()->id, 404);
        $watermark->delete();

        return redirect()->route('portal.watermarks')->with('status', __('Watermark deleted successfully.'));
    }

    protected function validateForm(Request $request): array
    {
        $userId = (int) $request->user()->id;
        $validated = $request->validate([
            'type' => ['required', Rule::in(['image', 'text'])],
            'account_id' => ['nullable', 'integer', Rule::exists('social_accounts', 'id')->where(fn ($query) => $query->where('created_by_user_id', $userId))],
            'file_id' => ['nullable', Rule::exists('files', 'id')],
            'text' => ['nullable', 'string', 'max:1000'],
            'text_preset' => ['nullable', Rule::in(['glass', 'solid-dark', 'solid-light', 'minimal'])],
            'text_color' => ['nullable', Rule::in(['brand-gradient', 'sunset-gradient', 'ocean-gradient', 'dark', 'white'])],
            'text_weight' => ['nullable', Rule::in(['medium', 'semibold', 'bold'])],
            'position' => ['required', Rule::in(['top-left', 'top-right', 'center', 'bottom-left', 'bottom-right'])],
            'opacity_percent' => ['required', 'integer', 'min:5', 'max:100'],
            'scale_percent' => ['required', 'integer', 'min:5', 'max:90'],
        ]);

        $validated['account_id'] = filled($validated['account_id'] ?? null) ? (int) $validated['account_id'] : null;
        $validated['file_id'] = filled($validated['file_id'] ?? null) ? (int) $validated['file_id'] : null;
        $validated['text'] = trim((string) ($validated['text'] ?? '')) ?: null;
        $validated['metadata'] = [
            'text_preset' => (string) ($validated['text_preset'] ?? 'glass'),
            'text_color' => (string) ($validated['text_color'] ?? 'brand-gradient'),
            'text_weight' => (string) ($validated['text_weight'] ?? 'semibold'),
        ];

        if ($validated['type'] === 'image' && ! $validated['file_id']) {
            abort(422, 'Please select an image file for the watermark.');
        }

        if ($validated['type'] === 'text' && ! $validated['text']) {
            abort(422, 'Please enter watermark text.');
        }

        return $validated;
    }

    protected function uniqueSlug(string $value, int $userId, ?int $ignoreId = null): string
    {
        $baseSlug = Str::slug($value) ?: 'watermark';
        $slug = $baseSlug;
        $counter = 2;

        while (PublishingWatermark::query()
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

    protected function watermarksEnabled(Request $request): bool
    {
        $user = $request->user();

        return ! $user?->plan || $user->hasPlanFeature('watermark');
    }

    protected function findWatermarkForTarget(int $userId, ?int $accountId): ?PublishingWatermark
    {
        return PublishingWatermark::query()
            ->ownedBy($userId)
            ->with(['accounts', 'file'])
            ->when(
                $accountId,
                fn ($query, $accountId) => $query->whereHas('accounts', fn ($accountQuery) => $accountQuery->where('social_accounts.id', $accountId)),
                fn ($query) => $query->where('is_global', true)
            )
            ->latest('id')
            ->first();
    }

    protected function defaultWatermarkName($accounts, string $selectedAccountId): string
    {
        if ($selectedAccountId === '') {
            return __('Global watermark');
        }

        $account = $accounts->firstWhere('id', (int) $selectedAccountId);

        return $account ? __('Watermark for :name', ['name' => $account->display_name]) : __('Account watermark');
    }

    protected function defaultWatermarkNameForRequest(Request $request, ?int $accountId): string
    {
        if (! $accountId) {
            return __('Global watermark');
        }

        $account = SocialAccount::query()
            ->where('created_by_user_id', (int) $request->user()->id)
            ->find($accountId);

        return $account ? __('Watermark for :name', ['name' => $account->display_name]) : __('Account watermark');
    }
}
