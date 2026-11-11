<?php

namespace Modules\AppWatermark\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\AppChannels\Models\SocialAccount;
use Modules\AppFiles\Models\AppFile;
use Modules\AppTeams\Support\TeamWorkspaceAccess;
use Modules\AppWatermark\Models\PublishingWatermark;

#[Title('Watermark')]
class WatermarkWorkspace extends Component
{
    #[Url(as: 'account')]
    public string $selectedAccountId = '';

    public ?int $editingId = null;

    public string $type = 'image';

    public string $fileId = '';

    public string $filePreviewUrl = '';

    public string $text = '';

    public string $textPreset = 'glass';

    public string $textColor = 'brand-gradient';

    public string $textWeight = 'semibold';

    public string $position = 'bottom-right';

    public int $opacityPercent = 72;

    public int $scalePercent = 24;

    public string $previewSourceId = '';
    #[\Livewire\Attributes\Locked] public string $renderedPreview = '';
    public function updated($name): void { if ($name !== 'renderedPreview') $this->renderedPreview = ''; }
    public function renderPreview(): void
    {
        abort_unless($this->watermarksEnabled(), 404);
        $this->renderedPreview = '';
        $this->validate(['type'=>['required','in:image,text'],'text'=>['nullable','string','max:1000'],
            'opacityPercent'=>['integer','between:5,100'],'scalePercent'=>['integer','between:5,90'],
            'position'=>['required','in:top-left,top-right,center,bottom-left,bottom-right']]);
        $source = \Modules\AppTeams\Support\PortalLibraryAccess::query(AppFile::query())->whereKey((int)$this->previewSourceId)
            ->whereIn('mime_type',['image/jpeg','image/png','image/webp'])->where('size_bytes','<=',10*1024*1024)->first();
        if (!$source) { $this->addError('previewSourceId', __('Choose a JPG, PNG or WebP image from this workspace.')); return; }
        if ((int)$source->width * (int)$source->height > 24000000) { $this->addError('previewSourceId', __('Choose a sample image smaller than 24 megapixels.')); return; }
        $overlay = $this->type === 'image' ? \Modules\AppTeams\Support\PortalLibraryAccess::query(AppFile::query())->whereKey((int)$this->fileId)->where('is_image',true)->first() : null;
        if (($this->type === 'image' && !$overlay) || ($this->type === 'text' && trim($this->text)==='')) {
            $this->addError('previewSourceId', __('Choose your watermark image or enter watermark text first.')); return;
        }
        try {
            $watermark = new PublishingWatermark(['type'=>$this->type,'text'=>$this->text,'position'=>$this->position,
                'opacity_percent'=>$this->opacityPercent,'scale_percent'=>$this->scalePercent,
                'metadata'=>['text_preset'=>$this->textPreset,'text_color'=>$this->textColor,'text_weight'=>$this->textWeight]]);
            if ($overlay) $watermark->setRelation('file',$overlay);
            $binary = app(\Modules\AppWatermark\Services\PublishingWatermarkProcessor::class)->renderWatermarkedBinary($source,$watermark);
            if (!$binary) throw new \RuntimeException('No image output');
            $this->renderedPreview = 'data:'.$source->mime_type.';base64,'.base64_encode($binary);
        } catch (\Throwable $e) { report($e); $this->addError('previewSourceId', __('Preview could not be rendered. Check the image and try again.')); }
    }

    public function mount(Request $request): void
    {
        abort_unless($this->watermarksEnabled(), 404);

        $accountId = trim((string) $request->query('account', ''));
        $this->selectedAccountId = ctype_digit($accountId) ? $accountId : '';
        $this->loadWatermarkForSelection();
    }

    public function selectAccount(string $accountId = ''): void
    {
        abort_unless($this->watermarksEnabled(), 404);

        $this->selectedAccountId = ctype_digit(trim($accountId)) ? trim($accountId) : '';
        $this->loadWatermarkForSelection();
    }

    public function resetFormState(): void
    {
        abort_unless($this->watermarksEnabled(), 404);

        $this->loadWatermarkForSelection();
    }

    public function save(): void
    {
        abort_unless(\Modules\AppTeams\Support\PortalLibraryAccess::canManage('channel.manage'), 403);
        abort_unless($this->selectedAccountId !== '' || \Modules\AppTeams\Support\PortalLibraryAccess::canManage('team.manage'), 403);
        abort_unless($this->watermarksEnabled(), 404);

        $user = auth()->user();
        $workspaceOwnerUserId = $this->workspaceOwnerUserId();
        $accessibleAccountIds = $this->accessibleAccountIds();
        $validated = $this->validate([
            'selectedAccountId' => ['nullable', 'integer', Rule::in($accessibleAccountIds)],
            'type' => ['required', Rule::in(['image', 'text'])],
            'fileId' => ['nullable', Rule::exists('files', 'id')],
            'text' => ['nullable', 'string', 'max:1000'],
            'textPreset' => ['nullable', Rule::in(['glass', 'solid-dark', 'solid-light', 'minimal'])],
            'textColor' => ['nullable', Rule::in(['brand-gradient', 'sunset-gradient', 'ocean-gradient', 'dark', 'white'])],
            'textWeight' => ['nullable', Rule::in(['medium', 'semibold', 'bold'])],
            'position' => ['required', Rule::in(['top-left', 'top-right', 'center', 'bottom-left', 'bottom-right'])],
            'opacityPercent' => ['required', 'integer', 'min:5', 'max:100'],
            'scalePercent' => ['required', 'integer', 'min:5', 'max:90'],
        ], [], [
            'selectedAccountId' => __('account'),
            'fileId' => __('watermark image'),
            'textPreset' => __('text style'),
            'textColor' => __('text color'),
            'textWeight' => __('font weight'),
            'opacityPercent' => __('transparent'),
            'scalePercent' => __('size'),
        ]);

        $targetAccountId = filled($validated['selectedAccountId'] ?? null) ? (int) $validated['selectedAccountId'] : null;
        $fileId = filled($validated['fileId'] ?? null) ? (int) $validated['fileId'] : null;
        $text = trim((string) ($validated['text'] ?? '')) ?: null;

        if ($validated['type'] === 'image' && ! $fileId) {
            $this->addError('fileId', __('Please select an image file for the watermark.'));

            return;
        }

        if ($validated['type'] === 'text' && ! $text) {
            $this->addError('text', __('Please enter watermark text.'));

            return;
        }

        if ($fileId && !\Modules\AppTeams\Support\PortalLibraryAccess::query(AppFile::query())->whereKey($fileId)->where('is_image', true)->exists()) {
            $this->addError('fileId', __('Choose an image from your workspace library.')); return;
        }
        $name = $this->defaultWatermarkName($targetAccountId);
        $existing = $this->findWatermarkForTarget($workspaceOwnerUserId, $targetAccountId);

        if ($existing && $this->editingId !== null && (int) $existing->id !== $this->editingId) {
            $current = \Modules\AppTeams\Support\PortalLibraryAccess::query(PublishingWatermark::query())->find($this->editingId);

            $existing->update([
                'name' => $name,
                'slug' => $this->uniqueSlug($name, $workspaceOwnerUserId, $existing->id),
                'type' => $validated['type'],
                'status' => 'active',
                'is_global' => $targetAccountId === null,
                'file_id' => $fileId,
                'text' => $text,
                'position' => $validated['position'],
                'opacity_percent' => (int) $validated['opacityPercent'],
                'scale_percent' => (int) $validated['scalePercent'],
                'metadata' => $this->metadataPayload($validated),
            ]);

            $existing->accounts()->sync($targetAccountId ? [$targetAccountId] : []);
            $current?->delete();
        } elseif ($existing) {
            $existing->update([
                'name' => $name,
                'slug' => $this->uniqueSlug($name, $workspaceOwnerUserId, $existing->id),
                'type' => $validated['type'],
                'status' => 'active',
                'is_global' => $targetAccountId === null,
                'file_id' => $fileId,
                'text' => $text,
                'position' => $validated['position'],
                'opacity_percent' => (int) $validated['opacityPercent'],
                'scale_percent' => (int) $validated['scalePercent'],
                'metadata' => $this->metadataPayload($validated),
            ]);

            $existing->accounts()->sync($targetAccountId ? [$targetAccountId] : []);
        } else {
            $watermark = PublishingWatermark::query()->create([
                'owner_user_id' => $workspaceOwnerUserId,
                'team_id' => $this->currentTeamId($user),
                'name' => $name,
                'slug' => $this->uniqueSlug($name, $workspaceOwnerUserId),
                'type' => $validated['type'],
                'status' => 'active',
                'is_global' => $targetAccountId === null,
                'file_id' => $fileId,
                'text' => $text,
                'position' => $validated['position'],
                'opacity_percent' => (int) $validated['opacityPercent'],
                'scale_percent' => (int) $validated['scalePercent'],
                'metadata' => $this->metadataPayload($validated),
            ]);

            $watermark->accounts()->sync($targetAccountId ? [$targetAccountId] : []);
        }

        $this->loadWatermarkForSelection();
        $this->dispatch('app-toast', type: 'success', message: __('Watermark saved successfully.'));
    }

    public function deleteWatermark(): void
    {
        abort_unless(\Modules\AppTeams\Support\PortalLibraryAccess::canManage('channel.manage'), 403);
        abort_unless($this->selectedAccountId !== '' || \Modules\AppTeams\Support\PortalLibraryAccess::canManage('team.manage'), 403);
        abort_unless($this->watermarksEnabled(), 404);

        if ($this->editingId === null) {
            return;
        }

        $watermark = \Modules\AppTeams\Support\PortalLibraryAccess::query(PublishingWatermark::query())
            ->findOrFail($this->editingId);

        $watermark->delete();
        $this->loadWatermarkForSelection();

        $this->dispatch('app-toast', type: 'success', message: __('Watermark deleted successfully.'));
    }

    public function render(): View
    {
        abort_unless($this->watermarksEnabled(), 404);

        $accounts = SocialAccount::query()
            ->whereIn('id', $this->accessibleAccountIds())
            ->where('is_active', true)
            ->orderBy('display_name')
            ->get();

        return view('appwatermark::livewire.workspace', [
            'canManage' => \Modules\AppTeams\Support\PortalLibraryAccess::canManage('channel.manage') && ($this->selectedAccountId !== '' || \Modules\AppTeams\Support\PortalLibraryAccess::canManage('team.manage')),
            'accounts' => $accounts,
            'selectedAccount' => $this->selectedAccountId !== '' ? $accounts->firstWhere('id', (int) $this->selectedAccountId) : null,
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Watermark'),
        ]);
    }

    protected function loadWatermarkForSelection(): void
    {
        if ($this->selectedAccountId !== '') abort_unless(in_array((int) $this->selectedAccountId, $this->accessibleAccountIds(), true), 404);
        $userId = $this->workspaceOwnerUserId();
        $targetAccountId = $this->selectedAccountId !== '' ? (int) $this->selectedAccountId : null;
        $watermark = $this->findWatermarkForTarget($userId, $targetAccountId);

        $this->editingId = $watermark?->id;
        $this->type = (string) ($watermark?->type ?? 'image');
        $this->fileId = $watermark?->file_id ? (string) $watermark->file_id : '';
        $this->filePreviewUrl = $watermark?->file ? route('portal.files.preview', $watermark->file) : '';
        $this->text = (string) ($watermark?->text ?? '');
        $this->textPreset = (string) data_get($watermark?->metadata, 'text_preset', 'glass');
        $this->textColor = (string) data_get($watermark?->metadata, 'text_color', 'brand-gradient');
        $this->textWeight = (string) data_get($watermark?->metadata, 'text_weight', 'semibold');
        $this->position = (string) ($watermark?->position ?? 'bottom-right');
        $this->opacityPercent = (int) ($watermark?->opacity_percent ?? 72);
        $this->scalePercent = (int) ($watermark?->scale_percent ?? 24);

        $this->resetErrorBag();
        $this->resetValidation();
    }

    protected function metadataPayload(array $validated): array
    {
        return [
            'text_preset' => (string) ($validated['textPreset'] ?? 'glass'),
            'text_color' => (string) ($validated['textColor'] ?? 'brand-gradient'),
            'text_weight' => (string) ($validated['textWeight'] ?? 'semibold'),
        ];
    }

    protected function findWatermarkForTarget(int $userId, ?int $accountId): ?PublishingWatermark
    {
        return \Modules\AppTeams\Support\PortalLibraryAccess::query(PublishingWatermark::query())
            ->with(['accounts', 'file'])
            ->when(
                $accountId,
                fn ($query, $resolvedAccountId) => $query->whereHas('accounts', fn ($accountQuery) => $accountQuery->where('social_accounts.id', $resolvedAccountId)),
                fn ($query) => $query->where('is_global', true)
            )
            ->latest('id')
            ->first();
    }

    protected function defaultWatermarkName(?int $accountId): string
    {
        if ($accountId === null) {
            return __('Global watermark');
        }

        $account = SocialAccount::query()
            ->whereIn('id', $this->accessibleAccountIds())
            ->find($accountId);

        return $account ? __('Watermark for :name', ['name' => $account->display_name]) : __('Account watermark');
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
        return TeamWorkspaceAccess::activeTeam($user)?->id;
    }

    protected function workspaceOwnerUserId(): int
    {
        return TeamWorkspaceAccess::workspaceOwnerUserId(auth()->user());
    }

    protected function accessibleAccountIds(): array
    {
        return TeamWorkspaceAccess::accessibleAccountsQuery(auth()->user())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    protected function watermarksEnabled(): bool
    {
        $user = auth()->user();
        $team = TeamWorkspaceAccess::activeTeam($user);
        $planOwner = $team?->owner ?: $user;

        return ! $planOwner?->plan || $planOwner->hasPlanFeature('watermark');
    }
}
