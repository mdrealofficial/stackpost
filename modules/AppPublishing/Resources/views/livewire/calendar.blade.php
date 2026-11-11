@php
    $dayCount = $days->count();
    $composerCanShortenUrls = url_shortening_enabled(auth()->user());
    $providerCardsByKey = collect(channel_provider_cards())->keyBy('key');
    $publishingApprovalTeam = \Modules\AppTeams\Support\TeamWorkspaceAccess::activeTeam(auth()->user());
    $publishingCanApprovePosts = auth()->user()
        && $publishingApprovalTeam
        && \Modules\AppTeams\Support\TeamWorkspaceAccess::hasPermission(auth()->user(), 'post.approve', $publishingApprovalTeam);

    $statusMeta = [
        'pending' => ['label' => __('Pending'), 'surface' => 'rgba(59, 130, 246, 0.12)', 'text' => '#2563eb'],
        'waiting_approve' => ['label' => __('Waiting approve'), 'surface' => 'rgba(245, 158, 11, 0.14)', 'text' => '#d97706'],
        'processing' => ['label' => __('Processing'), 'surface' => 'rgba(99, 102, 241, 0.12)', 'text' => '#4f46e5'],
        'published' => ['label' => __('Published'), 'surface' => 'rgba(16, 185, 129, 0.14)', 'text' => '#059669'],
        'failed' => ['label' => __('Failed'), 'surface' => 'rgba(239, 68, 68, 0.12)', 'text' => '#dc2626'],
        'draft' => ['label' => __('Draft'), 'surface' => 'rgba(99, 102, 241, 0.14)', 'text' => '#4f46e5'],
    ];
@endphp

<div
    class="flex h-full min-h-full flex-col"
    x-data="{
        composerBaseline: null,
        discardComposerOpen: false,
        composerSnapshot() {
            const state = JSON.parse(JSON.stringify(this.$wire.composer || {}));
            delete state.preview_account_id;
            state.caption = this.localPreviewCaption;
            state.schedule_slots = this.localScheduleSlots;
            return JSON.stringify(state);
        },
        composerHasChanges() {
            return this.composerBaseline !== null && this.$wire.composerOpen
                && this.composerSnapshot() !== this.composerBaseline;
        },
        mobileFiltersOpen: false,
        trackMinHeight: 264,
        confirmDeleteOpen: false,
        confirmDeleteType: '',
        confirmDeletePostId: '',
        dayPostsModalLoadingDate: '',
        dayPostsModalOpen: false,
        dayPostsModalDateLabel: '',
        dayPostsModalItems: [],
        draggingCalendarPost: null,
        dragTargetDate: '',
        dragTargetDateLabel: '',
        dragMoveModalOpen: false,
        dragMoveMode: 'keep',
        dragMoveTime: '',
        dragMoveSubmitting: false,
        recentDropDate: '',
        recentDropTimer: null,
        captionPickerOpen: false,
        saveCaptionOpen: false,
        captionPickerSearch: '',
        captionLibrary: @js($composerCaptionLibrary),
        localPreviewCaption: @js((string) ($composer['caption'] ?? '')),
        captionTypingTimer: null,
        captionTypingToken: 0,
        composerClosing: false,
        composerSavingAction: '',
        composerPublishBlocked: false,
        composerPublishBlockedMessage: '',
        localPreviewMediaItems: @js(collect((array) ($composer['media_items'] ?? []))->values()->all()),
        localScheduleSlots: @js(collect((array) ($composer['schedule_slots'] ?? []))->values()->all()),
        activePreviewAccountId: @js((string) (($composer['preview_account_id'] ?? '') ?: (($composer['account_ids'][0] ?? '') ?: ''))),
        localSelectedPreviewAccountIds: @js(collect($composer['account_ids'] ?? [])->map(fn ($id) => (string) $id)->values()->all()),
        previewOptionLimit: 8,
        previewOptionsExpanded: false,
        localSelectedPreviewOptions: @js($selectedComposerAccounts->map(fn ($account) => [
            'id' => (string) $account->id,
            'label' => (string) $account->display_name,
            'avatarUrl' => (string) ($account->avatar_url ?? ''),
            'initials' => (string) str($account->display_name)->substr(0, 2)->upper(),
            'providerKey' => (string) $account->provider_key,
            'providerIcon' => (string) data_get($providerCardsByKey->get((string) $account->provider_key, []), 'icon', ''),
            'providerColor' => (string) data_get($providerCardsByKey->get((string) $account->provider_key, []), 'color', ''),
            'providerToneSurface' => (string) publishing_provider_tone((string) $account->provider_key)['surface'],
            'providerToneText' => (string) publishing_provider_tone((string) $account->provider_key)['text'],
        ])->values()->all()),
        filteredCaptionLibrary() {
            const needle = String(this.captionPickerSearch || '').trim().toLowerCase();

            return (Array.isArray(this.captionLibrary) ? this.captionLibrary : []).filter((caption) => {
                const searchable = [
                    String(caption?.name || ''),
                    String(caption?.content || ''),
                    String(caption?.notes || ''),
                    ...(Array.isArray(caption?.tags) ? caption.tags : []),
                ].join(' ').toLowerCase();

                if (needle !== '' && !searchable.includes(needle)) {
                    return false;
                }

                return true;
            });
        },
        composerCaptionTextarea() {
            const textarea = document.getElementById('composer-caption-textarea');

            return textarea instanceof HTMLTextAreaElement ? textarea : null;
        },
        syncComposerCaptionTextarea(value) {
            const textarea = this.composerCaptionTextarea();

            if (!textarea) {
                return;
            }

            if (textarea.value !== value) {
                textarea.value = value;
                textarea.dispatchEvent(new Event('input', { bubbles: true }));
            }

            const caretPosition = textarea.value.length;

            if (typeof textarea.setSelectionRange === 'function') {
                textarea.setSelectionRange(caretPosition, caretPosition);
            }

            textarea.scrollTop = textarea.scrollHeight;
        },
        stopComposerCaptionTyping() {
            this.captionTypingToken += 1;

            if (this.captionTypingTimer) {
                clearTimeout(this.captionTypingTimer);
                this.captionTypingTimer = null;
            }
        },
        applyComposerCaption(value) {
            const nextCaption = String(value || '');

            this.stopComposerCaptionTyping();
            this.localPreviewCaption = nextCaption;
            this.syncComposerCaptionTextarea(nextCaption);
        },
        animateComposerCaption(value) {
            const nextCaption = String(value || '');
            const characters = Array.from(nextCaption);
            const textarea = this.composerCaptionTextarea();

            if (!textarea || characters.length === 0) {
                this.applyComposerCaption(nextCaption);
                return;
            }

            this.stopComposerCaptionTyping();
            this.localPreviewCaption = '';
            this.syncComposerCaptionTextarea('');

            const token = this.captionTypingToken + 1;
            let cursor = 0;
            const chunkSize = characters.length > 420 ? 4 : (characters.length > 180 ? 2 : 1);
            const delay = characters.length > 420 ? 18 : 26;

            textarea.focus();

            const step = () => {
                if (token !== this.captionTypingToken) {
                    return;
                }

                cursor = Math.min(characters.length, cursor + chunkSize);
                const partial = characters.slice(0, cursor).join('');

                this.localPreviewCaption = partial;
                this.syncComposerCaptionTextarea(partial);

                if (cursor < characters.length) {
                    this.captionTypingTimer = setTimeout(step, delay);
                    return;
                }

                this.captionTypingTimer = null;
            };

            this.captionTypingToken = token;
            this.captionTypingTimer = setTimeout(() => {
                if (token !== this.captionTypingToken) {
                    return;
                }

                step();
            }, 80);
        },
        selectComposerLibraryCaption(caption) {
            const nextCaption = String(caption?.content || '');

            this.animateComposerCaption(nextCaption);
            this.captionPickerOpen = false;
        },
        previewPrimaryMedia() {
            return Array.isArray(this.localPreviewMediaItems) && this.localPreviewMediaItems.length > 0
                ? (this.localPreviewMediaItems[0] || null)
                : null;
        },
        previewMediaUrl() {
            const media = this.previewPrimaryMedia();

            if (!media || typeof media !== 'object') {
                return '';
            }

            return String(media.previewUrl || media.url || '');
        },
        previewMediaIsVideo() {
            const media = this.previewPrimaryMedia();

            if (!media || typeof media !== 'object') {
                return false;
            }

            const mime = String(media.mimeType || '').toLowerCase();
            const category = String(media.category || '').toLowerCase();
            const extension = String(media.extension || '').toLowerCase();
            const preview = String(media.previewUrl || media.url || '').toLowerCase();

            return mime.startsWith('video/')
                || category === 'video'
                || ['mp4', 'mov', 'webm', 'm4v', 'avi', 'mkv'].includes(extension)
                || /\.(mp4|mov|webm|m4v|avi|mkv)(\?.*)?$/.test(preview);
        },
        defaultScheduleSlot(baseValue = null) {
            let date = null;

            if (baseValue) {
                const parsed = new Date(baseValue);
                if (!Number.isNaN(parsed.getTime())) {
                    date = parsed;
                    date.setDate(date.getDate() + 1);
                }
            }

            if (!date) {
                date = new Date();
                date.setMinutes(0, 0, 0);
                date.setHours(date.getHours() + 1);
            }

            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const hour = String(date.getHours()).padStart(2, '0');
            const minute = String(date.getMinutes()).padStart(2, '0');

            return `${year}-${month}-${day}T${hour}:${minute}`;
        },
        addLocalScheduleSlot() {
            const lastSlot = this.localScheduleSlots.length > 0 ? this.localScheduleSlots[this.localScheduleSlots.length - 1] : null;
            this.localScheduleSlots = [...this.localScheduleSlots, this.defaultScheduleSlot(lastSlot)];
        },
        visiblePreviewOptions() {
            if (this.previewOptionsExpanded) {
                return this.localSelectedPreviewOptions;
            }

            return this.localSelectedPreviewOptions.slice(0, this.previewOptionLimit);
        },
        hiddenPreviewOptionsCount() {
            return Math.max(0, this.localSelectedPreviewOptions.length - this.previewOptionLimit);
        },
        removeLocalScheduleSlot(index) {
            const nextSlots = this.localScheduleSlots.filter((_, slotIndex) => slotIndex !== index);
            this.localScheduleSlots = nextSlots.length > 0 ? nextSlots : [this.defaultScheduleSlot()];
        },
        repeatDayMap() {
            return {
                mon: 1,
                tue: 2,
                wed: 3,
                thu: 4,
                fri: 5,
                sat: 6,
                sun: 7,
            };
        },
        repeatCountPreview(repeatRule, repeatUntil, repeatDays = []) {
            const untilValue = String(repeatUntil || '').trim();

            if (!untilValue || repeatRule === 'none') {
                return this.localScheduleSlots.length;
            }

            const until = new Date(`${untilValue}T23:59:59`);
            if (Number.isNaN(until.getTime())) {
                return this.localScheduleSlots.length;
            }

            const selectedDays = (Array.isArray(repeatDays) ? repeatDays : [])
                .map((day) => this.repeatDayMap()[String(day || '').toLowerCase()] || null)
                .filter((day) => day !== null);

            let total = 0;

            for (const slotValue of this.localScheduleSlots) {
                const base = new Date(slotValue);

                if (Number.isNaN(base.getTime())) {
                    continue;
                }

                let cursor = new Date(base);
                let safety = 0;

                while (cursor <= until && safety < 366) {
                    const dayOfWeekIso = ((cursor.getDay() + 6) % 7) + 1;
                    const include = repeatRule === 'weekday'
                        ? dayOfWeekIso >= 1 && dayOfWeekIso <= 5
                        : selectedDays.includes(dayOfWeekIso);

                    if (include && cursor >= base) {
                        total += 1;
                    }

                    cursor.setDate(cursor.getDate() + 1);
                    safety += 1;
                }
            }

            return total;
        },
        recurringPreviewText(repeatRule, repeatUntil, repeatDays = []) {
            if (repeatRule === 'none') {
                return '';
            }

            const untilValue = String(repeatUntil || '').trim();
            if (!untilValue) {
                return '';
            }

            const until = new Date(`${untilValue}T12:00:00`);
            if (Number.isNaN(until.getTime())) {
                return '';
            }

            const dayLabels = {
                mon: 'Mon',
                tue: 'Tue',
                wed: 'Wed',
                thu: 'Thu',
                fri: 'Fri',
                sat: 'Sat',
                sun: 'Sun',
            };

            const repeatLabel = repeatRule === 'weekday'
                ? 'Repeats on weekdays'
                : `Repeats on ${(Array.isArray(repeatDays) ? repeatDays : []).map((day) => dayLabels[String(day || '').toLowerCase()] || '').filter(Boolean).join('/')}`;

            if (repeatLabel.trim() === 'Repeats on') {
                return '';
            }

            const formattedDate = until.toLocaleDateString('en-US', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
            });

            const slotLabel = this.localScheduleSlots.length === 1 ? 'slot' : 'slots';

            return `${repeatLabel} until ${formattedDate}. ${this.localScheduleSlots.length} base ${slotLabel}; the next repost is created only after each successful publish.`;
        },
        async syncComposerScheduleSlots() {
            if (this.$wire) {
                await this.$wire.set('composer.schedule_slots', this.localScheduleSlots, false);
            }
        },
        async syncComposerRepeatUntil() {
            if (!this.$wire) {
                return;
            }

            const input = document.querySelector('input[name=composer_repeat_until]');

            if (input instanceof HTMLInputElement) {
                await this.$wire.set('composer.repeat_until', input.value || '', true);
            }
        },
        async closeComposerLocal(discard = false) {
            if (this.composerClosing || this.composerSavingAction !== '' || !this.$wire) {
                return;
            }

            if (!discard && this.composerHasChanges()) {
                this.discardComposerOpen = true;
                this.$nextTick(() => this.$refs.keepEditing?.focus());
                return;
            }
            this.discardComposerOpen = false;
            this.composerClosing = true;

            try {
                await this.$wire.closeComposer();
            } finally {
                this.composerClosing = false;
            }
        },
        async saveComposerLocal(mode) {
            if (this.composerClosing || this.composerSavingAction !== '' || !this.$wire || (mode === 'scheduled' && this.composerPublishBlocked)) {
                if (mode === 'scheduled' && this.composerPublishBlocked && this.composerPublishBlockedMessage) {
                    window.dispatchEvent(new CustomEvent('app-toast', {
                        detail: {
                            type: 'error',
                            title: 'Publishing failed',
                            message: this.composerPublishBlockedMessage,
                        },
                    }));
                }

                return;
            }

            this.composerSavingAction = String(mode || 'scheduled');

            try {
                await this.syncComposerScheduleSlots();
                await this.syncComposerRepeatUntil();
                await this.$wire.saveComposer(mode);
            } catch (error) {
                console.error('Publishing composer save failed', error);

                const message = String(
                    error?.message ||
                    error?.detail?.message ||
                    'The publishing request could not be completed.'
                ).trim();

                window.dispatchEvent(new CustomEvent('app-toast', {
                    detail: {
                        type: 'error',
                        title: 'Publishing failed',
                        message,
                    },
                }));
            } finally {
                this.composerSavingAction = '';
            }
        },
        openDeleteDialog(type, postId) {
            this.confirmDeleteType = type;
            this.confirmDeletePostId = postId;
            this.confirmDeleteOpen = true;
        },
        closeDeleteDialog() {
            this.confirmDeleteOpen = false;
            this.confirmDeleteType = '';
            this.confirmDeletePostId = '';
        },
        openDayPostsModal(dateLabel, items) {
            this.dayPostsModalLoadingDate = '';
            this.dayPostsModalDateLabel = String(dateLabel || '');
            this.dayPostsModalItems = Array.isArray(items) ? items : [];
            this.dayPostsModalOpen = true;
        },
        closeDayPostsModal() {
            this.dayPostsModalLoadingDate = '';
            this.dayPostsModalOpen = false;
            this.dayPostsModalDateLabel = '';
            this.dayPostsModalItems = [];
        },
        startCalendarPostDrag(item) {
            if (!item || !item.can_edit) {
                this.draggingCalendarPost = null;
                return;
            }

            this.draggingCalendarPost = {
                id: String(item.post_id || ''),
                title: String(item.title || ''),
                time: String(item.time || ''),
                date: String(item.date || ''),
            };
        },
        clearCalendarPostDrag() {
            this.draggingCalendarPost = null;
            this.dragTargetDate = '';
            this.dragTargetDateLabel = '';
        },
        canDropCalendarPost(dayDate, isMoveTarget) {
            if (!isMoveTarget || !this.draggingCalendarPost) {
                return false;
            }

            return String(this.draggingCalendarPost.date || '') !== String(dayDate || '');
        },
        handleCalendarDayDragOver(event, dayDate, dayLabel, isMoveTarget) {
            if (!this.canDropCalendarPost(dayDate, isMoveTarget)) {
                return;
            }

            event.preventDefault();
            this.dragTargetDate = String(dayDate || '');
            this.dragTargetDateLabel = String(dayLabel || '');
        },
        handleCalendarDayDragLeave(dayDate) {
            if (String(this.dragTargetDate || '') === String(dayDate || '')) {
                this.dragTargetDate = '';
                this.dragTargetDateLabel = '';
            }
        },
        openMovePostDialog(dayDate, dayLabel, isMoveTarget) {
            if (!this.draggingCalendarPost || !this.canDropCalendarPost(dayDate, isMoveTarget)) {
                return;
            }

            this.dragTargetDate = String(dayDate || '');
            this.dragTargetDateLabel = String(dayLabel || '');
            this.dragMoveMode = 'keep';
            this.dragMoveTime = String(this.draggingCalendarPost.time || '').trim();
            this.dragMoveModalOpen = true;
        },
        pulseDroppedDay(dayDate) {
            this.recentDropDate = String(dayDate || '');

            if (this.recentDropTimer) {
                clearTimeout(this.recentDropTimer);
            }

            this.recentDropTimer = setTimeout(() => {
                this.recentDropDate = '';
                this.recentDropTimer = null;
            }, 850);
        },
        closeMovePostDialog(resetDrag = true) {
            this.dragMoveModalOpen = false;
            this.dragMoveMode = 'keep';
            this.dragMoveTime = '';
            this.dragMoveSubmitting = false;

            if (resetDrag) {
                this.clearCalendarPostDrag();
            }
        },
        async confirmMovePost() {
            if (this.dragMoveSubmitting || !this.$wire || !this.draggingCalendarPost || !this.dragTargetDate) {
                return;
            }

            this.dragMoveSubmitting = true;

            try {
                const completedDropDate = String(this.dragTargetDate || '');
                await this.$wire.movePostToDate(
                    this.draggingCalendarPost.id,
                    this.dragTargetDate,
                    this.dragMoveMode === 'change',
                    this.dragMoveMode === 'change' ? String(this.dragMoveTime || '').trim() : ''
                );
                this.closeMovePostDialog(true);
                this.pulseDroppedDay(completedDropDate);
            } finally {
                this.dragMoveSubmitting = false;
            }
        },
        setActivePreviewAccount(accountId, sync = true) {
            const nextId = String(accountId || '');

            this.activePreviewAccountId = nextId;

            if (sync && this.$wire) {
                this.$wire.set('composer.preview_account_id', nextId, false);
            }
        },
    }"
    x-on:publishing-composer-scroll-lock.window="$nextTick(() => { composerBaseline = composerSnapshot(); }); document.documentElement.style.overflow = 'hidden'; document.body.style.overflow = 'hidden';"
    x-on:publishing-composer-scroll-unlock.window="composerBaseline = null; discardComposerOpen = false; document.documentElement.style.overflow = ''; document.body.style.overflow = '';"
    x-on:beforeunload.window="if (composerHasChanges()) { $event.preventDefault(); $event.returnValue = ''; }"
    x-on:livewire:navigate.window="if (composerHasChanges() && !window.confirm(@js(__('Leave this page and discard your unsaved post changes?')))) { $event.preventDefault(); }"
    x-on:publishing-post-preview-scroll-lock.window="document.documentElement.style.overflow = 'hidden'; document.body.style.overflow = 'hidden';"
    x-on:publishing-post-preview-scroll-unlock.window="document.documentElement.style.overflow = ''; document.body.style.overflow = '';"
    x-on:publishing-caption-save-finished.window="saveCaptionOpen = false"
    x-on:tiktok-composer-policy.window="
        composerPublishBlocked = Boolean($event.detail?.blocked);
        composerPublishBlockedMessage = String($event.detail?.message || '');
    "
    x-on:media-browser:change.window="
        if ($event.detail?.model === 'composer.media_items') {
            localPreviewMediaItems = Array.isArray($event.detail.items) ? $event.detail.items : [];
        }
    "
    x-on:publishing-media-updated.window="localPreviewMediaItems = Array.isArray($event.detail?.items) ? $event.detail.items : []"
    x-on:channel-selector:change.window="
        if ($event.detail?.model === 'composer.account_ids') {
            localSelectedPreviewAccountIds = ($event.detail.selectedKeys || []).map(String);
            localSelectedPreviewOptions = ($event.detail.selectedOptions || []).map((option) => ({
                id: String(option.key || ''),
                label: String(option.label || ''),
                avatarUrl: String(option.avatarUrl || ''),
                initials: String((option.label || '').split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase()),
                providerKey: String(option.providerKey || ''),
                providerIcon: String(option.providerIcon || ''),
                providerColor: String(option.providerColor || ''),
                providerToneSurface: String(option.providerToneSurface || ''),
                providerToneText: String(option.providerToneText || ''),
            })).filter((option) => option.id !== '');
            previewOptionsExpanded = false;
            const changedKey = String($event.detail.changedKey || '');

            if (changedKey && localSelectedPreviewAccountIds.includes(changedKey)) {
                setActivePreviewAccount(changedKey);
            } else if (!localSelectedPreviewAccountIds.includes(String(activePreviewAccountId || ''))) {
                setActivePreviewAccount(localSelectedPreviewAccountIds[0] || '');
            }
        }
    "
    x-on:publishing-ai-caption-updated.window="
        const nextCaption = String($event.detail?.caption || '');
        if (!composerCaptionTextarea()) {
            localPreviewCaption = nextCaption;
        }
    "
    x-on:publishing-day-posts-modal-open.window="openDayPostsModal($event.detail?.dateLabel || '', $event.detail?.items || [])"
    x-on:publishing-ai-schedule-updated.window="localScheduleSlots = Array.isArray($event.detail?.slots) && $event.detail.slots.length ? $event.detail.slots : localScheduleSlots"
>
    <style>
        .calendar-toolbar{display:flex;align-items:center;gap:24px;min-height:64px}
        .calendar-toolbar-main{display:flex;align-items:center;gap:24px;flex:1}
        .calendar-heading{display:flex;align-items:center;gap:12px;min-width:210px}
        .calendar-heading-icon{display:grid;place-items:center;width:42px;height:42px;border-radius:12px;background:rgba(var(--theme-accent-rgb),.08);color:var(--theme-accent);font-size:18px}
        .calendar-heading p{font-size:12px;line-height:18px;color:var(--theme-muted-text-color);margin:0 0 2px}
        .calendar-heading h1{font-size:21px;font-weight:650;letter-spacing:-.5px;line-height:28px;color:var(--theme-header-text-color);margin:0}
        .calendar-date-nav{display:flex;align-items:center;gap:2px}
        .calendar-date-nav button{height:34px;padding:0 12px;border:0;border-radius:7px;background:transparent;color:var(--theme-muted-text-color);font-size:12px;font-weight:600}
        .calendar-date-nav button:hover{background:rgba(var(--theme-accent-rgb),.07);color:var(--theme-accent)}
        .calendar-view-switch{display:flex;align-items:center;padding:4px;gap:3px;border-radius:10px;background:var(--theme-surface-base);border:1px solid var(--theme-border-color)}
        .calendar-view-switch button{height:34px;padding:0 17px;border:0;border-radius:7px;background:transparent;color:var(--theme-muted-text-color);font-size:13px;font-weight:500}
        .calendar-view-switch button[aria-pressed="true"]{background:var(--sp-nav-active);color:var(--theme-accent);box-shadow:none;font-weight:650}
        .calendar-toolbar button:focus-visible{outline:2px solid var(--theme-accent);outline-offset:3px}
        @media(min-width:768px) and (max-width:1200px){.calendar-toolbar{flex-wrap:wrap;gap:12px}.calendar-toolbar-main{flex-basis:100%;justify-content:space-between}.calendar-view-switch{margin-bottom:2px}}
        @media(max-width:767px){.calendar-toolbar{display:none}}
    </style>
    <div class="flex flex-col border-b" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background: linear-gradient(180deg, var(--theme-surface-soft) 0%, var(--theme-surface-base) 18%, var(--theme-surface-subtle) 100%);">
        <section class="px-4 py-3 sm:px-5 xl:px-6" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-base) 86%, transparent);">
            <div class="mb-3 flex items-center justify-between gap-3 md:hidden">
                <div class="flex min-w-0 items-center gap-2.5">
                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-[0.95rem] shadow-[0_18px_45px_-28px_rgba(15,23,42,0.45)]" style="background: linear-gradient(135deg, rgba(var(--theme-accent-rgb), 0.18), rgba(var(--theme-accent-rgb), 0.08)); color: var(--theme-accent);">
                        <i class="fa-light fa-calendar-lines-pen text-base"></i>
                    </span>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold uppercase tracking-[0.24em]" style="color: var(--theme-muted-text-color);">{{ __('Publishing') }}</p>
                        <h1 class="truncate text-[1.1rem] font-semibold tracking-[-0.05em]" style="color: var(--theme-header-text-color);">{{ $calendarTitle }}</h1>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <x-ui.button
                        type="button"
                        variant="primary"
                        size="md"
                        class="shadow-[0_18px_40px_-30px_rgba(var(--theme-accent-rgb),0.35)]"
                        wire:click="openComposer"
                        wire:loading.attr="disabled"
                        wire:target="openComposer"
                    >
                        <span class="inline-flex items-center gap-2" wire:loading.remove wire:target="openComposer">
                            <i class="fa-light fa-square-plus"></i>
                            {{ __('New') }}
                        </span>
                        <span class="inline-flex items-center gap-2" wire:loading wire:target="openComposer">
                            <i class="fa-light fa-loader animate-spin"></i>
                            {{ __('Loading...') }}
                        </span>
                    </x-ui.button>

                    <div class="relative" x-cloak>
                        <button
                            type="button"
                            x-on:click="mobileFiltersOpen = !mobileFiltersOpen"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-[0.95rem] border shadow-[0_18px_40px_-30px_rgba(15,23,42,0.22)]"
                            style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-base) 92%, transparent); color: var(--theme-header-text-color);"
                        >
                            <i class="fa-light fa-filters"></i>
                        </button>

                        <div
                            x-show="mobileFiltersOpen"
                            x-transition.opacity.duration.150ms
                            x-on:click.outside="mobileFiltersOpen = false"
                            class="absolute right-0 top-full z-40 mt-2 w-[min(18rem,calc(100vw-1rem))] max-h-[76vh] overflow-y-auto rounded-[1rem] border p-3.5 shadow-[0_24px_60px_-34px_rgba(15,23,42,0.32)]"
                            style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-overlay) 98%, transparent);"
                        >
                            <div class="mb-2.5 flex items-center justify-between gap-3">
                                <div>
                                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Filters') }}</p>
                                    <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Search and narrow the publishing queue.') }}</p>
                                </div>
                                <button
                                    type="button"
                                    x-on:click="mobileFiltersOpen = false"
                                    class="inline-flex h-9 w-9 items-center justify-center rounded-[0.9rem] border transition hover:bg-slate-900/5"
                                    style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color);"
                                >
                                    <i class="fa-light fa-xmark"></i>
                                </button>
                            </div>

                            <div class="space-y-2.5">
                                <div class="flex h-10 items-center gap-2.5 rounded-[0.85rem] border px-3 shadow-[0_18px_40px_-30px_rgba(15,23,42,0.22)]" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-base) 92%, transparent);">
                                    <i class="fa-light fa-magnifying-glass text-sm" style="color: var(--theme-muted-text-color);"></i>
                                    <input
                                        wire:model.live.debounce.300ms="search"
                                        type="text"
                                        class="w-full border-0 bg-transparent p-0 text-sm focus:outline-none focus:ring-0"
                                        style="color: var(--theme-header-text-color);"
                                        placeholder="{{ __('Campaign, channel, network...') }}"
                                    >
                                </div>

                                <x-ui.select wire:model.live="providerFilter" class="[&>div>select]:h-10 [&>div>select]:rounded-[0.85rem] [&>div>select]:shadow-[0_18px_40px_-30px_rgba(15,23,42,0.22)]">
                                    <option value="all">{{ __('All networks') }}</option>
                                    @foreach ($providerFilters as $provider)
                                        <option value="{{ $provider['key'] }}">{{ $provider['label'] }}</option>
                                    @endforeach
                                </x-ui.select>

                                <x-ui.select wire:model.live="statusFilter" class="[&>div>select]:h-10 [&>div>select]:rounded-[0.85rem] [&>div>select]:shadow-[0_18px_40px_-30px_rgba(15,23,42,0.22)]">
                                    <option value="all">{{ __('All states') }}</option>
                                    <option value="pending">{{ __('Pending') }}</option>
                                    <option value="waiting_approve">{{ __('Waiting approve') }}</option>
                                    <option value="processing">{{ __('Processing') }}</option>
                                    <option value="published">{{ __('Published') }}</option>
                                    <option value="failed">{{ __('Failed') }}</option>
                                </x-ui.select>

                                <x-ui.select wire:model.live="campaignFilter" class="[&>div>select]:h-10 [&>div>select]:rounded-[0.85rem] [&>div>select]:shadow-[0_18px_40px_-30px_rgba(15,23,42,0.22)]">
                                    <option value="all">{{ __('All campaigns') }}</option>
                                    @foreach ($campaignFilters as $campaignFilterOption)
                                        <option value="{{ $campaignFilterOption->id }}">{{ $campaignFilterOption->name }}</option>
                                    @endforeach
                                </x-ui.select>

                                <x-ui.select wire:model.live="labelFilter" class="[&>div>select]:h-10 [&>div>select]:rounded-[0.85rem] [&>div>select]:shadow-[0_18px_40px_-30px_rgba(15,23,42,0.22)]">
                                    <option value="all">{{ __('All labels') }}</option>
                                    @foreach ($labelFilters as $labelFilterOption)
                                        <option value="{{ $labelFilterOption->id }}">{{ $labelFilterOption->name }}</option>
                                    @endforeach
                                </x-ui.select>

                                <button type="button" wire:click="clearFilters" x-on:click="mobileFiltersOpen = false" class="h-10 w-full rounded-[0.85rem] border px-3 text-sm font-semibold shadow-[0_18px_40px_-30px_rgba(15,23,42,0.22)] transition hover:bg-slate-900/5" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-header-text-color); background-color: color-mix(in srgb, var(--theme-surface-base) 92%, transparent);">
                                    {{ __('Reset') }}
                                </button>
                                <button
                                    type="button"
                                    x-on:click="mobileFiltersOpen = false; openDeleteDialog('filtered', '')"
                                    class="h-10 w-full rounded-[0.85rem] border px-3 text-sm font-semibold shadow-[0_18px_40px_-30px_rgba(239,68,68,0.24)] transition hover:bg-red-50/50 disabled:cursor-not-allowed disabled:opacity-60"
                                    style="border-color: rgba(239,68,68,0.45); color: #dc2626; background-color: rgba(254,242,242,0.72);"
                                    @disabled(($filteredVisibleCount ?? 0) < 1)
                                >
                                    {{ __('Delete (:count)', ['count' => (int) ($filteredVisibleCount ?? 0)]) }}
                                </button>
                            </div>
                        </div>
                    </div>

                    <x-ui.dropdown-menu align="right" width="auto">
                        <x-slot:trigger>
                            <button
                                type="button"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-[0.95rem] border shadow-[0_18px_40px_-30px_rgba(15,23,42,0.22)]"
                                style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-base) 92%, transparent); color: var(--theme-header-text-color);"
                            >
                                <i class="fa-light fa-sliders"></i>
                            </button>
                        </x-slot:trigger>

                        <x-ui.dropdown-menu-item icon="fa-light fa-grid-2" wire:click="setView('month')">
                            {{ __('Month view') }}
                        </x-ui.dropdown-menu-item>
                        <x-ui.dropdown-menu-item icon="fa-light fa-calendar-days" wire:click="setView('calendar')">
                            {{ __('Calendar view') }}
                        </x-ui.dropdown-menu-item>
                        <x-ui.dropdown-menu-item icon="fa-light fa-table-cells" wire:click="setView('week')">
                            {{ __('Week view') }}
                        </x-ui.dropdown-menu-item>
                        <x-ui.dropdown-menu-item icon="fa-light fa-chevron-left" wire:click="goPrevious">
                            {{ __('Previous') }}
                        </x-ui.dropdown-menu-item>
                        <x-ui.dropdown-menu-item icon="fa-light fa-chevron-right" wire:click="goNext">
                            {{ __('Next') }}
                        </x-ui.dropdown-menu-item>
                        <x-ui.dropdown-menu-item icon="fa-light fa-calendar-day" wire:click="goToday">
                            {{ __('Today') }}
                        </x-ui.dropdown-menu-item>
                        <x-ui.dropdown-menu-item icon="fa-light fa-tags" href="{{ route('portal.publishing.labels') }}" wire:navigate>
                            {{ __('Labels') }}
                        </x-ui.dropdown-menu-item>
                        <x-ui.dropdown-menu-item icon="fa-light fa-file-pen" href="{{ route('portal.publishing.drafts') }}" wire:navigate>
                            {{ __('Drafts') }}
                        </x-ui.dropdown-menu-item>
                        <x-ui.dropdown-menu-item icon="fa-light fa-list-check" href="{{ route('portal.publishing.queue') }}" wire:navigate>
                            {{ __('Queue') }}
                        </x-ui.dropdown-menu-item>
                        @if ($publishingCanApprovePosts)
                            <x-ui.dropdown-menu-item icon="fa-light fa-badge-check" href="{{ route('portal.publishing.approvals') }}" wire:navigate>
                                {{ __('Approvals') }}
                            </x-ui.dropdown-menu-item>
                        @endif
                        <x-ui.dropdown-menu-item icon="fa-light fa-bullhorn" href="{{ route('portal.publishing.campaigns') }}" wire:navigate>
                            {{ __('Campaigns') }}
                        </x-ui.dropdown-menu-item>
                        <x-ui.dropdown-menu-item icon="fa-light fa-rotate-left" wire:click="clearFilters">
                            {{ __('Reset filters') }}
                        </x-ui.dropdown-menu-item>
                        <x-ui.dropdown-menu-item icon="fa-light fa-trash-can-list" x-on:click="openDeleteDialog('filtered', '')">
                            {{ __('Delete filtered (:count)', ['count' => (int) ($filteredVisibleCount ?? 0)]) }}
                        </x-ui.dropdown-menu-item>
                    </x-ui.dropdown-menu>
                </div>
            </div>

            <div class="calendar-toolbar">
                <div class="calendar-toolbar-main">
                    <div class="calendar-heading">
                        <span class="calendar-heading-icon"><i class="fa-light fa-calendar-lines-pen" aria-hidden="true"></i></span>
                        <div><p>{{ __('Publishing calendar') }}</p><h1>{{ $calendarTitle }}</h1></div>
                    </div>
                    <div class="calendar-date-nav" aria-label="{{ __('Calendar navigation') }}">
                        <button type="button" wire:click="goPrevious" aria-label="{{ __('Previous period') }}"><i class="fa-light fa-chevron-left" aria-hidden="true"></i></button>
                        <button type="button" wire:click="goToday">{{ __('Today') }}</button>
                        <button type="button" wire:click="goNext" aria-label="{{ __('Next period') }}"><i class="fa-light fa-chevron-right" aria-hidden="true"></i></button>
                    </div>
                </div>
                <div class="calendar-view-switch" aria-label="{{ __('Calendar view') }}">
                    @foreach(['month'=>__('Month'),'calendar'=>__('Calendar'),'week'=>__('Week')] as $view=>$label)
                        <button type="button" wire:click="setView('{{ $view }}')" aria-pressed="{{ $calendarView === $view ? 'true' : 'false' }}">{{ $label }}</button>
                    @endforeach
                </div>
                <div class="hidden shrink-0 items-center gap-2 md:ml-auto md:flex">
                    <x-ui.button
                        type="button"
                        variant="primary"
                        size="md"
                        class="h-10 rounded-[0.95rem] px-4 shadow-[0_18px_40px_-30px_rgba(var(--theme-accent-rgb),0.35)]"
                        wire:click="openComposer"
                        wire:loading.attr="disabled"
                        wire:target="openComposer"
                    >
                            <span class="inline-flex items-center gap-2" wire:loading.remove wire:target="openComposer">
                                <i class="fa-light fa-square-plus"></i>
                                {{ __('New Post') }}
                            </span>
                            <span class="inline-flex items-center gap-2" wire:loading wire:target="openComposer">
                                <i class="fa-light fa-loader animate-spin"></i>
                                {{ __('Loading...') }}
                            </span>
                        </x-ui.button>
                        <div class="relative" x-cloak>
                            <button
                                type="button"
                                x-on:click="mobileFiltersOpen = !mobileFiltersOpen"
                                class="inline-flex h-10 items-center gap-2 rounded-[0.95rem] border px-4 text-sm font-semibold shadow-[0_18px_40px_-30px_rgba(15,23,42,0.22)]"
                                style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-base) 92%, transparent); color: var(--theme-header-text-color);"
                            >
                                <i class="fa-light fa-filters"></i>
                                {{ __('Filters') }}
                            </button>

                        <div
                            x-show="mobileFiltersOpen"
                            x-transition.opacity.duration.150ms
                            x-on:click.outside="mobileFiltersOpen = false"
                            class="absolute right-0 top-full z-40 mt-2 w-[min(18rem,calc(100vw-1rem))] max-h-[76vh] overflow-y-auto rounded-[1rem] border p-3.5 shadow-[0_24px_60px_-34px_rgba(15,23,42,0.32)]"
                            style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-overlay) 98%, transparent);"
                        >
                            <div class="mb-2.5 flex items-center justify-between gap-3">
                                    <div>
                                        <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Filters') }}</p>
                                        <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Search and narrow the publishing queue.') }}</p>
                                    </div>
                                    <button
                                        type="button"
                                        x-on:click="mobileFiltersOpen = false"
                                        class="inline-flex h-9 w-9 items-center justify-center rounded-[0.9rem] border transition hover:bg-slate-900/5"
                                        style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color);"
                                    >
                                        <i class="fa-light fa-xmark"></i>
                                    </button>
                                </div>

                                <div class="space-y-2.5">
                                    <div class="flex h-10 items-center gap-2.5 rounded-[0.85rem] border px-3 shadow-[0_18px_40px_-30px_rgba(15,23,42,0.22)]" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-base) 92%, transparent);">
                                        <i class="fa-light fa-magnifying-glass text-sm" style="color: var(--theme-muted-text-color);"></i>
                                        <input
                                            wire:model.live.debounce.300ms="search"
                                            type="text"
                                            class="w-full border-0 bg-transparent p-0 text-sm focus:outline-none focus:ring-0"
                                            style="color: var(--theme-header-text-color);"
                                            placeholder="{{ __('Campaign, channel, network...') }}"
                                        >
                                    </div>

                                    <x-ui.select wire:model.live="providerFilter" class="[&>div>select]:h-10 [&>div>select]:rounded-[0.85rem] [&>div>select]:shadow-[0_18px_40px_-30px_rgba(15,23,42,0.22)]">
                                        <option value="all">{{ __('All networks') }}</option>
                                        @foreach ($providerFilters as $provider)
                                            <option value="{{ $provider['key'] }}">{{ $provider['label'] }}</option>
                                        @endforeach
                                    </x-ui.select>

                                    <x-ui.select wire:model.live="statusFilter" class="[&>div>select]:h-10 [&>div>select]:rounded-[0.85rem] [&>div>select]:shadow-[0_18px_40px_-30px_rgba(15,23,42,0.22)]">
                                        <option value="all">{{ __('All states') }}</option>
                                        <option value="pending">{{ __('Pending') }}</option>
                                        <option value="waiting_approve">{{ __('Waiting approve') }}</option>
                                        <option value="processing">{{ __('Processing') }}</option>
                                        <option value="published">{{ __('Published') }}</option>
                                        <option value="failed">{{ __('Failed') }}</option>
                                    </x-ui.select>

                                    <x-ui.select wire:model.live="campaignFilter" class="[&>div>select]:h-10 [&>div>select]:rounded-[0.85rem] [&>div>select]:shadow-[0_18px_40px_-30px_rgba(15,23,42,0.22)]">
                                        <option value="all">{{ __('All campaigns') }}</option>
                                        @foreach ($campaignFilters as $campaignFilterOption)
                                            <option value="{{ $campaignFilterOption->id }}">{{ $campaignFilterOption->name }}</option>
                                        @endforeach
                                    </x-ui.select>

                                    <x-ui.select wire:model.live="labelFilter" class="[&>div>select]:h-10 [&>div>select]:rounded-[0.85rem] [&>div>select]:shadow-[0_18px_40px_-30px_rgba(15,23,42,0.22)]">
                                        <option value="all">{{ __('All labels') }}</option>
                                        @foreach ($labelFilters as $labelFilterOption)
                                            <option value="{{ $labelFilterOption->id }}">{{ $labelFilterOption->name }}</option>
                                        @endforeach
                                    </x-ui.select>

                                    <button type="button" wire:click="clearFilters" x-on:click="mobileFiltersOpen = false" class="h-10 w-full rounded-[0.85rem] border px-3 text-sm font-semibold shadow-[0_18px_40px_-30px_rgba(15,23,42,0.22)] transition hover:bg-slate-900/5" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-header-text-color); background-color: color-mix(in srgb, var(--theme-surface-base) 92%, transparent);">
                                        {{ __('Reset') }}
                                    </button>
                                    <button
                                        type="button"
                                        x-on:click="mobileFiltersOpen = false; openDeleteDialog('filtered', '')"
                                        class="h-10 w-full rounded-[0.85rem] border px-3 text-sm font-semibold shadow-[0_18px_40px_-30px_rgba(239,68,68,0.24)] transition hover:bg-red-50/50 disabled:cursor-not-allowed disabled:opacity-60"
                                        style="border-color: rgba(239,68,68,0.45); color: #dc2626; background-color: rgba(254,242,242,0.72);"
                                        @disabled(($filteredVisibleCount ?? 0) < 1)
                                    >
                                        {{ __('Delete (:count)', ['count' => (int) ($filteredVisibleCount ?? 0)]) }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </section>

        <div class="flex flex-col">
            <section
                x-data="{
                    syncing: false,
                    resizeHandler: null,
                    resizeObserver: null,
                    syncFrame: null,
                    lastCalendarKey: '',
                    isPointerPanning: false,
                    pointerPanStartX: 0,
                    pointerPanStartScrollLeft: 0,
                    shouldStartPointerPan(event) {
                        if (!event || event.button !== 0) {
                            return false;
                        }

                        const target = event.target;

                        if (!(target instanceof HTMLElement)) {
                            return false;
                        }

                        if (target.closest('a, button, input, textarea, select, option, label, summary, [role=button], [draggable=true], [data-no-pan]')) {
                            return false;
                        }

                        const scroller = this.$refs.mainScroller;

                        return !!scroller && scroller.scrollWidth > scroller.clientWidth;
                    },
                    startPointerPan(event) {
                        if (!this.shouldStartPointerPan(event)) {
                            return;
                        }

                        this.isPointerPanning = true;
                        this.pointerPanStartX = event.clientX;
                        this.pointerPanStartScrollLeft = this.$refs.mainScroller.scrollLeft;

                        document.body.style.cursor = 'grabbing';
                        document.body.style.userSelect = 'none';
                        event.preventDefault();
                    },
                    movePointerPan(event) {
                        if (!this.isPointerPanning || !this.$refs.mainScroller) {
                            return;
                        }

                        this.$refs.mainScroller.scrollLeft = this.pointerPanStartScrollLeft - (event.clientX - this.pointerPanStartX);
                    },
                    stopPointerPan() {
                        if (!this.isPointerPanning) {
                            return;
                        }

                        this.isPointerPanning = false;
                        document.body.style.removeProperty('cursor');
                        document.body.style.removeProperty('user-select');
                    },
                    sync(source, target) {
                        if (this.syncing || !source || !target) {
                            return;
                        }

                        this.syncing = true;

                        if (this.syncFrame) {
                            cancelAnimationFrame(this.syncFrame);
                        }

                        this.syncFrame = requestAnimationFrame(() => {
                            target.scrollLeft = source.scrollLeft;
                            this.syncing = false;
                            this.syncFrame = null;
                        });
                    },
                    syncWidth() {
                        if (!this.$refs.mainTrack || !this.$refs.bottomTrack) {
                            return;
                        }

                        this.$refs.bottomTrack.style.width = `${this.$refs.mainTrack.scrollWidth}px`;
                    },
                    syncHeight() {
                        const scroller = this.$refs.mainScroller;

                        if (!scroller) {
                            this.trackMinHeight = 264;
                            return;
                        }

                        const rect = scroller.getBoundingClientRect();
                        this.trackMinHeight = Math.max(264, Math.floor(window.innerHeight - rect.top - 1));
                    },
                    centerToday() {
                        const scroller = this.$refs.mainScroller;
                        const todayColumn = this.$refs.todayColumn;

                        if (!scroller || !todayColumn) {
                            return;
                        }

                        if ($wire.calendarView !== 'month') {
                            return;
                        }

                        const targetLeft = todayColumn.offsetLeft + (todayColumn.offsetWidth / 2) - (scroller.clientWidth / 2);
                        const maxLeft = Math.max(scroller.scrollWidth - scroller.clientWidth, 0);

                        scroller.scrollLeft = Math.max(0, Math.min(targetLeft, maxLeft));

                        if (this.$refs.bottomScroller) {
                            this.$refs.bottomScroller.scrollLeft = scroller.scrollLeft;
                        }
                    },
                    init() {
                        this.$nextTick(() => {
                            this.lastCalendarKey = `${$wire.calendarView}:${$wire.calendarTitle}`;
                            this.syncWidth();
                            this.syncHeight();
                            this.centerToday();

                            if (window.ResizeObserver) {
                                this.resizeObserver = new ResizeObserver(() => {
                                    this.syncWidth();
                                    this.syncHeight();
                                });
                                if (this.$refs.mainTrack) {
                                    this.resizeObserver.observe(this.$refs.mainTrack);
                                }
                                this.resizeObserver.observe(this.$el);
                            }

                            this.resizeHandler = () => {
                                this.syncWidth();
                                this.syncHeight();
                            };

                            window.addEventListener('resize', this.resizeHandler);
                        });
                    },
                    destroy() {
                        if (this.syncFrame) {
                            cancelAnimationFrame(this.syncFrame);
                        }

                        if (this.resizeObserver) {
                            this.resizeObserver.disconnect();
                        }

                        if (this.resizeHandler) {
                            window.removeEventListener('resize', this.resizeHandler);
                        }

                        this.stopPointerPan();
                    },
                }"
                x-effect="
                    const nextCalendarKey = `${$wire.calendarView}:${$wire.calendarTitle}`;

                    if (lastCalendarKey !== nextCalendarKey) {
                        lastCalendarKey = nextCalendarKey;
                        $nextTick(() => {
                            syncWidth();
                            syncHeight();
                            centerToday();
                        });
                    }
                "
                class="relative min-w-0 border-r"
                style="border-color: rgba(var(--theme-border-color-rgb), 0.68);"
            >
                @if ($calendarView === 'calendar')
                <div wire:key="publishing-calendar-grid-{{ $calendarTitle }}" class="overflow-x-auto overflow-y-visible [scrollbar-gutter:stable]">
                    <div class="min-w-[70rem] xl:min-w-full">
                        <div class="grid border-b text-center" style="grid-template-columns: repeat(7, minmax(0, 1fr)); border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-overlay) 94%, transparent);">
                            @foreach ([__('Sunday'), __('Monday'), __('Tuesday'), __('Wednesday'), __('Thursday'), __('Friday'), __('Saturday')] as $weekday)
                                <div class="border-r px-3 py-2 text-xs font-semibold last:border-r-0" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-header-text-color);">
                                    {{ $weekday }}
                                </div>
                            @endforeach
                        </div>

                        <div class="grid auto-rows-[minmax(8.25rem,auto)]" style="grid-template-columns: repeat(7, minmax(0, 1fr));">
                            @foreach ($days as $day)
                                @php
                                    $items = $itemsByDate->get($day['date'], collect());
                                    $visibleItems = $items->take(3);
                                    $remainingItemsCount = max(0, $items->count() - 3);
                                @endphp
                                <div
                                    @if($day['is_today']) x-ref="todayColumn" @endif
                                    class="group/day relative min-h-[8.25rem] border-b border-r p-2 last:border-r-0"
                                    style="border-color: rgba(var(--theme-border-color-rgb), 0.58); background-color: {{ $day['is_today'] ? 'color-mix(in srgb, var(--theme-accent) 3%, var(--theme-surface-base))' : ($day['is_current_period'] ? 'var(--theme-surface-base)' : 'rgba(var(--theme-border-color-rgb), 0.035)') }};"
                                    x-on:dragover="handleCalendarDayDragOver($event, '{{ $day['date'] }}', '{{ $day['long_label'] }}', {{ $day['is_move_target'] ? 'true' : 'false' }})"
                                    x-on:dragleave="handleCalendarDayDragLeave('{{ $day['date'] }}')"
                                    x-on:drop.prevent="openMovePostDialog('{{ $day['date'] }}', '{{ $day['long_label'] }}', {{ $day['is_move_target'] ? 'true' : 'false' }})"
                                    x-bind:class="{
                                        'ring-2 ring-[rgba(var(--theme-accent-rgb),0.18)] ring-inset': canDropCalendarPost('{{ $day['date'] }}', {{ $day['is_move_target'] ? 'true' : 'false' }}) && dragTargetDate === '{{ $day['date'] }}',
                                        'animate-pulse': recentDropDate === '{{ $day['date'] }}',
                                    }"
                                >
                                    <div class="mb-2 flex items-center justify-between gap-2">
                                        <span class="text-sm font-semibold" style="color: {{ $day['is_current_period'] ? 'var(--theme-header-text-color)' : 'var(--theme-muted-text-color)' }};">{{ $day['day_number'] }}</span>
                                        @if ($day['is_today'])
                                            <span class="rounded-full px-2 py-0.5 text-[9px] font-semibold uppercase tracking-[0.12em]" style="background-color: rgba(var(--theme-accent-rgb), 0.12); color: var(--theme-accent);">{{ __('Today') }}</span>
                                        @endif
                                    </div>

                                    <div class="space-y-1.5">
                                        @foreach ($visibleItems as $item)
                                            @php
                                                $status = $statusMeta[$item['status_key']] ?? $statusMeta['pending'];
                                                $displayTime = \Carbon\Carbon::parse((string) $item['datetime'])->format('g:i A');
                                                $calendarMediaItems = collect($item['media_items'] ?? [])->values();
                                                $primaryCalendarMedia = $calendarMediaItems->first();
                                                $primaryCalendarPreview = is_array($primaryCalendarMedia) ? trim((string) ($primaryCalendarMedia['previewUrl'] ?? $primaryCalendarMedia['preview_url'] ?? $primaryCalendarMedia['url'] ?? '')) : '';
                                                $primaryCalendarMime = strtolower((string) (is_array($primaryCalendarMedia) ? ($primaryCalendarMedia['mimeType'] ?? '') : ''));
                                                $primaryCalendarCategory = strtolower((string) (is_array($primaryCalendarMedia) ? ($primaryCalendarMedia['category'] ?? '') : ''));
                                                $primaryCalendarExtension = strtolower((string) (is_array($primaryCalendarMedia) ? ($primaryCalendarMedia['extension'] ?? '') : ''));
                                                $calendarHasMedia = $primaryCalendarPreview !== '';
                                                $calendarIsVideo = $calendarHasMedia && (
                                                    str_starts_with($primaryCalendarMime, 'video/')
                                                    || $primaryCalendarCategory === 'video'
                                                    || in_array($primaryCalendarExtension, ['mp4', 'mov', 'webm', 'm4v', 'avi', 'mkv'], true)
                                                    || preg_match('/\.(mp4|mov|webm|m4v|avi|mkv)(\?.*)?$/', strtolower($primaryCalendarPreview))
                                                );
                                                $calendarTags = collect($item['tags'] ?? []);
                                                if ($calendarTags->isEmpty() && !empty($item['campaign'])) {
                                                    $calendarTags = collect([$item['campaign']]);
                                                }
                                                if ($calendarTags->isEmpty() && !empty($item['source_label'])) {
                                                    $calendarTags = collect([$item['source_label']]);
                                                }
                                                $calendarTagPalette = [
                                                    ['bg' => 'rgba(22, 163, 74, 0.14)', 'text' => '#15803d'],
                                                    ['bg' => 'rgba(239, 68, 68, 0.13)', 'text' => '#dc2626'],
                                                    ['bg' => 'rgba(37, 99, 235, 0.13)', 'text' => '#2563eb'],
                                                    ['bg' => 'rgba(217, 119, 6, 0.15)', 'text' => '#b45309'],
                                                ];
                                                $dragPayload = json_encode([
                                                    'post_id' => (string) $item['post_id'],
                                                    'title' => (string) $item['title'],
                                                    'time' => (string) $item['time'],
                                                    'date' => (string) $item['date'],
                                                    'can_edit' => !empty($item['can_edit']),
                                                ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
                                            @endphp
                                            <article
                                                class="group/item relative z-0 w-full overflow-visible rounded-[0.85rem] border bg-white p-1.5 text-left text-[10px] shadow-[0_10px_24px_-24px_rgba(15,23,42,0.32)] transition duration-150 hover:z-50 hover:-translate-y-[1px] hover:shadow-[0_20px_38px_-28px_rgba(15,23,42,0.42)] focus-within:z-50 focus-within:ring-2 focus-within:ring-[rgba(var(--theme-accent-rgb),0.22)] dark:bg-slate-950 {{ !empty($item['can_edit']) ? 'cursor-grab active:cursor-grabbing' : '' }}"
                                                style="border-color: rgba(var(--theme-border-color-rgb), 0.72);"
                                                draggable="{{ !empty($item['can_edit']) ? 'true' : 'false' }}"
                                                data-drag-payload='{{ $dragPayload }}'
                                                x-on:dragstart="startCalendarPostDrag(JSON.parse($el.dataset.dragPayload || '{}'))"
                                                x-on:dragend="if (!dragMoveModalOpen) clearCalendarPostDrag()"
                                                title="{{ $item['title'] }}"
                                            >
                                                <span class="flex gap-1.5">
                                                    @if ($calendarHasMedia)
                                                        <button
                                                            type="button"
                                                            class="relative h-8 w-8 shrink-0 overflow-hidden rounded-[0.58rem] border transition hover:brightness-95"
                                                            style="border-color: rgba(var(--theme-border-color-rgb), 0.65); background-color: rgba(var(--theme-border-color-rgb), 0.06);"
                                                            wire:click="openPostPreview('{{ $item['post_id'] }}')"
                                                        >
                                                            @if ($calendarIsVideo)
                                                                <video src="{{ $primaryCalendarPreview }}" muted playsinline preload="metadata" class="h-full w-full object-cover"></video>
                                                                <span class="absolute inset-0 flex items-center justify-center bg-slate-950/18">
                                                                    <span class="inline-flex h-4 w-4 items-center justify-center rounded-full bg-white/90 text-[7px] text-slate-900 shadow-sm">
                                                                        <i class="fa-solid fa-play ml-0.5"></i>
                                                                    </span>
                                                                </span>
                                                            @else
                                                                <img src="{{ $primaryCalendarPreview }}" alt="{{ $item['title'] }}" class="h-full w-full object-cover" loading="lazy" decoding="async">
                                                            @endif

                                                            @if ($calendarMediaItems->count() > 1)
                                                                <span class="absolute bottom-0.5 right-0.5 rounded-full px-1 text-[7px] font-bold leading-3 text-white" style="background-color: rgba(15,23,42,0.72);">
                                                                    +{{ $calendarMediaItems->count() - 1 }}
                                                                </span>
                                                            @endif
                                                        </button>
                                                    @endif

                                                    <span class="min-w-0 flex-1">
                                                        <span class="flex items-start justify-between gap-2">
                                                            <span class="min-w-0 flex-1 text-[10.5px] font-semibold leading-4" style="color: var(--theme-header-text-color); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">{{ $item['title'] }}</span>
                                                            <span class="shrink-0 pt-0.5 text-[8.5px] font-semibold tabular-nums" style="color: var(--theme-muted-text-color);">{{ $displayTime }}</span>
                                                        </span>
                                                        <span class="mt-0.5 flex items-center gap-1">
                                                            <span class="h-1.5 w-1.5 shrink-0 rounded-full" style="background-color: {{ $status['text'] }};"></span>
                                                            <span class="truncate text-[8.5px] font-medium" style="color: var(--theme-muted-text-color);">{{ $item['status'] }}</span>
                                                            @if ($calendarIsVideo)
                                                                <span class="rounded-full px-1 py-0.5 text-[7px] font-bold leading-none" style="background-color: rgba(239, 68, 68, 0.12); color: #dc2626;">{{ __('Video') }}</span>
                                                            @elseif ($calendarHasMedia)
                                                                <span class="rounded-full px-1 py-0.5 text-[7px] font-bold leading-none" style="background-color: rgba(37, 99, 235, 0.12); color: #2563eb;">{{ __('Image') }}</span>
                                                            @endif
                                                        </span>
                                                    </span>
                                                </span>
                                                <span class="mt-1 flex min-w-0 flex-nowrap gap-1 overflow-hidden">
                                                    @foreach ($calendarTags->take(2) as $tag)
                                                        @php
                                                            $tagTone = $calendarTagPalette[$loop->index % count($calendarTagPalette)];
                                                        @endphp
                                                        <span class="max-w-full truncate rounded-full px-1.5 py-0.5 text-[8px] font-semibold leading-none" style="background-color: {{ $tagTone['bg'] }}; color: {{ $tagTone['text'] }};">
                                                            {{ \Illuminate\Support\Str::limit((string) $tag, 16) }}
                                                        </span>
                                                    @endforeach
                                                </span>
                                                <span class="mt-1 flex items-center gap-1">
                                                    <span class="inline-flex h-3.5 w-3.5 items-center justify-center rounded-full text-[8px]" style="background-color: color-mix(in srgb, {{ $item['provider_color'] }} 14%, var(--theme-surface-overlay)); color: {{ $item['provider_color'] }};">
                                                        <i class="{{ $item['provider_icon'] }}"></i>
                                                    </span>
                                                    @if (!empty($item['source_label']))
                                                        <span class="inline-flex h-3.5 w-3.5 items-center justify-center rounded-full text-[8px]" style="background-color: {{ $item['source_surface'] }}; color: {{ $item['source_text'] }};">
                                                            <i class="fa-light fa-bolt"></i>
                                                        </span>
                                                    @endif
                                                </span>
                                                <span class="mt-1.5 flex items-center justify-between gap-1 border-t pt-1.5" style="border-color: rgba(var(--theme-border-color-rgb), 0.42);">
                                                    <button
                                                        type="button"
                                                        class="inline-flex h-6 w-6 items-center justify-center rounded-[0.55rem] border text-[10px] transition hover:bg-slate-900/5"
                                                        style="border-color: rgba(var(--theme-border-color-rgb), 0.62); color: var(--theme-muted-text-color);"
                                                        wire:click="openPostPreview('{{ $item['post_id'] }}')"
                                                        title="{{ __('Preview post') }}"
                                                    >
                                                        <i class="fa-light fa-eye"></i>
                                                    </button>

                                                    <span class="flex items-center gap-1">
                                                        @if (!empty($item['post_url']))
                                                            <a
                                                                href="{{ $item['post_url'] }}"
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                class="inline-flex h-6 items-center rounded-[0.55rem] border px-2 text-[9px] font-semibold transition hover:bg-slate-900/5"
                                                                style="border-color: rgba(var(--theme-border-color-rgb), 0.62); color: var(--theme-header-text-color);"
                                                                title="{{ __('Open published post') }}"
                                                            >
                                                                {{ __('View') }}
                                                            </a>
                                                        @elseif (!empty($item['can_edit']))
                                                            <button
                                                                type="button"
                                                                class="inline-flex h-6 items-center rounded-[0.55rem] border px-2 text-[9px] font-semibold transition hover:bg-slate-900/5"
                                                                style="border-color: rgba(var(--theme-border-color-rgb), 0.62); color: var(--theme-header-text-color);"
                                                                wire:click="editPost('{{ $item['post_id'] }}')"
                                                            >
                                                                {{ __('Edit') }}
                                                            </button>
                                                        @else
                                                            <span
                                                                class="inline-flex h-6 items-center rounded-[0.55rem] border px-2 text-[9px] font-semibold"
                                                                style="border-color: rgba(var(--theme-border-color-rgb), 0.62); color: var(--theme-muted-text-color);"
                                                            >
                                                                {{ __('Preview') }}
                                                            </span>
                                                        @endif

                                                        <x-ui.dropdown-menu align="right" width="auto">
                                                            <x-slot:trigger>
                                                                <button
                                                                    type="button"
                                                                    class="inline-flex h-6 w-6 items-center justify-center rounded-[0.55rem] border text-[10px] transition hover:bg-slate-900/5"
                                                                    style="border-color: rgba(var(--theme-border-color-rgb), 0.62); color: var(--theme-muted-text-color);"
                                                                    title="{{ __('More actions') }}"
                                                                >
                                                                    <i class="fa-light fa-ellipsis"></i>
                                                                </button>
                                                            </x-slot:trigger>

                                                            @if (!empty($item['can_edit']))
                                                                <x-ui.dropdown-menu-item icon="fa-light fa-pen-to-square" wire:click="editPost('{{ $item['post_id'] }}')">
                                                                    {{ __('Edit post') }}
                                                                </x-ui.dropdown-menu-item>
                                                            @endif

                                                            <x-ui.dropdown-menu-item icon="fa-light fa-eye" wire:click="openPostPreview('{{ $item['post_id'] }}')">
                                                                {{ __('Preview post') }}
                                                            </x-ui.dropdown-menu-item>

                                                            <x-ui.dropdown-menu-item icon="fa-light fa-copy" wire:click="copyPost('{{ $item['post_id'] }}')">
                                                                {{ __('Copy post') }}
                                                            </x-ui.dropdown-menu-item>

                                                            @if (!empty($item['can_delete_remote']))
                                                                <x-ui.dropdown-menu-item
                                                                    icon="fa-light fa-trash-arrow-up"
                                                                    variant="danger"
                                                                    :close="false"
                                                                    x-on:click.stop="open = false; openDeleteDialog('remote', '{{ $item['post_id'] }}')"
                                                                >
                                                                    {{ __('Delete on social network') }}
                                                                </x-ui.dropdown-menu-item>
                                                            @endif

                                                            <x-ui.dropdown-menu-item
                                                                icon="fa-light fa-trash-can"
                                                                variant="danger"
                                                                :close="false"
                                                                x-on:click.stop="open = false; openDeleteDialog('local', '{{ $item['post_id'] }}')"
                                                            >
                                                                {{ __('Delete post') }}
                                                            </x-ui.dropdown-menu-item>
                                                        </x-ui.dropdown-menu>
                                                    </span>
                                                </span>
                                            </article>
                                        @endforeach

                                        @if ($remainingItemsCount > 0)
                                            <button
                                                type="button"
                                                class="flex w-full items-center justify-between rounded-[0.55rem] border px-2 py-1.5 text-left text-[11px] font-semibold transition hover:-translate-y-[1px] hover:bg-slate-900/5"
                                                style="border-color: rgba(var(--theme-border-color-rgb), 0.58); background-color: rgba(var(--theme-border-color-rgb), 0.035); color: var(--theme-muted-text-color);"
                                                wire:click="openDayPosts('{{ $day['date'] }}')"
                                                x-on:click="dayPostsModalLoadingDate = '{{ $day['date'] }}'"
                                                data-no-loading
                                            >
                                                <span>{{ trans_choice('+ :count more item|+ :count more items', $remainingItemsCount, ['count' => $remainingItemsCount]) }}</span>
                                                <i class="fa-light fa-chevron-right text-[10px]"></i>
                                            </button>
                                        @endif
                                    </div>

                                    @if ($items->isEmpty() && $day['can_compose'])
                                        <button
                                            type="button"
                                            class="absolute inset-x-2 bottom-2 hidden rounded-[0.55rem] border px-2 py-1.5 text-xs font-semibold transition hover:bg-slate-900/5 group-hover/day:block"
                                            style="border-color: rgba(var(--theme-border-color-rgb), 0.58); background-color: color-mix(in srgb, var(--theme-surface-overlay) 96%, transparent); color: var(--theme-header-text-color);"
                                            wire:click="openComposer('{{ $day['date'] }}')"
                                        >
                                            <i class="fa-light fa-square-plus mr-1"></i>
                                            {{ __('Compose') }}
                                        </button>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                @else
                <div
                    wire:key="publishing-timeline-{{ $calendarView }}-{{ $calendarTitle }}"
                    x-ref="mainScroller"
                    x-on:scroll="sync($refs.mainScroller, $refs.bottomScroller)"
                    x-on:mousedown="startPointerPan($event)"
                    x-on:mousemove.window="movePointerPan($event)"
                    x-on:mouseup.window="stopPointerPan()"
                    x-on:mouseleave.window="stopPointerPan()"
                    x-on:dragstart="stopPointerPan()"
                    x-bind:class="isPointerPanning ? 'cursor-grabbing select-none' : 'cursor-grab'"
                    class="overflow-x-auto overflow-y-visible [touch-action:pan-x_pan-y] [-webkit-overflow-scrolling:touch]"
                    x-bind:style="`min-height: ${trackMinHeight}px; overscroll-behavior-x: contain;`"
                >
                    <div x-ref="mainTrack" class="relative grid items-stretch" x-bind:style="`min-height: ${trackMinHeight}px; min-width: {{ max($dayCount * 17, 120) }}rem; grid-template-columns: repeat({{ $dayCount }}, minmax(0, 1fr));`">
                        <div aria-hidden="true" class="pointer-events-none absolute inset-0 grid" style="grid-template-columns: repeat({{ $dayCount }}, minmax(0, 1fr));">
                            @foreach ($days as $day)
                                <div class="h-full border-r last:border-r-0" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);"></div>
                            @endforeach
                        </div>
                        @foreach ($days as $day)
                            @php
                                $items = $itemsByDate->get($day['date'], collect());
                                $visibleItems = $items->take(5);
                                $remainingItemsCount = max(0, $items->count() - 5);
                            @endphp
                            <div
                                @if($day['is_today']) x-ref="todayColumn" @endif
                                class="group/day relative flex h-full min-h-[18rem] flex-col transition-all duration-200 ease-out"
                                style="background-color: {{ $day['is_today'] ? 'color-mix(in srgb, var(--theme-accent) 3%, var(--theme-surface-base))' : ($day['is_current_period'] ? 'color-mix(in srgb, var(--theme-surface-base) 76%, transparent)' : 'rgba(var(--theme-border-color-rgb), 0.03)') }};"
                                x-on:dragover="handleCalendarDayDragOver($event, '{{ $day['date'] }}', '{{ $day['long_label'] }}', {{ $day['is_move_target'] ? 'true' : 'false' }})"
                                x-on:dragleave="handleCalendarDayDragLeave('{{ $day['date'] }}')"
                                x-on:drop.prevent="openMovePostDialog('{{ $day['date'] }}', '{{ $day['long_label'] }}', {{ $day['is_move_target'] ? 'true' : 'false' }})"
                                x-bind:class="{
                                    'scale-[1.003] -translate-y-[1px]': canDropCalendarPost('{{ $day['date'] }}', {{ $day['is_move_target'] ? 'true' : 'false' }}) && dragTargetDate === '{{ $day['date'] }}',
                                    'ring-2 ring-[rgba(var(--theme-accent-rgb),0.18)] ring-inset': canDropCalendarPost('{{ $day['date'] }}', {{ $day['is_move_target'] ? 'true' : 'false' }}) && dragTargetDate === '{{ $day['date'] }}',
                                    'animate-pulse': recentDropDate === '{{ $day['date'] }}',
                                }"
                            >
                                <div
                                    x-show="recentDropDate === '{{ $day['date'] }}'"
                                    x-transition.opacity.duration.300ms
                                    class="pointer-events-none absolute inset-0 z-10"
                                    style="background: radial-gradient(circle at center, rgba(var(--theme-accent-rgb), 0.12), transparent 65%);"
                                ></div>
                                <div class="sticky top-0 z-30 overflow-hidden border-b px-3 py-3 backdrop-blur-xl @if (! $loop->last) border-r @endif" style="border-bottom-color: {{ $day['is_today'] ? 'rgba(var(--theme-accent-rgb), 0.22)' : 'rgba(var(--theme-border-color-rgb), 0.68)' }}; border-right-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: {{ $day['is_today'] ? 'color-mix(in srgb, var(--theme-accent) 7%, var(--theme-surface-overlay))' : 'color-mix(in srgb, var(--theme-surface-overlay) 92%, transparent)' }};">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <p class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ $day['label'] }}</p>
                                            <p class="mt-1 text-lg font-semibold tracking-[-0.04em]" style="color: {{ $day['is_today'] ? 'var(--theme-accent)' : 'var(--theme-header-text-color)' }};">{{ $day['day_number'] }}</p>
                                        </div>
                                        <div class="flex flex-col items-end gap-1">
                                            @if ($day['is_today'])
                                                <span class="rounded-full px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.16em]" style="background-color: rgba(var(--theme-accent-rgb), 0.12); color: var(--theme-accent);">{{ __('Today') }}</span>
                                            @endif
                                            @if ($items->isNotEmpty())
                                                <span class="rounded-full px-2 py-1 text-[9px] font-semibold uppercase tracking-[0.14em]" style="background-color: rgba(var(--theme-border-color-rgb), 0.08); color: var(--theme-muted-text-color);">
                                                    {{ trans_choice('{1} :count item|[2,*] :count items', $items->count(), ['count' => $items->count()]) }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>

                                    @if ($items->isNotEmpty() && $day['can_compose'])
                                        <div class="pointer-events-none absolute inset-0 z-40">
                                            <div class="h-full w-full -translate-y-full opacity-0 transition-all duration-220 ease-out group-hover/day:translate-y-0 group-hover/day:opacity-100 group-focus-within/day:translate-y-0 group-focus-within/day:opacity-100">
                                                <div class="flex h-full items-center px-3" style="background-color: color-mix(in srgb, rgba(var(--theme-accent-rgb), 0.18) 64%, var(--theme-surface-overlay));">
                                                    <x-ui.button
                                                        type="button"
                                                        variant="primary"
                                                        size="sm"
                                                        wire:click="openComposer('{{ $day['date'] }}')"
                                                        wire:loading.attr="disabled"
                                                        wire:target="openComposer"
                                                        class="pointer-events-auto w-full shadow-[0_12px_30px_-22px_rgba(var(--theme-accent-rgb),0.8)]"
                                                    >
                                                        <span class="inline-flex items-center gap-2" wire:loading.remove wire:target="openComposer">
                                                            <i class="fa-light fa-square-plus"></i>
                                                            {{ __('Compose') }}
                                                        </span>
                                                        <span class="inline-flex items-center gap-2" wire:loading wire:target="openComposer">
                                                            <i class="fa-light fa-loader animate-spin"></i>
                                                            {{ __('Loading...') }}
                                                        </span>
                                                    </x-ui.button>
                                                </div>
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <div class="flex-1 space-y-3 p-3">
                                    @forelse ($visibleItems as $item)
                                        @php
                                            $status = $statusMeta[$item['status_key']] ?? $statusMeta['pending'];
                                            $primaryMedia = collect($item['media_items'] ?? [])->first();
                                            $primaryPreview = is_array($primaryMedia) ? ($primaryMedia['previewUrl'] ?? $primaryMedia['url'] ?? null) : null;
                                            $primaryMime = strtolower((string) (is_array($primaryMedia) ? ($primaryMedia['mimeType'] ?? '') : ''));
                                            $hasVisual = filled($primaryPreview);
                                            $isVideo = str_starts_with($primaryMime, 'video/');
                                            $dragPayload = json_encode([
                                                'post_id' => (string) $item['post_id'],
                                                'title' => (string) $item['title'],
                                                'time' => (string) $item['time'],
                                                'date' => (string) $item['date'],
                                                'can_edit' => !empty($item['can_edit']),
                                            ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
                                            $footerStatusLabel = $status['label'] ?? __('Pending');
                                            $footerStatusTitle = match ($item['status_key'] ?? 'pending') {
                                                'published' => __('This post is published, but the direct link is not available yet.'),
                                                'processing' => __('This post is still being processed by the social network.'),
                                                'failed' => __('This post failed to publish.'),
                                                default => __('This post has not been published yet.'),
                                            };
                                        @endphp
                                        <article
                                            class="relative z-0 overflow-visible rounded-[1rem] border shadow-[0_18px_36px_-30px_rgba(15,23,42,0.2)] transition-all duration-200 ease-out hover:z-10 focus-within:z-10 {{ !empty($item['can_edit']) ? 'cursor-grab active:cursor-grabbing' : '' }}"
                                            style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-overlay) 98%, transparent);"
                                            draggable="{{ !empty($item['can_edit']) ? 'true' : 'false' }}"
                                            data-drag-payload='{{ $dragPayload }}'
                                            x-on:dragstart="startCalendarPostDrag(JSON.parse($el.dataset.dragPayload || '{}'))"
                                            x-on:dragend="if (!dragMoveModalOpen) clearCalendarPostDrag()"
                                            x-bind:class="draggingCalendarPost && draggingCalendarPost.id === '{{ $item['post_id'] }}'
                                                ? 'opacity-70 scale-[1.02] -rotate-[1.2deg] shadow-[0_28px_60px_-30px_rgba(var(--theme-accent-rgb),0.45)]'
                                                : ''"
                                        >
                                            <div class="flex items-center justify-between gap-2 border-b px-3 py-2.5" style="border-color: rgba(var(--theme-border-color-rgb), 0.55); background-color: rgba(var(--theme-border-color-rgb), 0.04);">
                                                <div class="flex min-w-0 items-center gap-2.5">
                                                    <div class="inline-flex items-center gap-1.5 rounded-full px-1.5 py-1" style="background-color: rgba(var(--theme-border-color-rgb), 0.06);">
                                                        <span class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px]" style="background-color: color-mix(in srgb, var(--theme-surface-base) 95%, transparent); color: var(--theme-header-text-color);">
                                                        <i class="{{ $item['provider_icon'] }}"></i>
                                                        </span>
                                                        <span
                                                            class="inline-flex h-5 w-5 shrink-0 items-center justify-center overflow-hidden rounded-full border text-[9px] font-semibold"
                                                            style="border-color: rgba(var(--theme-border-color-rgb), 0.5); background-color: color-mix(in srgb, var(--theme-surface-base) 98%, transparent); color: var(--theme-header-text-color);"
                                                            title="{{ $item['provider'] }} | {{ $item['channel'] }}{{ $item['handle'] ? ' | '.$item['handle'] : '' }} | {{ $item['time'] }}"
                                                        >
                                                            @if ($item['avatar_url'])
                                                                <img src="{{ $item['avatar_url'] }}" alt="{{ $item['channel'] }}" class="h-full w-full object-cover">
                                                            @else
                                                                {{ $item['initials'] }}
                                                            @endif
                                                        </span>
                                                    </div>
                                                    <span class="text-[11px] font-semibold uppercase tracking-[0.12em]" style="color: var(--theme-muted-text-color);">{{ $item['time'] }}</span>
                                                </div>
                                                <span class="rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.12em]" style="background-color: {{ $status['surface'] }}; color: {{ $status['text'] }};">
                                                    {{ $item['status'] }}
                                                </span>
                                            </div>

                                            <div class="space-y-3 p-3">
                                                <div class="flex items-start gap-3">
                                                    <div class="min-w-0 flex-1">
                                                        <div class="flex items-start gap-2">
                                                            <p class="min-w-0 text-[14px] font-semibold leading-5" style="color: var(--theme-header-text-color); display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">
                                                                {{ $item['title'] }}
                                                            </p>
                                                        </div>

                                                        @if (!empty($item['source_label']) || !empty($item['campaign']) || !empty($item['tags']))
                                                            <div class="mt-2 flex flex-wrap gap-1.5">
                                                                <span
                                                                    class="rounded-full px-2 py-0.5 text-[10px] font-medium"
                                                                    style="background-color: {{ $item['source_surface'] }}; color: {{ $item['source_text'] }};"
                                                                >
                                                                    {{ $item['source_label'] }}
                                                                </span>
                                                                @if (!empty($item['campaign']))
                                                                    <span
                                                                        class="rounded-full px-2 py-0.5 text-[10px] font-medium"
                                                                        style="background-color: color-mix(in srgb, {{ $item['campaign_color'] ?: '#c9802a' }} 14%, white); color: {{ $item['campaign_color'] ?: '#c9802a' }};"
                                                                    >
                                                                        {{ $item['campaign'] }}
                                                                    </span>
                                                                @endif
                                                                @foreach (collect($item['tags'])->take(2) as $tag)
                                                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-medium" style="background-color: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-muted-text-color);">{{ $tag }}</span>
                                                                @endforeach
                                                            </div>
                                                        @endif
                                                    </div>

                                                    @if ($hasVisual)
                                                        <div
                                                            class="relative h-[4.5rem] w-[4.5rem] shrink-0 overflow-hidden rounded-[0.9rem] border"
                                                            style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.08);"
                                                            x-data="{ imageLoaded: false, imageFailed: false }"
                                                        >
                                                            @if ($isVideo)
                                                                <video class="h-full w-full object-cover" muted playsinline preload="metadata">
                                                                    <source src="{{ $primaryPreview }}" type="{{ $primaryMime ?: 'video/mp4' }}">
                                                                </video>
                                                                <span class="absolute inset-x-0 bottom-0 flex justify-center pb-2">
                                                                    <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-950/70 text-[10px] text-white">
                                                                        <i class="fa-solid fa-play"></i>
                                                                    </span>
                                                                </span>
                                                            @else
                                                                <img
                                                                    src="{{ $primaryPreview }}"
                                                                    alt="{{ $item['title'] }}"
                                                                    class="h-full w-full object-cover transition-opacity duration-300 ease-out"
                                                                    loading="lazy"
                                                                    decoding="async"
                                                                    x-show="!imageFailed"
                                                                    x-bind:style="imageLoaded ? 'opacity: 1; visibility: visible;' : 'opacity: 0; visibility: hidden;'"
                                                                    x-on:load="imageLoaded = true"
                                                                    x-on:error="imageFailed = true; imageLoaded = false"
                                                                >
                                                                <span
                                                                    x-show="!imageLoaded && !imageFailed"
                                                                    class="absolute inset-0 flex items-center justify-center"
                                                                >
                                                                    <i class="fa-solid fa-spinner animate-spin text-sm" style="color: var(--theme-muted-text-color);"></i>
                                                                </span>
                                                            @endif
                                                        </div>
                                                    @endif
                                                </div>

                                                @if (filled($item['excerpt']))
                                                    <p class="text-[12px] leading-5" style="color: var(--theme-muted-text-color); display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;">
                                                        {{ $item['excerpt'] }}
                                                    </p>
                                                @endif

                                                <div class="relative z-30 flex items-center justify-between gap-3 border-t pt-2.5 text-[11px]" style="border-color: rgba(var(--theme-border-color-rgb), 0.45); color: var(--theme-muted-text-color);">
                                                    <button
                                                        type="button"
                                                        class="inline-flex h-8 w-8 items-center justify-center rounded-[0.7rem] border transition hover:bg-slate-900/5"
                                                        style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color);"
                                                        wire:click="openPostPreview('{{ $item['post_id'] }}')"
                                                        title="{{ __('Preview post') }}"
                                                    >
                                                        <i class="fa-light fa-eye"></i>
                                                    </button>

                                                    @if (!empty($item['post_url']))
                                                        <div class="flex items-center gap-2">
                                                            <a
                                                                href="{{ $item['post_url'] }}"
                                                                target="_blank"
                                                                rel="noopener noreferrer"
                                                                class="rounded-[0.7rem] border px-2.5 py-1 text-[11px] font-semibold transition hover:bg-slate-900/5"
                                                                style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-header-text-color);"
                                                                title="{{ __('Open published post') }}"
                                                            >
                                                                {{ __('View Post') }}
                                                            </a>

                                                            <x-ui.dropdown-menu align="right" width="auto">
                                                                <x-slot:trigger>
                                                                    <button
                                                                        type="button"
                                                                        class="inline-flex h-8 w-8 items-center justify-center rounded-[0.7rem] border transition hover:bg-slate-900/5"
                                                                        style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color);"
                                                                    >
                                                                        <i class="fa-light fa-ellipsis"></i>
                                                                    </button>
                                                                </x-slot:trigger>

                                                                <x-ui.dropdown-menu-item icon="fa-light fa-copy" wire:click="copyPost('{{ $item['post_id'] }}')">
                                                                    {{ __('Copy post') }}
                                                                </x-ui.dropdown-menu-item>

                                                                @if (!empty($item['can_delete_remote']))
                                                                    <x-ui.dropdown-menu-item
                                                                        icon="fa-light fa-trash-arrow-up"
                                                                        variant="danger"
                                                                        :close="false"
                                                                        x-on:click.stop="open = false; openDeleteDialog('remote', '{{ $item['post_id'] }}')"
                                                                    >
                                                                        {{ __('Delete on social network') }}
                                                                    </x-ui.dropdown-menu-item>
                                                                @endif

                                                                <x-ui.dropdown-menu-item
                                                                    icon="fa-light fa-trash-can"
                                                                    variant="danger"
                                                                    :close="false"
                                                                    x-on:click.stop="open = false; openDeleteDialog('local', '{{ $item['post_id'] }}')"
                                                                >
                                                                    {{ __('Delete post') }}
                                                                </x-ui.dropdown-menu-item>
                                                            </x-ui.dropdown-menu>
                                                        </div>
                                                    @elseif (!empty($item['open_error']))
                                                        <div class="flex items-center gap-2">
                                                            <span
                                                                class="cursor-help rounded-[0.7rem] border px-2.5 py-1 text-[11px] font-semibold"
                                                                style="border-color: rgba(239, 68, 68, 0.22); background-color: rgba(239, 68, 68, 0.08); color: #b91c1c;"
                                                                title="{{ $item['open_error'] }}"
                                                            >
                                                                {{ __('Failed') }}
                                                            </span>

                                                            <x-ui.dropdown-menu align="right" width="auto">
                                                                <x-slot:trigger>
                                                                    <button
                                                                        type="button"
                                                                        class="inline-flex h-8 w-8 items-center justify-center rounded-[0.7rem] border transition hover:bg-slate-900/5"
                                                                        style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color);"
                                                                    >
                                                                        <i class="fa-light fa-ellipsis"></i>
                                                                    </button>
                                                                </x-slot:trigger>

                                                                @if (!empty($item['can_edit']))
                                                                    <x-ui.dropdown-menu-item icon="fa-light fa-pen-to-square" wire:click="editPost('{{ $item['post_id'] }}')">
                                                                        {{ __('Edit post') }}
                                                                    </x-ui.dropdown-menu-item>
                                                                @endif

                                                                <x-ui.dropdown-menu-item icon="fa-light fa-copy" wire:click="copyPost('{{ $item['post_id'] }}')">
                                                                    {{ __('Copy post') }}
                                                                </x-ui.dropdown-menu-item>

                                                                <x-ui.dropdown-menu-item
                                                                    icon="fa-light fa-trash-can"
                                                                    variant="danger"
                                                                    :close="false"
                                                                    x-on:click.stop="open = false; openDeleteDialog('local', '{{ $item['post_id'] }}')"
                                                                >
                                                                    {{ __('Delete post') }}
                                                                </x-ui.dropdown-menu-item>
                                                            </x-ui.dropdown-menu>
                                                        </div>
                                                    @else
                                                        <div class="flex items-center gap-2">
                                                            @if (!empty($item['can_edit']))
                                                                <button
                                                                    type="button"
                                                                    class="rounded-[0.7rem] border px-2.5 py-1 text-[11px] font-semibold transition hover:bg-slate-900/5"
                                                                    style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-header-text-color);"
                                                                    wire:click="editPost('{{ $item['post_id'] }}')"
                                                                >
                                                                    {{ __('Edit Post') }}
                                                                </button>
                                                            @else
                                                                <span
                                                                    class="rounded-[0.7rem] border px-2.5 py-1 text-[11px] font-semibold"
                                                                    style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-header-text-color);"
                                                                    title="{{ $footerStatusTitle }}"
                                                                >
                                                                    {{ __('Preview Post') }}
                                                                </span>
                                                            @endif

                                                            <x-ui.dropdown-menu align="right" width="auto">
                                                                <x-slot:trigger>
                                                                    <button
                                                                        type="button"
                                                                        class="inline-flex h-8 w-8 items-center justify-center rounded-[0.7rem] border transition hover:bg-slate-900/5"
                                                                        style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color);"
                                                                    >
                                                                        <i class="fa-light fa-ellipsis"></i>
                                                                    </button>
                                                                </x-slot:trigger>

                                                                @if (!empty($item['can_edit']))
                                                                    <x-ui.dropdown-menu-item icon="fa-light fa-pen-to-square" wire:click="editPost('{{ $item['post_id'] }}')">
                                                                        {{ __('Edit post') }}
                                                                    </x-ui.dropdown-menu-item>
                                                                @endif

                                                                <x-ui.dropdown-menu-item icon="fa-light fa-eye" wire:click="openPostPreview('{{ $item['post_id'] }}')">
                                                                    {{ __('Preview post') }}
                                                                </x-ui.dropdown-menu-item>

                                                                <x-ui.dropdown-menu-item icon="fa-light fa-copy" wire:click="copyPost('{{ $item['post_id'] }}')">
                                                                    {{ __('Copy post') }}
                                                                </x-ui.dropdown-menu-item>

                                                                <x-ui.dropdown-menu-item
                                                                    icon="fa-light fa-trash-can"
                                                                    variant="danger"
                                                                    :close="false"
                                                                    x-on:click.stop="open = false; openDeleteDialog('local', '{{ $item['post_id'] }}')"
                                                                >
                                                                    {{ __('Delete post') }}
                                                                </x-ui.dropdown-menu-item>
                                                            </x-ui.dropdown-menu>
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </article>
                                    @empty
                                        <div class="rounded-[1.1rem] border border-dashed px-4 py-7 text-center" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.02);">
                                            <span class="inline-flex h-11 w-11 items-center justify-center rounded-[0.95rem]" style="background-color: rgba(var(--theme-border-color-rgb), 0.06); color: var(--theme-muted-text-color);">
                                                <i class="fa-light fa-calendar-plus text-base"></i>
                                            </span>
                                            <p class="mt-3 text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Open slot') }}</p>
                                            <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('No posts scheduled.') }}</p>
                                            @if ($day['can_compose'])
                                                <div class="mt-4">
                                                    <x-ui.button
                                                        type="button"
                                                        variant="outline"
                                                        size="sm"
                                                        wire:click="openComposer('{{ $day['date'] }}')"
                                                        wire:loading.attr="disabled"
                                                        wire:target="openComposer"
                                                    >
                                                        <span wire:loading.remove wire:target="openComposer">{{ __('Compose') }}</span>
                                                        <span class="inline-flex items-center gap-2" wire:loading wire:target="openComposer">
                                                            <i class="fa-light fa-loader animate-spin"></i>
                                                            {{ __('Loading...') }}
                                                        </span>
                                                    </x-ui.button>
                                                </div>
                                            @endif
                                        </div>
                                    @endforelse

                                    @if ($remainingItemsCount > 0)
                                        <button
                                            type="button"
                                            class="w-full cursor-pointer rounded-[0.9rem] border px-3 py-2.5 text-sm font-semibold transition duration-150 ease-out hover:-translate-y-[1px] hover:bg-slate-900/5 focus:outline-none focus-visible:ring-2 focus-visible:ring-[rgba(var(--theme-accent-rgb),0.28)] active:translate-y-0"
                                            style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-header-text-color); background-color: color-mix(in srgb, var(--theme-surface-overlay) 98%, transparent);"
                                            wire:click="openDayPosts('{{ $day['date'] }}')"
                                            x-on:click="dayPostsModalLoadingDate = '{{ $day['date'] }}'"
                                            x-bind:disabled="dayPostsModalLoadingDate === '{{ $day['date'] }}'"
                                            x-bind:class="{ 'cursor-wait opacity-70 hover:translate-y-0 hover:bg-transparent': dayPostsModalLoadingDate === '{{ $day['date'] }}' }"
                                            data-no-loading
                                        >
                                            <span
                                                class="inline-flex items-center gap-2"
                                                x-show="dayPostsModalLoadingDate !== '{{ $day['date'] }}'"
                                            >
                                                {{ __('View more (:count)', ['count' => $remainingItemsCount]) }}
                                            </span>
                                            <span
                                                class="inline-flex items-center gap-2"
                                                x-show="dayPostsModalLoadingDate === '{{ $day['date'] }}'"
                                                x-cloak
                                            >
                                                <i class="fa-solid fa-spinner animate-spin"></i>
                                                {{ __('Loading...') }}
                                            </span>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div aria-hidden="true" class="hidden sticky bottom-0 z-30 border-t backdrop-blur-xl" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-base) 94%, transparent);">
                    <div x-ref="bottomScroller" x-on:scroll="sync($refs.bottomScroller, $refs.mainScroller)" class="overflow-x-auto px-2">
                        <div x-ref="bottomTrack" class="h-1"></div>
                    </div>
                </div>
                @endif
            </section>

        </div>

    <div>
        <template x-teleport="body">
            <div
                x-cloak
                x-show="dayPostsModalOpen"
                class="fixed inset-0 z-[118] flex items-center justify-center p-4 sm:p-6"
                x-on:keydown.escape.window="closeDayPostsModal()"
            >
                <div class="absolute inset-0 bg-white/55 backdrop-blur-[6px] dark:bg-slate-950/55" x-on:click="closeDayPostsModal()"></div>

                <div x-show="dayPostsModalOpen" x-transition.opacity.scale.95 class="relative w-full max-w-[44rem]">
                    <div class="overflow-hidden rounded-[1.15rem] border shadow-[0_32px_80px_-34px_rgba(15,23,42,0.32)]" style="border-color: color-mix(in srgb, var(--theme-border-color) 58%, transparent); background-color: var(--theme-surface-overlay);">
                        <div class="flex items-start justify-between gap-4 border-b px-5 py-4 sm:px-6 sm:py-5" style="border-color: color-mix(in srgb, var(--theme-border-color) 52%, transparent);">
                            <div class="min-w-0">
                                <h3 class="text-[1.05rem] font-semibold tracking-[-0.02em] text-slate-950 dark:text-white">{{ __('Posts for') }} <span x-text="dayPostsModalDateLabel"></span></h3>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400"><span x-text="`${dayPostsModalItems.length} {{ __('items') }}`"></span></p>
                            </div>

                            <button type="button" class="text-slate-400 transition hover:text-slate-700 dark:hover:text-slate-200" x-on:click="closeDayPostsModal()">
                                <i class="fa-light fa-xmark text-lg"></i>
                            </button>
                        </div>

                        <div class="max-h-[70vh] overflow-y-auto px-5 py-4 sm:px-6">
                            <div class="space-y-3">
                                <template x-for="modalItem in dayPostsModalItems" :key="`day-post-${modalItem.post_id}`">
                                    <div
                                        class="rounded-[0.95rem] border px-3 py-3"
                                        style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-overlay) 98%, transparent);"
                                        x-data="{
                                            expanded: false,
                                            primaryMedia() {
                                                return modalItem?.media_items?.[0] || {};
                                            },
                                            primaryPreview() {
                                                const media = this.primaryMedia();
                                                return String(media.previewUrl || media.preview_url || media.url || '').trim();
                                            },
                                            primaryMime() {
                                                const media = this.primaryMedia();
                                                return String(media.mimeType || media.mime_type || '').toLowerCase().trim();
                                            },
                                            videoPoster() {
                                                const media = this.primaryMedia();
                                                return String(media.thumbnail || media.thumbnail_url || media.poster || media.poster_url || '').trim();
                                            },
                                            hasVisual() {
                                                return this.primaryPreview() !== '';
                                            },
                                            isVideo() {
                                                return this.primaryMime().startsWith('video/');
                                            },
                                            imageLoaded: false,
                                            imageFailed: false,
                                        }"
                                    >
                                        <div class="flex items-start justify-between gap-4">
                                            <div class="min-w-0 flex-1 space-y-3">
                                                <div class="min-w-0">
                                                    <div class="flex items-start gap-3">
                                                        <div class="relative h-11 w-11 shrink-0">
                                                            <div
                                                                class="h-11 w-11 overflow-hidden rounded-full border"
                                                                style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.08);"
                                                            >
                                                                <img
                                                                    x-show="Boolean(modalItem.avatar_url)"
                                                                    x-bind:src="modalItem.avatar_url"
                                                                    alt=""
                                                                    class="h-full w-full object-cover"
                                                                >
                                                                <div
                                                                    x-show="!modalItem.avatar_url"
                                                                    class="flex h-full w-full items-center justify-center text-sm"
                                                                    style="color: var(--theme-muted-text-color);"
                                                                >
                                                                    <i class="fa-light fa-user"></i>
                                                                </div>
                                                            </div>
                                                            <span
                                                                class="absolute -bottom-1 -right-1 inline-flex h-5 w-5 items-center justify-center rounded-full border text-[10px] shadow-sm"
                                                                x-bind:style="`border-color: rgba(255,255,255,0.92); background-color: ${modalItem.provider_color || '#2563eb'}; color: #ffffff;`"
                                                                x-bind:title="modalItem.provider || modalItem.channel || ''"
                                                            >
                                                                <i x-bind:class="modalItem.provider_icon || 'fa-light fa-share-nodes'"></i>
                                                            </span>
                                                        </div>

                                                        <div class="min-w-0 flex-1">
                                                            <div class="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
                                                                <p class="truncate text-sm font-semibold" style="color: var(--theme-header-text-color);" x-text="modalItem.channel"></p>
                                                                <span class="text-xs" style="color: var(--theme-muted-text-color);" x-text="modalItem.provider"></span>
                                                            </div>
                                                            <p class="mt-1 text-xs" style="color: var(--theme-muted-text-color);" x-text="modalItem.time"></p>
                                                        </div>
                                                    </div>
                                                <p
                                                    class="mt-1 whitespace-pre-line text-[0.95rem] font-normal leading-7"
                                                    style="color: var(--theme-muted-text-color);"
                                                    x-bind:class="expanded ? '' : 'max-h-[7em] overflow-hidden'"
                                                    x-show="String(modalItem.excerpt || '').trim() !== ''"
                                                    x-text="String(modalItem.excerpt || '').trim()"
                                                ></p>
                                                <p
                                                    class="mt-2 inline-flex max-w-full items-start gap-1.5 rounded-[0.65rem] border px-2 py-1 text-xs leading-5"
                                                    style="border-color: rgba(239, 68, 68, 0.28); background-color: rgba(239, 68, 68, 0.08); color: #b91c1c;"
                                                    x-show="String(modalItem.status_key || '').toLowerCase() === 'failed' && String(modalItem.open_error || '').trim() !== ''"
                                                    x-bind:title="String(modalItem.open_error || '')"
                                                >
                                                    <i class="fa-light fa-triangle-exclamation mt-[2px]"></i>
                                                    <span
                                                        style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;"
                                                        x-text="String(modalItem.open_error || '')"
                                                    ></span>
                                                </p>
                                                <button
                                                    type="button"
                                                    class="mt-1 text-xs font-semibold transition hover:opacity-80"
                                                    style="color: var(--theme-accent);"
                                                    x-show="String(modalItem.excerpt || '').trim().length > 220"
                                                    x-on:click="expanded = !expanded"
                                                    x-text="expanded ? @js(__('Show less')) : @js(__('Show more'))"
                                                ></button>
                                                </div>

                                                <div class="flex flex-wrap items-center gap-2">
                                                    <button type="button" class="rounded-[0.7rem] border px-2.5 py-1 text-[11px] font-semibold transition hover:bg-slate-900/5" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-header-text-color);" x-on:click="closeDayPostsModal(); $wire.openPostPreview(modalItem.post_id)">
                                                        {{ __('Preview') }}
                                                    </button>
                                                    <button type="button" class="rounded-[0.7rem] border px-2.5 py-1 text-[11px] font-semibold transition hover:bg-slate-900/5" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-header-text-color);" x-on:click="closeDayPostsModal(); $wire.copyPost(modalItem.post_id)">
                                                        {{ __('Copy') }}
                                                    </button>
                                                    <button type="button" class="rounded-[0.7rem] border px-2.5 py-1 text-[11px] font-semibold transition hover:bg-red-50/50" style="border-color: rgba(239, 68, 68, 0.35); color: #b91c1c;" x-on:click="openDeleteDialog('local', modalItem.post_id); closeDayPostsModal();">
                                                        {{ __('Delete') }}
                                                    </button>
                                                    <a x-show="Boolean(modalItem.post_url)" x-bind:href="modalItem.post_url" target="_blank" rel="noopener noreferrer" class="rounded-[0.7rem] border px-2.5 py-1 text-[11px] font-semibold transition hover:bg-slate-900/5" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-header-text-color);">
                                                        {{ __('View Post') }}
                                                    </a>
                                                </div>
                                            </div>

                                            <div class="flex w-[6.25rem] shrink-0 flex-col items-end gap-2 self-start">
                                                <span
                                                    class="rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.12em]"
                                                    x-bind:style="(() => {
                                                        const key = String(modalItem.status_key || '').toLowerCase();
                                                        if (key === 'published') return 'background-color: rgba(16, 185, 129, 0.14); color: #059669;';
                                                        if (key === 'failed') return 'background-color: rgba(239, 68, 68, 0.12); color: #dc2626;';
                                                        if (key === 'processing') return 'background-color: rgba(99, 102, 241, 0.12); color: #4f46e5;';
                                                        if (key === 'waiting_approve') return 'background-color: rgba(245, 158, 11, 0.14); color: #d97706;';
                                                        if (key === 'draft') return 'background-color: rgba(99, 102, 241, 0.14); color: #4f46e5;';
                                                        return 'background-color: rgba(59, 130, 246, 0.12); color: #2563eb;';
                                                    })()"
                                                    x-text="modalItem.status"
                                                ></span>
                                                <div
                                                    x-show="hasVisual()"
                                                    class="relative h-14 w-14 overflow-hidden rounded-[0.75rem] border"
                                                    style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.06);"
                                                >
                                                    <div
                                                        x-show="isVideo()"
                                                        class="absolute inset-0"
                                                        style="background: linear-gradient(180deg, rgba(15,23,42,0.10) 0%, rgba(15,23,42,0.22) 100%);"
                                                    ></div>
                                                    <video
                                                        x-show="isVideo()"
                                                        x-bind:poster="videoPoster() || null"
                                                        autoplay
                                                        loop
                                                        muted
                                                        playsinline
                                                        preload="metadata"
                                                        class="h-full w-full object-cover"
                                                        x-init="$nextTick(() => { try { $el.play?.(); } catch (e) {} })"
                                                        x-on:loadedmetadata="
                                                            try {
                                                                if (($el.duration || 0) > 0.1) {
                                                                    $el.currentTime = 0.1;
                                                                }
                                                            } catch (e) {}
                                                        "
                                                        x-on:loadeddata="
                                                            try {
                                                                if (($el.currentTime || 0) === 0 && ($el.duration || 0) > 0.1) {
                                                                    $el.currentTime = 0.1;
                                                                }
                                                                $el.play?.();
                                                            } catch (e) {}
                                                        "
                                                    >
                                                        <source x-bind:src="primaryPreview()" x-bind:type="primaryMime() || 'video/mp4'">
                                                    </video>
                                                    <img
                                                        x-show="!isVideo() && !imageFailed"
                                                        x-bind:src="primaryPreview()"
                                                        alt=""
                                                        class="h-full w-full object-cover"
                                                        x-on:load="imageLoaded = true"
                                                        x-on:error="imageFailed = true; imageLoaded = false"
                                                    >
                                                    <span
                                                        x-show="!isVideo() && !imageLoaded && !imageFailed"
                                                        class="absolute inset-0 flex items-center justify-center"
                                                    >
                                                        <i class="fa-solid fa-spinner animate-spin text-sm" style="color: var(--theme-muted-text-color);"></i>
                                                    </span>
                                                    <span
                                                        x-show="isVideo()"
                                                        class="absolute inset-0 flex items-center justify-center"
                                                    >
                                                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-950/70 text-[10px] text-white shadow-sm">
                                                            <i class="fa-solid fa-play"></i>
                                                        </span>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <template x-teleport="body">
            <div
                x-cloak
                x-show="dragMoveModalOpen"
                class="fixed inset-0 z-[119] flex items-center justify-center p-4 sm:p-6"
                x-on:keydown.escape.window="closeMovePostDialog()"
            >
                <div class="absolute inset-0 bg-white/55 backdrop-blur-[6px] dark:bg-slate-950/55" x-on:click="closeMovePostDialog()"></div>

                <div x-show="dragMoveModalOpen" x-transition.opacity.scale.90 class="relative w-full max-w-[28rem]">
                    <div class="overflow-hidden rounded-[1.15rem] border shadow-[0_32px_80px_-34px_rgba(15,23,42,0.32)]" style="border-color: color-mix(in srgb, var(--theme-border-color) 58%, transparent); background-color: var(--theme-surface-overlay);">
                        <div class="flex items-start justify-between gap-4 border-b px-5 py-4 sm:px-6 sm:py-5" style="border-color: color-mix(in srgb, var(--theme-border-color) 52%, transparent);">
                            <div class="min-w-0">
                                <h3 class="text-[1.05rem] font-semibold tracking-[-0.02em] text-slate-950 dark:text-white">{{ __('Move post') }}</h3>
                                <p class="mt-2 text-[15px] leading-7 text-slate-500 dark:text-slate-400">
                                    <span x-text="draggingCalendarPost ? `${draggingCalendarPost.title || @js(__('Untitled post'))} -> ${dragTargetDateLabel || dragTargetDate}` : ''"></span>
                                </p>
                            </div>

                            <button type="button" class="text-slate-400 transition hover:text-slate-700 dark:hover:text-slate-200" x-on:click="closeMovePostDialog()">
                                <i class="fa-light fa-xmark text-lg"></i>
                            </button>
                        </div>

                        <div class="space-y-4 px-5 py-4 sm:px-6">
                            <label class="flex cursor-pointer items-start gap-3 rounded-[0.95rem] border px-3 py-3 transition hover:bg-slate-900/5" style="border-color: rgba(var(--theme-border-color-rgb), 0.6);">
                                <input type="radio" name="move-time-choice" class="mt-1" x-model="dragMoveMode" value="keep">
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Keep current time') }}</span>
                                    <span class="mt-1 block text-sm" style="color: var(--theme-muted-text-color);" x-text="draggingCalendarPost ? `{{ __('Publish at') }} ${draggingCalendarPost.time}` : ''"></span>
                                </span>
                            </label>

                            <label class="flex cursor-pointer items-start gap-3 rounded-[0.95rem] border px-3 py-3 transition hover:bg-slate-900/5" style="border-color: rgba(var(--theme-border-color-rgb), 0.6);">
                                <input type="radio" name="move-time-choice" class="mt-1" x-model="dragMoveMode" value="change">
                                <span class="min-w-0 flex-1">
                                    <span class="block text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Change time') }}</span>
                                    <span class="mt-1 block text-sm" style="color: var(--theme-muted-text-color);">{{ __('Choose a new publish time for the target day.') }}</span>
                                    <div x-show="dragMoveMode === 'change'" x-cloak class="mt-3">
                                        <x-ui.time-picker
                                            x-model="dragMoveTime"
                                            :placeholder="__('Select time')"
                                            pickerAlign="left"
                                            pickerPosition="top"
                                            class="w-full"
                                        />
                                    </div>
                                </span>
                            </label>
                        </div>

                        <div class="border-t bg-slate-50/70 px-5 py-4 sm:px-6" style="border-color: color-mix(in srgb, var(--theme-border-color) 52%, transparent);">
                            <div class="flex items-center justify-end gap-3">
                                <x-ui.button type="button" variant="outline" x-on:click="closeMovePostDialog()" x-bind:disabled="dragMoveSubmitting">
                                    {{ __('Cancel') }}
                                </x-ui.button>

                                <x-ui.button
                                    type="button"
                                    variant="primary"
                                    x-on:click="confirmMovePost()"
                                    x-bind:disabled="dragMoveSubmitting || (dragMoveMode === 'change' && String(dragMoveTime || '').trim() === '')"
                                    data-no-loading
                                >
                                    <span class="inline-flex items-center gap-2" x-show="!dragMoveSubmitting">
                                        <i class="fa-light fa-arrows-up-down-left-right"></i>
                                        {{ __('Move post') }}
                                    </span>
                                    <span class="inline-flex items-center gap-2" x-show="dragMoveSubmitting" x-cloak>
                                        <i class="fa-light fa-loader animate-spin"></i>
                                        {{ __('Moving...') }}
                                    </span>
                                </x-ui.button>
                            </div>
                        </div>
                </div>
            </div>
        </template>

        <template x-teleport="body">
            <div
                x-cloak
                x-show="confirmDeleteOpen"
                class="fixed inset-0 z-[120] flex items-center justify-center p-4 sm:p-6"
                x-on:keydown.escape.window="closeDeleteDialog()"
            >
                <div class="absolute inset-0 bg-white/55 backdrop-blur-[6px] dark:bg-slate-950/55" x-on:click="closeDeleteDialog()"></div>

                <div x-show="confirmDeleteOpen" x-transition.opacity.scale.90 class="relative w-full max-w-[26rem]">
                    <div class="overflow-hidden rounded-[1.15rem] border shadow-[0_32px_80px_-34px_rgba(15,23,42,0.32)]" style="border-color: color-mix(in srgb, var(--theme-border-color) 58%, transparent); background-color: var(--theme-surface-overlay);">
                        <div class="flex items-start justify-between gap-4 border-b px-5 py-4 sm:px-6 sm:py-5" style="border-color: color-mix(in srgb, var(--theme-border-color) 52%, transparent);">
                            <div class="min-w-0">
                                <h3 class="text-[1.05rem] font-semibold tracking-[-0.02em] text-slate-950 dark:text-white">
                                    <span x-text="confirmDeleteType === 'remote'
                                        ? @js(__('Delete this post on the social network?'))
                                        : (confirmDeleteType === 'filtered'
                                            ? @js(__('Delete all filtered posts?'))
                                            : @js(__('Delete this post?')))"></span>
                                </h3>
                                <p class="mt-2 text-[15px] leading-7 text-slate-500 dark:text-slate-400">
                                    <span x-text="confirmDeleteType === 'remote'
                                        ? @js(__('This will remove the published post from the connected social network and reset it locally.'))
                                        : (confirmDeleteType === 'filtered'
                                            ? @js(__('This permanently removes all posts matching the current filters in this view.'))
                                            : @js(__('This permanently removes the publishing item from your queue.')))"></span>
                                </p>
                            </div>

                            <button type="button" class="text-slate-400 transition hover:text-slate-700 dark:hover:text-slate-200" x-on:click="closeDeleteDialog()">
                                <i class="fa-light fa-xmark text-lg"></i>
                            </button>
                        </div>

                        <div class="border-t bg-slate-50/70 px-5 py-4 sm:px-6" style="border-color: color-mix(in srgb, var(--theme-border-color) 52%, transparent);">
                            <div class="flex items-center justify-end gap-3">
                                <x-ui.button type="button" variant="outline" x-on:click="closeDeleteDialog()">
                                    {{ __('Cancel') }}
                                </x-ui.button>

                                <x-ui.button
                                    type="button"
                                    variant="danger"
                                    x-on:click="
                                        if (confirmDeleteType === 'remote') {
                                            $wire.deleteRemotePost(confirmDeletePostId);
                                        } else if (confirmDeleteType === 'filtered') {
                                            $wire.deleteFilteredPosts();
                                        } else {
                                            $wire.deletePost(confirmDeletePostId);
                                        }
                                        closeDeleteDialog();
                                    "
                                >
                                    <span x-text="confirmDeleteType === 'remote'
                                        ? @js(__('Delete on social network'))
                                        : (confirmDeleteType === 'filtered'
                                            ? @js(__('Delete filtered posts'))
                                            : @js(__('Delete post')))"></span>
                                </x-ui.button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    @if ($postPreviewOpen && $postPreviewAccount)
    <div
        class="fixed inset-0 z-[130] flex items-center justify-center p-4 sm:p-6"
        x-data="{
            localPreviewCaption: @js((string) ($postPreviewComposer['caption'] ?? '')),
            localPreviewMediaItems: @js(collect((array) ($postPreviewComposer['media_items'] ?? []))->values()->all()),
            previewPrimaryMedia() {
                return Array.isArray(this.localPreviewMediaItems) && this.localPreviewMediaItems.length > 0
                    ? (this.localPreviewMediaItems[0] || null)
                    : null;
            },
            previewMediaUrl() {
                const media = this.previewPrimaryMedia();

                if (!media || typeof media !== 'object') {
                    return '';
                }

                return String(media.previewUrl || media.url || '');
            },
            previewMediaIsVideo() {
                const media = this.previewPrimaryMedia();

                if (!media || typeof media !== 'object') {
                    return false;
                }

                const mime = String(media.mimeType || '').toLowerCase();
                const category = String(media.category || '').toLowerCase();
                const extension = String(media.extension || '').toLowerCase();
                const preview = String(media.previewUrl || media.url || '').toLowerCase();

                return mime.startsWith('video/')
                    || category === 'video'
                    || ['mp4', 'mov', 'webm', 'm4v', 'avi', 'mkv'].includes(extension)
                    || /\.(mp4|mov|webm|m4v|avi|mkv)(\?.*)?$/.test(preview);
            },
        }"
        x-on:keydown.escape.window="$wire.closePostPreview()"
    >
        <div class="absolute inset-0 bg-slate-950/55 backdrop-blur-[5px]" wire:click="closePostPreview"></div>

        <div class="relative z-10 flex max-h-[calc(100dvh-2rem)] w-full max-w-5xl flex-col overflow-hidden rounded-[1.35rem] border shadow-[0_32px_90px_-36px_rgba(15,23,42,0.42)]" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: var(--theme-surface-base);">
            <div class="flex items-start justify-between gap-4 border-b px-5 py-4 sm:px-6" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Preview post') }}</p>
                    <h2 class="mt-1 text-[1.1rem] font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">
                        {{ $postPreviewMeta['title'] ?: $postPreviewAccount->display_name }}
                    </h2>
                    <p class="mt-2 text-sm" style="color: var(--theme-muted-text-color);">
                        {{ $postPreviewAccount->display_name }}
                        @if ($postPreviewAccount->username)
                            <span>&middot; {{ '@'.$postPreviewAccount->username }}</span>
                        @endif
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    @if (!empty($postPreviewMeta['post_url']))
                        <a
                            href="{{ $postPreviewMeta['post_url'] }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex h-10 items-center justify-center rounded-[0.9rem] border px-4 text-sm font-semibold transition hover:bg-slate-900/5"
                            style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-header-text-color);"
                        >
                            {{ __('View Post') }}
                        </a>
                    @endif

                    <button
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-full border transition hover:bg-slate-900/5"
                        style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color);"
                        wire:click="closePostPreview"
                    >
                        <i class="fa-light fa-xmark"></i>
                    </button>
                </div>
            </div>

            <div class="overflow-y-auto px-5 py-6 sm:px-6">
                <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
                    <div class="flex min-w-0 items-start justify-center rounded-[1.15rem] border px-4 py-6" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-overlay) 96%, transparent);">
                        @php($previewView = app(\Modules\AppPublishing\Support\PublishingPreviewRegistry::class)->get((string) $postPreviewAccount->provider_key, 'apppublishing::livewire.partials.network-preview-generic'))
                        @include($previewView, [
                            'composer' => $postPreviewComposer,
                            'composerAccount' => $postPreviewAccount,
                            'composerProvider' => $postPreviewProvider,
                            'composerCampaigns' => $composerCampaigns,
                        ])
                    </div>

                    <aside class="space-y-4">
                        <div class="rounded-[1rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-overlay) 96%, transparent);">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em]" style="color: var(--theme-muted-text-color);">{{ __('Network') }}</p>
                            <div class="mt-3 flex items-center gap-3">
                                <span class="inline-flex h-11 w-11 items-center justify-center rounded-[0.95rem]" style="background-color: rgba(var(--theme-accent-rgb), 0.08); color: {{ $postPreviewProvider['color'] ?? 'var(--theme-accent)' }};">
                                    <i class="{{ $postPreviewProvider['icon'] ?? 'fa-light fa-share-nodes' }} text-lg"></i>
                                </span>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $postPreviewProvider['label'] ?? str((string) $postPreviewAccount->provider_key)->headline() }}</p>
                                    <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ $postPreviewAccount->display_name }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-[1rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-overlay) 96%, transparent);">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em]" style="color: var(--theme-muted-text-color);">{{ __('Caption') }}</p>
                            <p class="mt-3 whitespace-pre-line text-sm leading-7" style="color: var(--theme-header-text-color);">{{ $postPreviewComposer['caption'] ?: __('No caption') }}</p>
                        </div>

                        <div class="rounded-[1rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-overlay) 96%, transparent);">
                            <p class="text-xs font-semibold uppercase tracking-[0.16em]" style="color: var(--theme-muted-text-color);">{{ __('Media') }}</p>
                            <p class="mt-3 text-sm" style="color: var(--theme-muted-text-color);">
                                {{ trans_choice(':count file selected|:count files selected', count((array) ($postPreviewComposer['media_items'] ?? [])), ['count' => count((array) ($postPreviewComposer['media_items'] ?? []))]) }}
                            </p>
                            @if (!empty($postPreviewMeta['open_error']))
                                <p class="mt-3 text-sm" style="color: var(--theme-danger-color);">{{ $postPreviewMeta['open_error'] }}</p>
                            @endif
                        </div>
                    </aside>
                </div>
            </div>
        </div>
    </div>
    @endif

    <div x-cloak x-show="discardComposerOpen" class="fixed inset-0 z-[180] flex items-center justify-center p-4" style="background:rgba(15,23,42,.5)" role="alertdialog" aria-modal="true" aria-labelledby="discard-composer-title" x-on:keydown.escape.stop.prevent="discardComposerOpen = false" x-on:keydown.tab.prevent="$event.target === $refs.keepEditing ? $refs.discardChanges.focus() : $refs.keepEditing.focus()">
        <div class="w-full max-w-md rounded-2xl border p-6 shadow-xl" style="background:var(--theme-surface-base);border-color:var(--theme-border-color);">
            <h2 id="discard-composer-title" class="text-xl font-semibold" style="color:var(--theme-header-text-color);">{{ __('Discard unsaved changes?') }}</h2>
            <p class="mt-3 text-sm leading-6" style="color:var(--theme-muted-text-color);">{{ __('Your post has not been saved. Keep editing to continue working on it.') }}</p>
            <div class="mt-6 flex flex-wrap justify-end gap-3">
                <button x-ref="keepEditing" type="button" x-on:click="discardComposerOpen = false" class="rounded-xl border px-4 py-3 text-sm font-semibold" style="border-color:var(--theme-border-color);color:var(--theme-header-text-color);">{{ __('Keep editing') }}</button>
                <button x-ref="discardChanges" type="button" x-on:click="closeComposerLocal(true)" class="rounded-xl px-4 py-3 text-sm font-semibold text-white" style="background:var(--theme-danger-color);">{{ __('Discard changes') }}</button>
            </div>
        </div>
    </div>

    <div class="{{ $composerOpen ? 'fixed' : 'hidden' }} inset-0 z-[140]" x-data="{ mobilePreviewOpen: false, mobileMediaOpen: false }" x-on:attached-media:open-mobile.window="mobileMediaOpen = true">
        <div class="absolute inset-0 bg-slate-950/45 backdrop-blur-[3px]" x-on:click="closeComposerLocal()"></div>

        <div class="absolute inset-3 flex min-h-0 flex-col overflow-hidden rounded-[1.35rem] border shadow-[0_32px_90px_-36px_rgba(15,23,42,0.42)]" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: var(--theme-surface-base);">
            <div class="flex items-center justify-between gap-4 border-b px-5 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Schedule Composer') }}</p>
                    <h2 class="mt-1 text-[1.1rem] font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">{{ __('New Publishing Item') }}</h2>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-full border transition hover:bg-slate-900/5 xl:hidden"
                        style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color);"
                        x-on:click="mobilePreviewOpen = !mobilePreviewOpen"
                        x-bind:aria-label="mobilePreviewOpen ? '{{ __('Hide preview') }}' : '{{ __('Show preview') }}'"
                    >
                        <i class="fa-light" x-bind:class="mobilePreviewOpen ? 'fa-eye-slash' : 'fa-eye'"></i>
                    </button>

                    <button
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-full border transition hover:bg-slate-900/5 disabled:opacity-70 disabled:cursor-not-allowed"
                        style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color);"
                        x-bind:disabled="composerClosing || composerSavingAction !== ''"
                        aria-label="{{ __('Close composer') }}" x-on:click="closeComposerLocal()"
                    >
                        <template x-if="composerClosing">
                            <i class="fa-light fa-loader animate-spin"></i>
                        </template>
                        <template x-if="!composerClosing">
                            <i class="fa-light fa-xmark"></i>
                        </template>
                    </button>
                </div>
            </div>

            <div class="grid min-h-0 flex-1 xl:grid-cols-[minmax(0,1fr)_30rem] 2xl:grid-cols-[24rem_minmax(0,1fr)_32rem]">
                <aside class="hidden min-h-0 border-b 2xl:block 2xl:border-b-0 2xl:border-r" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                    <div class="h-full min-h-0">
                        @if ($composerMediaBrowserReady)
                            <x-ui.media-browser
                                wire:key="composer-media-browser-{{ (int) ($composer['media_refresh_token'] ?? 0) }}"
                                wire:model.live="composer.media_items"
                                context="portal"
                                layout="library"
                                :error="$errors->first('composer.media_items')"
                                type="all"
                                :multiple="true"
                                :value="$composer['media_items'] ?? []"
                                :library-title="__('Media')"
                                :show-library-header="false"
                                :frameless="true"
                                :compact-toolbar="true"
                            />
                        @else
                            <div wire:init="loadComposerMediaBrowser" class="flex h-full min-h-[16rem] items-center justify-center">
                                <div class="inline-flex items-center gap-2 text-sm font-medium" style="color: var(--theme-muted-text-color);">
                                    <i class="fa-light fa-loader animate-spin"></i>
                                    <span>{{ __('Loading media library...') }}</span>
                                </div>
                            </div>
                        @endif
                    </div>
                </aside>

                <section class="min-h-0 min-w-0 overflow-y-auto">
                    <div class="mx-auto w-full max-w-[68rem] space-y-5 px-5 py-5 pb-8">
                        <div class="space-y-2.5">
                            <?php
                            $composerTeam = \Modules\AppTeams\Support\TeamWorkspaceAccess::activeTeam(auth()->user());
                            $composerCanViewChannels = auth()->user()
                                && \Modules\AppTeams\Support\TeamWorkspaceAccess::hasPermission(auth()->user(), 'channel.view', $composerTeam);
                            $composerCanManageChannels = auth()->user()
                                && \Modules\AppTeams\Support\TeamWorkspaceAccess::hasPermission(auth()->user(), 'channel.manage', $composerTeam);
                            $channelProviderRegistry = collect(channel_provider_cards())->keyBy('key');
                            $composerChannelOptions = $composerAccounts
                                ->map(function ($account) use ($channelProviderRegistry) {
                                    $provider = $channelProviderRegistry->get((string) $account->provider_key, []);
                                    $capability = channel_capability((string) ($account->capability_key ?: $account->provider_key));
                                    $providerLabel = (string) data_get($provider, 'label', str($account->provider_key)->headline());
                                    $capabilityLabel = (string) data_get($capability, 'title', data_get($capability, 'label', __('Channel')));

                                    return [
                                        'key' => (string) $account->id,
                                        'label' => (string) $account->display_name,
                                        'subtitle' => trim($providerLabel.' '.str($capabilityLabel)->lower()),
                                        'avatarUrl' => (string) ($account->avatar_url ?? ''),
                                        'providerKey' => (string) $account->provider_key,
                                        'providerLabel' => $providerLabel,
                                        'providerIcon' => (string) data_get($provider, 'icon', ''),
                                        'providerColor' => (string) data_get($provider, 'color', ''),
                                        'isAddon' => channel_capability_is_marketplace_addon($capability),
                                    ];
                                })
                                ->values()
                                ->all();

                            $composerChannelNetworks = $channelProviderRegistry
                                ->only($composerAccounts->pluck('provider_key')->filter()->unique()->values()->all())
                                ->map(fn ($provider) => [
                                    'key' => (string) ($provider['key'] ?? ''),
                                    'label' => (string) ($provider['label'] ?? ''),
                                    'icon' => (string) ($provider['icon'] ?? ''),
                                    'color' => (string) ($provider['color'] ?? ''),
                                    'isAddon' => (bool) ($provider['has_addon_capabilities'] ?? false),
                                ])
                                ->filter(fn ($provider) => $provider['key'] !== '' && $provider['label'] !== '')
                                ->values()
                                ->all();
                            ?>

                            <x-ui.channel-selector
                                name="composer_account_ids"
                                wire-model="composer.account_ids"
                                :options="$composerChannelOptions"
                                :network-options="$composerChannelNetworks"
                                :group-options="$composerAccountGroups"
                                :selected="collect($composer['account_ids'] ?? [])->map(fn ($id) => (string) $id)->all()"
                                :label="__('Channel')"
                                :error="$errors->first('composer.account_ids')"
                                :placeholder="__('Choose one or more accounts')"
                                :empty-label="__('No matching channels found.')"
                                :multiple="true"
                                :live="true"
                                :sync-on-close="false"
                                :connect-href="$composerCanViewChannels && $composerCanManageChannels ? route('portal.channels') : null"
                                :connect-label="__('Connect a channel')"
                            />
                        </div>

                        <div data-no-loading>
                            <x-ui.emoji-textarea
                                wire:key="composer-caption-field"
                                id="composer-caption-textarea"
                                wire:model.live.debounce.250ms="composer.caption"
                                x-on:input="localPreviewCaption = $event.target.value"
                                x-on:publishing-ai-caption-updated.window="
                                    const nextCaption = String($event.detail?.caption || '');
                                    if ($event.detail?.animate) {
                                        animateComposerCaption(nextCaption);
                                    } else {
                                        applyComposerCaption(nextCaption);
                                    }
                                "
                                :label="__('Caption')"
                                :error="$errors->first('composer.caption')"
                                trigger-position="inside-top-right"
                                picker-align="right"
                                picker-title="{{ __('Post caption') }}"
                                rows="5"
                                class="[&>div>div>textarea]:min-h-[9rem]"
                                placeholder="{{ __('Write the main caption, CTA, hashtags, and any publishing notes for this slot...') }}"
                            >{{ $composer['caption'] ?? '' }}</x-ui.emoji-textarea>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-3 rounded-[1rem] border px-4 py-3" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-overlay) 96%, transparent);">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-ui.button type="button" variant="outline" x-on:click="captionPickerOpen = true" size="sm" data-no-loading>
                                    <i class="fa-light fa-wand-magic-sparkles"></i>
                                    <span>{{ __('Get Caption') }}</span>
                                </x-ui.button>

                                @if ($composerCanShortenUrls)
                                    <x-ui.button type="button" variant="outline" wire:click="shortenComposerLinks" wire:loading.attr="disabled" wire:target="shortenComposerLinks" size="sm" data-no-loading>
                                        <i class="fa-light fa-link"></i>
                                        <span wire:loading.remove wire:target="shortenComposerLinks">{{ __('Shorten Links') }}</span>
                                        <span wire:loading wire:target="shortenComposerLinks">{{ __('Shortening...') }}</span>
                                    </x-ui.button>
                                @endif

                                <x-ui.button type="button" variant="outline" x-on:click="saveCaptionOpen = true" size="sm" data-no-loading>
                                    <i class="fa-light fa-floppy-disk"></i>
                                    <span>{{ __('Save Caption') }}</span>
                                </x-ui.button>
                            </div>

                            <div class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-semibold" style="background-color: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-muted-text-color);">
                                <i class="fa-light fa-input-text"></i>
                                <span x-text="`${Array.from(String(localPreviewCaption || '')).length}/2200 {{ __('characters') }}`"></span>
                            </div>
                        </div>

                        <template x-teleport="body">
                            <div
                                x-cloak
                                x-show="saveCaptionOpen"
                                class="fixed inset-0 z-[165] flex items-center justify-center p-6"
                                x-on:keydown.escape.window="saveCaptionOpen = false"
                            >
                                <div class="absolute inset-0 bg-slate-950/45 backdrop-blur-[4px]" x-on:click="saveCaptionOpen = false"></div>

                                <div
                                    x-show="saveCaptionOpen"
                                    x-transition.opacity.scale.95
                                    class="relative w-full max-w-xl"
                                >
                                    <div class="overflow-hidden rounded-[1.2rem] border shadow-[0_32px_90px_-36px_rgba(15,23,42,0.42)]" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: var(--theme-surface-base);">
                                        <div class="border-b px-5 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                                            <div class="flex items-start justify-between gap-4">
                                                <div>
                                                    <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Caption Library') }}</p>
                                                    <h3 class="mt-1 text-[1.05rem] font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">{{ __('Save caption') }}</h3>
                                                    <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Choose a name and source type before adding this caption to the library.') }}</p>
                                                </div>

                                                <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-full border transition hover:bg-slate-900/5" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color);" x-on:click="saveCaptionOpen = false">
                                                    <i class="fa-light fa-xmark"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="space-y-4 px-5 py-5">
                                            <x-ui.input
                                                wire:model.defer="composer.caption_library_name"
                                                :label="__('Caption name')"
                                                :error="$errors->first('composer.caption_library_name')"
                                                :placeholder="__('Product teaser set')"
                                            />

                                            <x-ui.select
                                                wire:model.defer="composer.caption_library_source_type"
                                                :label="__('Type')"
                                                :error="$errors->first('composer.caption_library_source_type')"
                                            >
                                                <option value="manual">{{ __('Manual') }}</option>
                                                <option value="ai">{{ __('AI') }}</option>
                                            </x-ui.select>
                                        </div>

                                        <div class="border-t px-5 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-overlay) 96%, transparent);">
                                            <div class="flex items-center justify-end gap-3">
                                                <x-ui.button type="button" variant="outline" x-on:click="saveCaptionOpen = false">
                                                    {{ __('Cancel') }}
                                                </x-ui.button>
                                                <x-ui.button type="button" wire:click="saveComposerCaption" wire:loading.attr="disabled" wire:target="saveComposerCaption" data-no-loading>
                                                    <span wire:loading.remove wire:target="saveComposerCaption">{{ __('Save Caption') }}</span>
                                                    <span wire:loading wire:target="saveComposerCaption">{{ __('Saving...') }}</span>
                                                </x-ui.button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <template x-teleport="body">
                            <div
                                x-cloak
                                x-show="captionPickerOpen"
                                class="fixed inset-0 z-[160] flex justify-end"
                                x-on:keydown.escape.window="captionPickerOpen = false"
                            >
                                <div class="absolute inset-0 bg-slate-950/45 backdrop-blur-[4px]" x-on:click="captionPickerOpen = false"></div>

                                <div
                                    x-show="captionPickerOpen"
                                    x-transition:enter="transform transition ease-out duration-220"
                                    x-transition:enter-start="translate-x-full opacity-0"
                                    x-transition:enter-end="translate-x-0 opacity-100"
                                    x-transition:leave="transform transition ease-in duration-180"
                                    x-transition:leave-start="translate-x-0 opacity-100"
                                    x-transition:leave-end="translate-x-full opacity-0"
                                    class="relative flex h-full w-full max-w-[34rem] flex-col border-l shadow-[-28px_0_70px_-38px_rgba(15,23,42,0.42)]"
                                    style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: var(--theme-surface-base);"
                                >
                                    <div class="border-b px-5 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                                        <div class="flex items-start justify-between gap-4">
                                            <div>
                                                <p class="text-[11px] font-semibold uppercase tracking-[0.22em]" style="color: var(--theme-muted-text-color);">{{ __('Caption Library') }}</p>
                                                <h3 class="mt-1 text-[1.05rem] font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">{{ __('Choose a caption') }}</h3>
                                                <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Select a saved caption and apply it to the composer.') }}</p>
                                            </div>

                                            <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-full border transition hover:bg-slate-900/5" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color);" x-on:click="captionPickerOpen = false">
                                                <i class="fa-light fa-xmark"></i>
                                            </button>
                                        </div>

                                        <div class="mt-4 space-y-3">
                                            <x-ui.input x-model="captionPickerSearch" :placeholder="__('Search caption library')" />
                                        </div>
                                    </div>

                                    <div class="flex-1 overflow-y-auto px-5 py-5">
                                        <div class="space-y-3" x-show="filteredCaptionLibrary().length > 0">
                                            <template x-for="caption in filteredCaptionLibrary()" :key="'composer-caption-'+caption.id">
                                                <button
                                                    type="button"
                                                    class="block w-full rounded-[1rem] border px-4 py-4 text-left transition hover:bg-slate-900/5"
                                                    style="border-color: rgba(var(--theme-border-color-rgb), 0.58); background-color: color-mix(in srgb, var(--theme-surface-overlay) 96%, transparent);"
                                                    x-on:click="selectComposerLibraryCaption(caption)"
                                                >
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div class="min-w-0">
                                                            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);" x-text="caption.name"></p>
                                                            <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);" x-text="caption.content.length > 220 ? `${caption.content.slice(0, 220)}...` : caption.content"></p>
                                                        </div>
                                                        <span class="shrink-0 text-[11px] font-semibold uppercase tracking-[0.16em]" style="color: var(--theme-accent);" x-text="caption.sourceType"></span>
                                                    </div>

                                                    <div class="mt-3 flex flex-wrap items-center gap-2">
                                                        <template x-for="tag in (caption.tags || []).slice(0, 4)" :key="`caption-tag-${caption.id}-${tag}`">
                                                            <span class="rounded-full px-2 py-1 text-[11px]" style="background-color: rgba(var(--theme-accent-rgb), 0.08); color: var(--theme-accent);" x-text="tag"></span>
                                                        </template>
                                                        <span class="text-[11px] font-medium" style="color: var(--theme-muted-text-color);" x-text="caption.updatedLabel"></span>
                                                    </div>
                                                </button>
                                            </template>
                                        </div>

                                        <div x-cloak x-show="filteredCaptionLibrary().length === 0" class="flex h-full min-h-[16rem] items-center justify-center">
                                            <div class="max-w-xs text-center">
                                                <i class="fa-light fa-books text-3xl" style="color: var(--theme-muted-text-color);"></i>
                                                <p class="mt-4 text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('No matching captions') }}</p>
                                                <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Try another keyword to see more saved captions.') }}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </template>

                        <div class="space-y-3 rounded-[1rem] border p-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-overlay) 96%, transparent);">
                            <div class="flex flex-wrap items-center justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('AI composer tools') }}</p>
                                    <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Generate captions, repurpose drafts, review quality, and pull best posting windows without leaving the composer.') }}</p>
                                </div>
                                <a href="{{ route('portal.ai-studio') }}" wire:navigate class="text-xs font-semibold uppercase tracking-[0.16em]" style="color: var(--theme-accent);">{{ __('Open studio') }}</a>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <x-ui.button type="button" variant="outline" wire:click="generateComposerCaption" wire:loading.attr="disabled" wire:target="generateComposerCaption" size="sm" data-no-loading>
                                    <i class="fa-light fa-wand-magic-sparkles"></i>
                                    <span wire:loading.remove wire:target="generateComposerCaption">{{ __('AI Caption') }}</span>
                                    <span wire:loading wire:target="generateComposerCaption">{{ __('Generating...') }}</span>
                                </x-ui.button>
                                <x-ui.button type="button" variant="outline" wire:click="generateComposerImage" wire:loading.attr="disabled" wire:target="generateComposerImage" size="sm" data-no-loading>
                                    <i class="fa-light fa-image"></i>
                                    <span wire:loading.remove wire:target="generateComposerImage">{{ __('AI Image') }}</span>
                                    <span wire:loading wire:target="generateComposerImage">{{ __('Creating image...') }}</span>
                                </x-ui.button>
                                <x-ui.button type="button" variant="outline" wire:click="repurposeComposer" wire:loading.attr="disabled" wire:target="repurposeComposer" size="sm" data-no-loading>
                                    <i class="fa-light fa-code-branch"></i>
                                    <span wire:loading.remove wire:target="repurposeComposer">{{ __('Repurpose') }}</span>
                                    <span wire:loading wire:target="repurposeComposer">{{ __('Repurposing...') }}</span>
                                </x-ui.button>
                                <x-ui.button type="button" variant="outline" wire:click="reviewComposer" wire:loading.attr="disabled" wire:target="reviewComposer" size="sm" data-no-loading>
                                    <i class="fa-light fa-shield-check"></i>
                                    <span wire:loading.remove wire:target="reviewComposer">{{ __('Review') }}</span>
                                    <span wire:loading wire:target="reviewComposer">{{ __('Reviewing...') }}</span>
                                </x-ui.button>
                                <x-ui.button type="button" variant="outline" wire:click="suggestComposerBestTimes" wire:loading.attr="disabled" wire:target="suggestComposerBestTimes" size="sm" data-no-loading>
                                    <i class="fa-light fa-clock"></i>
                                    <span wire:loading.remove wire:target="suggestComposerBestTimes">{{ __('Best time') }}</span>
                                    <span wire:loading wire:target="suggestComposerBestTimes">{{ __('Finding...') }}</span>
                                </x-ui.button>
                            </div>

                            <div wire:loading.flex wire:target="generateComposerCaption,generateComposerImage,repurposeComposer,reviewComposer,suggestComposerBestTimes" class="items-center gap-2 rounded-[0.9rem] border px-3 py-2 text-sm" style="border-color: rgba(var(--theme-border-color-rgb), 0.52); background-color: rgba(var(--theme-accent-rgb), 0.06); color: var(--theme-muted-text-color);">
                                <i class="fa-light fa-loader animate-spin" style="color: var(--theme-accent);"></i>
                                <span>{{ __('AI is processing your request...') }}</span>
                            </div>

                            @if (!empty($composer['ai_tags']))
                                <div class="flex flex-wrap gap-2">
                                    @foreach (($composer['ai_tags'] ?? []) as $tag)
                                        <span class="rounded-full px-2 py-1 text-[11px]" style="background-color: rgba(var(--theme-accent-rgb), 0.1); color: var(--theme-accent);">{{ $tag }}</span>
                                    @endforeach
                                </div>
                            @endif

                            @if (!empty($composer['ai_caption_variants']))
                                <div class="space-y-2">
                                    <p class="text-xs font-semibold uppercase tracking-[0.16em]" style="color: var(--theme-muted-text-color);">{{ __('Generated variants') }}</p>
                                    @foreach (($composer['ai_caption_variants'] ?? []) as $index => $variant)
                                        <button
                                            type="button"
                                            wire:click="applyComposerCaptionVariant({{ $index }})"
                                            class="block w-full rounded-[0.95rem] border px-3 py-3 text-left transition hover:bg-slate-900/5"
                                            style="border-color: rgba(var(--theme-border-color-rgb), 0.52); background-color: color-mix(in srgb, var(--theme-surface-base) 94%, transparent);"
                                        >
                                            <div class="flex items-center justify-between gap-3">
                                                <span class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Variant :number', ['number' => $loop->iteration]) }}</span>
                                                <span class="text-[11px] uppercase tracking-[0.16em]" style="color: var(--theme-accent);">{{ __('Use this') }}</span>
                                            </div>
                                            <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ \Illuminate\Support\Str::limit((string) ($variant['caption'] ?? ''), 220) }}</p>
                                        </button>
                                    @endforeach
                                </div>
                            @endif

                            @if (!empty($composer['ai_repurpose_items']))
                                <div class="space-y-2">
                                    <p class="text-xs font-semibold uppercase tracking-[0.16em]" style="color: var(--theme-muted-text-color);">{{ __('Repurpose variants') }}</p>
                                    @foreach (($composer['ai_repurpose_items'] ?? []) as $index => $variant)
                                        <button
                                            type="button"
                                            wire:click="applyRepurposeVariant({{ $index }})"
                                            class="block w-full rounded-[0.95rem] border px-3 py-3 text-left transition hover:bg-slate-900/5"
                                            style="border-color: rgba(var(--theme-border-color-rgb), 0.52); background-color: color-mix(in srgb, var(--theme-surface-base) 94%, transparent);"
                                        >
                                            <div class="flex items-center justify-between gap-3">
                                                <span class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $variant['title'] ?: strtoupper((string) ($variant['target'] ?? 'Variant')) }}</span>
                                                <span class="text-[11px] uppercase tracking-[0.16em]" style="color: var(--theme-accent);">{{ $variant['format'] ?? __('Variant') }}</span>
                                            </div>
                                            <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ \Illuminate\Support\Str::limit((string) ($variant['caption'] ?? ''), 180) }}</p>
                                        </button>
                                    @endforeach
                                </div>
                            @endif

                            @if (!empty($composer['ai_review']))
                                <div class="rounded-[0.95rem] border p-3" style="border-color: rgba(var(--theme-border-color-rgb), 0.52); background-color: color-mix(in srgb, var(--theme-surface-base) 94%, transparent);">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $composer['ai_review']['verdict'] ?? __('AI review') }}</p>
                                        <span class="text-xs uppercase tracking-[0.16em]" style="color: var(--theme-accent);">{{ $composer['ai_review']['score'] ?? 0 }}/100</span>
                                    </div>
                                    @foreach (array_slice((array) ($composer['ai_review']['fixes'] ?? []), 0, 2) as $fix)
                                        <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ $fix }}</p>
                                    @endforeach
                                </div>
                            @endif

                            @if (!empty($composer['review_status']))
                                <div class="rounded-[0.95rem] border p-3" style="border-color: rgba(var(--theme-border-color-rgb), 0.52); background-color: color-mix(in srgb, var(--theme-surface-base) 94%, transparent);">
                                    <div class="flex items-center justify-between gap-3">
                                        <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Approval status') }}</p>
                                        <span
                                            class="rounded-full px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.14em]"
                                            style="{{ ($composer['review_status'] ?? '') === 'pending'
                                                ? 'background-color: rgba(245, 158, 11, 0.14); color: #d97706;'
                                                : (($composer['review_status'] ?? '') === 'rejected'
                                                    ? 'background-color: rgba(239, 68, 68, 0.12); color: #dc2626;'
                                                    : 'background-color: rgba(16, 185, 129, 0.14); color: #059669;') }}"
                                        >
                                            {{ $composer['review_badge'] ?: str((string) $composer['review_status'])->headline() }}
                                        </span>
                                    </div>
                                    @if (!empty($composer['review_submitted_at']) || !empty($composer['review_submitted_by']))
                                        <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">
                                            {{ __('Submitted') }}
                                            @if (!empty($composer['review_submitted_by']))
                                                {{ __('by :name', ['name' => $composer['review_submitted_by']]) }}
                                            @endif
                                            @if (!empty($composer['review_submitted_at']))
                                                {{ __('on :date', ['date' => $composer['review_submitted_at']]) }}
                                            @endif
                                        </p>
                                    @endif
                                    @if (!empty($composer['review_decided_at']) || !empty($composer['review_decided_by']))
                                        <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">
                                            {{ __('Last decision') }}
                                            @if (!empty($composer['review_decided_by']))
                                                {{ __('by :name', ['name' => $composer['review_decided_by']]) }}
                                            @endif
                                            @if (!empty($composer['review_decided_at']))
                                                {{ __('on :date', ['date' => $composer['review_decided_at']]) }}
                                            @endif
                                        </p>
                                    @endif
                                    @if (!empty($composer['review_note']))
                                        <p class="mt-2 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ $composer['review_note'] }}</p>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <x-ui.attached-media
                            wire:key="composer-attached-media-{{ (int) ($composer['media_refresh_token'] ?? 0) }}-{{ md5(json_encode($composer['media_items'] ?? [])) }}"
                            wire-model="composer.media_items"
                            :value="$composer['media_items'] ?? []"
                            :error="$errors->first('composer.media_items')"
                            :label="__('Attached Media')"
                            :description="__('Selected assets from the media library will attach to this scheduled post.')"
                            :empty-label="__('Drag media here or choose files from the media library.')"
                            :mobile-button-label="__('Select media')"
                            mobile-open-event="attached-media:open-mobile"
                        />

                        @foreach ($composerOptionAccounts as $optionAccount)
                            @php($providerKey = (string) $optionAccount->provider_key)
                            @php($composerOptionsView = app(\Modules\AppPublishing\Support\PublishingOptionsRegistry::class)->get($providerKey))
                            @php($composerNetworkConfig = $this->networkConfigForProvider($providerKey))
                            @if ($composerOptionsView)
                                <div class="rounded-[1rem] border" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-overlay) 96%, transparent);">
                                    <div class="border-b px-4 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                                        <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $composerNetworkConfig['label'] }}</p>
                                    </div>

                                    <div data-no-loading>
                                        @include($composerOptionsView, ['providerKey' => $providerKey])
                                    </div>
                                </div>
                            @endif
                        @endforeach

                        <div class="space-y-4">
                            <div class="space-y-2.5">
                                <div class="flex items-center justify-between gap-3">
                                    <x-ui.label>{{ __('Campaign') }}</x-ui.label>
                                    <a href="{{ route('portal.publishing.campaigns') }}" wire:navigate class="text-xs font-semibold" style="color: var(--theme-accent);">{{ __('Manage') }}</a>
                                </div>
                                <p class="text-sm" style="color: var(--theme-muted-text-color);">
                                    {{ __('Track and report on your social marketing campaigns with the Campaign Planner, notes and more.') }}
                                </p>
                                <x-ui.select wire:model.defer="composer.campaign_id">
                                    <option value="">{{ __('No campaign') }}</option>
                                    @foreach ($composerCampaigns as $campaign)
                                        <option value="{{ $campaign->id }}">{{ $campaign->name }}{{ $campaign->status !== 'active' ? ' ('.str($campaign->status)->headline().')' : '' }}</option>
                                    @endforeach
                                </x-ui.select>
                            </div>

                            <div class="space-y-2.5">
                                <div class="flex items-center justify-between gap-3">
                                    <x-ui.label>{{ __('Labels') }}</x-ui.label>
                                    <a href="{{ route('portal.publishing.labels') }}" wire:navigate class="text-xs font-semibold" style="color: var(--theme-accent);">{{ __('Manage') }}</a>
                                </div>
                                <x-ui.tag-selector
                                    name="composer_label_ids"
                                    wire-model="composer.label_ids"
                                    :options="$composerLabels->map(fn ($label) => ['key' => (string) $label->id, 'label' => $label->name])->all()"
                                    :selected="collect($composer['label_ids'] ?? [])->map(fn ($id) => (string) $id)->all()"
                                    :description="__('Use Labels to organize, filter and report on your content.')"
                                    :placeholder="__('Search or pick labels...')"
                                    :empty-label="__('No matching labels found.')"
                                />
                            </div>
                        </div>

                        <x-ui.textarea wire:model.defer="composer.notes" :label="__('Internal notes')" :error="$errors->first('composer.notes')" rows="4" placeholder="{{ __('Approval notes, coordination reminders, or media instructions...') }}">{{ $composer['notes'] ?? '' }}</x-ui.textarea>

                        @php($scheduleMode = (string) ($composer['schedule_mode'] ?? 'specific_days_times'))
                        @php($composerRepeatRule = (string) ($composer['repeat_rule'] ?? 'none'))
                        @php($composerRepeatUntil = (string) ($composer['repeat_until'] ?? ''))
                        @php($composerRepeatDays = collect((array) ($composer['repeat_days'] ?? []))->map(fn ($day) => strtolower((string) $day))->all())
                        <div
                            class="rounded-[1rem] border"
                            style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.03);"
                            x-data="{
                                scheduleMode: @js($scheduleMode),
                                repeatRule: @js($composerRepeatRule),
                                repeatUntil: @js($composerRepeatUntil),
                                repeatDays: @js($composerRepeatDays),
                                async syncRepeatDays() {
                                    await $wire.set('composer.repeat_days', Array.isArray(this.repeatDays) ? this.repeatDays : [], true);
                                },
                                async toggleRepeatDay(day) {
                                    const key = String(day || '').toLowerCase();
                                    const order = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
                                    const nextDays = Array.isArray(this.repeatDays) ? [...this.repeatDays] : [];
                                    const index = nextDays.indexOf(key);

                                    if (index >= 0) {
                                        nextDays.splice(index, 1);
                                    } else {
                                        nextDays.push(key);
                                    }

                                    this.repeatDays = nextDays
                                        .filter((item) => order.includes(item))
                                        .sort((left, right) => order.indexOf(left) - order.indexOf(right));

                                    await this.syncRepeatDays();
                                },
                                async updateRepeatRule() {
                                    if (this.repeatRule === 'none') {
                                        this.repeatDays = [];
                                    } else if (this.repeatRule === 'weekday') {
                                        this.repeatDays = ['mon', 'tue', 'wed', 'thu', 'fri'];
                                    }

                                    await $wire.set('composer.repeat_rule', this.repeatRule, true);
                                    await this.syncRepeatDays();
                                },
                            }"
                        >
                            <div class="border-b px-4 py-3" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                                <div class="flex items-center justify-between gap-4">
                                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('When to post') }}</p>
                                    <div class="min-w-[16rem]" data-no-loading>
                                        <x-ui.select
                                            x-model="scheduleMode"
                                            x-on:change="$wire.set('composer.schedule_mode', scheduleMode, false)"
                                        >
                                            <option value="immediately">{{ __('Immediately') }}</option>
                                            <option value="specific_days_times">{{ __('Specific Days & Times') }}</option>
                                        </x-ui.select>
                                    </div>
                                </div>
                            </div>
                            <div class="space-y-4 px-4 py-4">
                                <div
                                    x-show="scheduleMode === 'immediately'"
                                    x-cloak
                                    class="rounded-[0.95rem] border px-4 py-3 text-sm"
                                    style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-accent-rgb), 0.04); color: var(--theme-muted-text-color);"
                                >
                                        {{ __('This post will be queued to publish immediately after you confirm.') }}
                                </div>

                                <div x-show="scheduleMode === 'specific_days_times'" x-cloak class="space-y-3">
                                        @if (!empty($composer['ai_best_times']))
                                            <div class="rounded-[0.95rem] border p-3" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-base) 94%, transparent);">
                                                <div class="flex items-center justify-between gap-3">
                                                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Suggested windows') }}</p>
                                                    <span class="text-xs uppercase tracking-[0.16em]" style="color: var(--theme-accent);">{{ __('Local history') }}</span>
                                                </div>
                                                <div class="mt-3 flex flex-wrap gap-2">
                                                    @foreach (($composer['ai_best_times'] ?? []) as $index => $slot)
                                                        <button
                                                            type="button"
                                                            wire:click="applyComposerBestTime({{ $index }})"
                                                            class="inline-flex items-center gap-2 rounded-full border px-3 py-2 text-xs font-semibold transition hover:bg-slate-900/5"
                                                            style="border-color: rgba(var(--theme-border-color-rgb), 0.58); color: var(--theme-header-text-color); background-color: color-mix(in srgb, var(--theme-surface-overlay) 92%, transparent);"
                                                        >
                                                            <span>{{ $slot['label'] ?? '' }}</span>
                                                            <span style="color: var(--theme-accent);">{{ $slot['confidence'] ?? 0 }}%</span>
                                                        </button>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                        <template x-for="(slotValue, slotIndex) in localScheduleSlots" :key="`schedule-slot-${slotIndex}`">
                                            <div
                                                class="grid items-start gap-3"
                                                x-bind:class="localScheduleSlots.length > 1 ? 'grid-cols-[minmax(0,1fr)_3rem]' : 'grid-cols-1'"
                                            >
                                                <div class="space-y-2.5">
                                                    <template x-if="slotIndex === 0">
                                                        <div class="space-y-2.5">
                                                            <label class="text-sm font-medium" style="color: var(--theme-header-text-color);">{{ __('Schedule slot') }}</label>
                                                            <template x-if="@js((string) $errors->first('composer.schedule_slots')) !== ''">
                                                                <p class="text-sm font-medium" style="color: var(--theme-danger-color);">{{ $errors->first('composer.schedule_slots') }}</p>
                                                            </template>
                                                        </div>
                                                    </template>

                                                    <div data-no-loading>
                                                        <x-ui.datetime-picker
                                                            x-model="localScheduleSlots[slotIndex]"
                                                            value=""
                                                            class="flex-1"
                                                            :label="null"
                                                            picker-align="auto"
                                                            picker-position="top"
                                                        />
                                                    </div>
                                                </div>

                                                <template x-if="localScheduleSlots.length > 1">
                                                    <button
                                                        type="button"
                                                        x-on:click="removeLocalScheduleSlot(slotIndex)"
                                                        class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-[0.75rem] border transition hover:bg-slate-900/5"
                                                        x-bind:class="slotIndex === 0 ? 'mt-[2.15rem]' : 'mt-0'"
                                                        style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color);"
                                                    >
                                                        <i class="fa-light fa-trash-can"></i>
                                                    </button>
                                                </template>
                                            </div>
                                        </template>
                                        <div class="grid gap-3 lg:grid-cols-[minmax(0,16rem)_minmax(0,1fr)]">
                                            <div data-no-loading>
                                                <x-ui.select
                                                    :label="__('Repeat')"
                                                    x-model="repeatRule"
                                                    x-on:change="updateRepeatRule()"
                                                    :error="$errors->first('composer.repeat_rule')"
                                                >
                                                    <option value="none">{{ __('Does not repeat') }}</option>
                                                    <option value="weekday">{{ __('Every weekday') }}</option>
                                                    <option value="weekly_custom">{{ __('Custom weekdays') }}</option>
                                                </x-ui.select>
                                            </div>

                                            <div
                                                x-show="repeatRule !== 'none'"
                                                x-cloak
                                                data-no-loading
                                                x-on:input="repeatUntil = $event.target.value || ''; $wire.set('composer.repeat_until', repeatUntil, true)"
                                                x-on:change="repeatUntil = $event.target.value || ''; $wire.set('composer.repeat_until', repeatUntil, true)"
                                            >
                                                <x-ui.date-picker
                                                    name="composer_repeat_until"
                                                    wire:model.live="composer.repeat_until"
                                                    :value="$composer['repeat_until'] ?? ''"
                                                    :label="__('Repeat until')"
                                                    :placeholder="__('Choose end date')"
                                                    :error="$errors->first('composer.repeat_until')"
                                                    picker-align="auto"
                                                    picker-position="top"
                                                />
                                            </div>
                                        </div>

                                        <div x-show="repeatRule === 'weekly_custom'" x-cloak class="space-y-2.5">
                                            <div class="flex items-center justify-between gap-3">
                                                <label class="text-sm font-medium" style="color: var(--theme-header-text-color);">{{ __('Repeat on') }}</label>
                                                @if ((string) $errors->first('composer.repeat_days') !== '')
                                                    <p class="text-sm font-medium" style="color: var(--theme-danger-color);">{{ $errors->first('composer.repeat_days') }}</p>
                                                @endif
                                            </div>
                                            <div class="flex flex-wrap gap-2">
                                                @foreach ([
                                                    'mon' => __('Mon'),
                                                    'tue' => __('Tue'),
                                                    'wed' => __('Wed'),
                                                    'thu' => __('Thu'),
                                                    'fri' => __('Fri'),
                                                    'sat' => __('Sat'),
                                                    'sun' => __('Sun'),
                                                ] as $repeatDayKey => $repeatDayLabel)
                                                    <button
                                                        type="button"
                                                        class="inline-flex min-w-[3.25rem] items-center justify-center rounded-full border px-3 py-2 text-xs font-semibold transition"
                                                        x-bind:aria-pressed="repeatDays.includes('{{ $repeatDayKey }}') ? 'true' : 'false'"
                                                        x-bind:style="repeatDays.includes('{{ $repeatDayKey }}')
                                                            ? 'border-color: rgba(var(--theme-accent-rgb), 0.38); background-color: rgba(var(--theme-accent-rgb), 0.10); color: var(--theme-accent);'
                                                            : 'border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-surface-base-rgb,255,255,255),0.92); color: var(--theme-header-text-color);'"
                                                        x-on:click.prevent="toggleRepeatDay('{{ $repeatDayKey }}')"
                                                        data-no-loading
                                                    >
                                                        {{ $repeatDayLabel }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        </div>

                                        <div x-show="repeatRule !== 'none'" x-cloak class="rounded-[0.95rem] border px-4 py-3 text-sm" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.02); color: var(--theme-muted-text-color);">
                                            <span x-show="repeatRule === 'weekday'">{{ __('This will repeat each selected time slot on weekdays until the chosen end date.') }}</span>
                                            <span x-show="repeatRule === 'weekly_custom'">{{ __('This will repeat each selected time slot on the chosen weekdays until the chosen end date.') }}</span>
                                        </div>

                                        <div
                                            x-show="repeatRule !== 'none' && recurringPreviewText(repeatRule, repeatUntil || '', repeatDays || []).trim() !== ''"
                                            x-cloak
                                            class="rounded-[0.95rem] border px-4 py-3 text-sm font-medium"
                                            style="border-color: rgba(var(--theme-accent-rgb), 0.18); background-color: rgba(var(--theme-accent-rgb), 0.05); color: var(--theme-header-text-color);"
                                        >
                                            <span x-text="recurringPreviewText(repeatRule, repeatUntil || '', repeatDays || [])"></span>
                                        </div>

                                        <div class="flex justify-start">
                                            <x-ui.button type="button" variant="outline" x-on:click="addLocalScheduleSlot()">
                                            <i class="fa-light fa-plus"></i>
                                            {{ __('Add time slot') }}
                                            </x-ui.button>
                                        </div>
                                    </div>
                            </div>
                        </div>
                        </div>

                    <div class="sticky bottom-0 z-20 border-t px-5 py-4 backdrop-blur-xl" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-overlay) 96%, transparent);">
                        <div class="mx-auto flex w-full max-w-[68rem] items-center justify-between gap-4">
                            <div class="hidden text-sm xl:block" style="color: var(--theme-muted-text-color);">
                                <p x-show="$wire.composer?.schedule_mode !== 'immediately'" x-cloak>{{ __('Publishing items are saved into the post queue and scheduled to the selected channels.') }}</p>
                                <p x-show="$wire.composer?.schedule_mode === 'immediately'" x-cloak>{{ __('Publishing items are sent to the selected channels immediately after you confirm.') }}</p>
                            </div>

                            <div class="flex items-center gap-3">
                                <x-ui.button
                                    type="button"
                                    variant="outline"
                                    x-bind:disabled="composerClosing || composerSavingAction !== ''"
                                    x-on:click="closeComposerLocal()"
                                >
                                    <span class="inline-flex items-center gap-2" x-show="!composerClosing">
                                        {{ __('Cancel') }}
                                    </span>
                                    <span class="inline-flex items-center gap-2" x-cloak x-show="composerClosing">
                                        <i class="fa-light fa-loader animate-spin"></i>
                                        {{ __('Loading...') }}
                                    </span>
                                </x-ui.button>
                                <x-ui.button
                                    type="button"
                                    variant="secondary"
                                    x-bind:disabled="composerClosing || composerSavingAction !== ''"
                                    x-on:click="saveComposerLocal('draft')"
                                >
                                    <span class="inline-flex items-center gap-2" x-show="composerSavingAction !== 'draft'">
                                        {{ __('Save Draft') }}
                                    </span>
                                    <span class="inline-flex items-center gap-2" x-cloak x-show="composerSavingAction === 'draft'">
                                        <i class="fa-light fa-loader animate-spin"></i>
                                        {{ __('Loading...') }}
                                    </span>
                                </x-ui.button>
                                <div
                                    x-bind:title="composerPublishBlocked ? composerPublishBlockedMessage : ''"
                                    x-bind:aria-label="composerPublishBlocked ? composerPublishBlockedMessage : ''"
                                >
                                    <x-ui.button
                                        type="button"
                                        variant="primary"
                                        x-bind:disabled="composerClosing || composerSavingAction !== '' || composerPublishBlocked"
                                        x-on:click="saveComposerLocal('scheduled')"
                                    >
                                        <span class="inline-flex items-center gap-2" x-show="composerSavingAction !== 'scheduled'">
                                            <span x-text="$wire.composer?.schedule_mode === 'immediately' ? @js(__('Publish Now')) : @js(__('Schedule Post'))"></span>
                                        </span>
                                        <span class="inline-flex items-center gap-2" x-cloak x-show="composerSavingAction === 'scheduled'">
                                            <i class="fa-light fa-loader animate-spin"></i>
                                            {{ __('Loading...') }}
                                        </span>
                                    </x-ui.button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <aside class="hidden min-h-0 border-t xl:block xl:border-l xl:border-t-0" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: rgba(var(--theme-border-color-rgb), 0.03);">
                    <div class="flex h-full min-h-0 flex-col">
                        <div class="border-b px-4 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Network Preview') }}</p>
                            <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Quick preview based on the selected profile and current caption.') }}</p>
                        </div>

                        <div class="flex-1 overflow-y-auto px-4 py-4">
                            @php($previewAccounts = $composerAccounts)
                            <div x-effect="if (!localSelectedPreviewAccountIds.includes(String(activePreviewAccountId || ''))) setActivePreviewAccount(localSelectedPreviewAccountIds[0] || '')" class="flex w-full justify-center">
                            <div x-show="localSelectedPreviewAccountIds.length > 0" class="w-full">
                            <div x-show="localSelectedPreviewOptions.length > 1" class="mb-4 flex flex-wrap gap-2">
                                <template x-for="previewOption in visiblePreviewOptions()" :key="'preview-chip-'+previewOption.id">
                                    <button
                                        type="button"
                                        x-on:click="setActivePreviewAccount(previewOption.id)"
                                        data-no-loading
                                        class="inline-flex items-center gap-2 rounded-full border px-3 py-2 text-xs font-semibold transition"
                                        x-bind:style="activePreviewAccountId === previewOption.id
                                            ? `border-color: transparent; background-color: ${previewOption.providerToneSurface || 'rgba(var(--theme-accent-rgb), 0.14)'}; color: ${previewOption.providerToneText || 'var(--theme-header-text-color)'};`
                                            : 'border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-overlay) 98%, transparent); color: var(--theme-muted-text-color);'"
                                    >
                                        <span class="relative inline-flex h-6 w-6 items-center justify-center overflow-visible rounded-full border text-[10px] font-semibold" style="border-color: rgba(var(--theme-border-color-rgb), 0.5); background-color: rgba(var(--theme-border-color-rgb), 0.04); color: var(--theme-header-text-color);">
                                            <span class="inline-flex h-full w-full items-center justify-center overflow-hidden rounded-full">
                                                <template x-if="previewOption.avatarUrl">
                                                    <img x-bind:src="previewOption.avatarUrl" x-bind:alt="previewOption.label" class="h-full w-full object-cover">
                                                </template>
                                                <template x-if="!previewOption.avatarUrl">
                                                    <span x-text="previewOption.initials"></span>
                                                </template>
                                            </span>
                                            <template x-if="previewOption.providerIcon">
                                                <span
                                                    class="absolute -bottom-1 -right-1 inline-flex h-3.5 w-3.5 items-center justify-center rounded-full border text-[8px]"
                                                    x-bind:style="`border-color: rgba(var(--theme-surface-base-rgb,255,255,255), 0.95); background-color: rgba(var(--theme-surface-base-rgb,255,255,255), 0.98); color: ${previewOption.providerToneText || 'var(--theme-muted-text-color)'};`"
                                                >
                                                    <i x-bind:class="previewOption.providerIcon"></i>
                                                </span>
                                            </template>
                                        </span>
                                        <span x-text="previewOption.label"></span>
                                    </button>
                                </template>
                                <button
                                    type="button"
                                    x-cloak
                                    x-show="!previewOptionsExpanded && hiddenPreviewOptionsCount() > 0"
                                    x-on:click="previewOptionsExpanded = true"
                                    class="inline-flex items-center rounded-full border px-3 py-2 text-xs font-semibold transition hover:bg-slate-900/5"
                                    style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color);"
                                >
                                    <span x-text="`+${hiddenPreviewOptionsCount()} {{ __('more') }}`"></span>
                                </button>
                            </div>
                            @foreach ($previewAccounts as $previewAccount)
                                @php($previewProvider = $providerCardsByKey->get((string) $previewAccount->provider_key, []))
                                @php($previewView = app(\Modules\AppPublishing\Support\PublishingPreviewRegistry::class)->get((string) $previewAccount->provider_key, 'apppublishing::livewire.partials.network-preview-generic'))
                                <div
                                    wire:key="desktop-preview-{{ $previewAccount->id }}-{{ $previewAccount->provider_key }}"
                                    x-cloak
                                    x-show="String(activePreviewAccountId || '') === '{{ (string) $previewAccount->id }}'"
                                    class="flex w-full justify-center"
                                >
                                    @include($previewView, ['composerAccount' => $previewAccount, 'composerProvider' => $previewProvider])
                                </div>
                            @endforeach
                            </div>
                            <div x-cloak x-show="localSelectedPreviewAccountIds.length === 0" class="flex h-full items-start justify-center pt-[50px]">
                                <div class="max-w-xs">
                                    <i class="fa-light fa-rectangle-history-circle-user text-3xl" style="color: var(--theme-muted-text-color);"></i>
                                    <p class="mt-4 text-sm leading-7" style="color: var(--theme-muted-text-color);">{{ __('Choose a channel and enter the post details to render a live preview.') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </aside>
            </div>

            <div
                x-cloak
                x-show="mobileMediaOpen"
                x-transition.opacity
                class="absolute inset-x-0 top-[5.375rem] bottom-[5.25rem] z-20 xl:hidden"
            >
                <div class="absolute inset-0 bg-slate-950/12" x-on:click="mobileMediaOpen = false"></div>

                <div class="absolute inset-x-3 top-3 bottom-3 overflow-hidden rounded-[1.15rem] border shadow-[0_28px_68px_-34px_rgba(15,23,42,0.42)]" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-overlay) 98%, transparent);">
                    <div class="flex h-full min-h-0 flex-col">
                        <div class="flex items-center justify-between gap-3 border-b px-4 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                            <div>
                                <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Select media') }}</p>
                                <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Browse files and attach media to this publishing item.') }}</p>
                            </div>
                            <button
                                type="button"
                                class="inline-flex h-10 w-10 items-center justify-center rounded-full border transition hover:bg-slate-900/5"
                                style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color);"
                                x-on:click="mobileMediaOpen = false"
                            >
                                <i class="fa-light fa-xmark"></i>
                            </button>
                        </div>

                        <div class="min-h-0 flex-1 overflow-hidden">
                            @if ($composerMediaBrowserReady)
                                <x-ui.media-browser
                                    wire:key="composer-media-browser-mobile-{{ (int) ($composer['media_refresh_token'] ?? 0) }}"
                                    wire:model.live="composer.media_items"
                                    context="portal"
                                    layout="library"
                                    :error="$errors->first('composer.media_items')"
                                    type="all"
                                    :multiple="true"
                                    :value="$composer['media_items'] ?? []"
                                    :library-title="__('Media')"
                                    :show-library-header="false"
                                    :frameless="true"
                                    :compact-toolbar="true"
                                />
                            @else
                                <div wire:init="loadComposerMediaBrowser" class="flex h-full min-h-[14rem] items-center justify-center">
                                    <div class="inline-flex items-center gap-2 text-sm font-medium" style="color: var(--theme-muted-text-color);">
                                        <i class="fa-light fa-loader animate-spin"></i>
                                        <span>{{ __('Loading media library...') }}</span>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div
                x-cloak
                x-show="mobilePreviewOpen"
                x-transition.opacity
                class="absolute inset-x-0 top-[5.375rem] bottom-[5.25rem] z-20 xl:hidden"
            >
                <div class="absolute inset-0 bg-slate-950/12" x-on:click="mobilePreviewOpen = false"></div>

                <div class="absolute inset-x-3 top-3 bottom-3 overflow-hidden rounded-[1.15rem] border shadow-[0_28px_68px_-34px_rgba(15,23,42,0.42)]" style="border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-overlay) 98%, transparent);">
                    <div class="flex h-full min-h-0 flex-col">
                        <div class="border-b px-4 py-4" style="border-color: rgba(var(--theme-border-color-rgb), 0.68);">
                            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ __('Network Preview') }}</p>
                            <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Quick preview based on the selected profile and current caption.') }}</p>
                        </div>

                        <div class="flex-1 overflow-y-auto px-4 py-4">
                            @php($mobilePreviewAccounts = $composerAccounts)
                            <div x-effect="if (!localSelectedPreviewAccountIds.includes(String(activePreviewAccountId || ''))) setActivePreviewAccount(localSelectedPreviewAccountIds[0] || '')" class="flex w-full justify-center">
                            <div x-show="localSelectedPreviewAccountIds.length > 0" class="w-full">
                            <div x-show="localSelectedPreviewOptions.length > 1" class="mb-4 flex flex-wrap gap-2">
                                <template x-for="previewOption in visiblePreviewOptions()" :key="'mobile-preview-chip-'+previewOption.id">
                                    <button
                                        type="button"
                                        x-on:click="setActivePreviewAccount(previewOption.id)"
                                        data-no-loading
                                        class="inline-flex items-center gap-2 rounded-full border px-3 py-2 text-xs font-semibold transition"
                                        x-bind:style="activePreviewAccountId === previewOption.id
                                            ? `border-color: transparent; background-color: ${previewOption.providerToneSurface || 'rgba(var(--theme-accent-rgb), 0.14)'}; color: ${previewOption.providerToneText || 'var(--theme-header-text-color)'};`
                                            : 'border-color: rgba(var(--theme-border-color-rgb), 0.68); background-color: color-mix(in srgb, var(--theme-surface-overlay) 98%, transparent); color: var(--theme-muted-text-color);'"
                                    >
                                        <span class="relative inline-flex h-6 w-6 items-center justify-center overflow-visible rounded-full border text-[10px] font-semibold" style="border-color: rgba(var(--theme-border-color-rgb), 0.5); background-color: rgba(var(--theme-border-color-rgb), 0.04); color: var(--theme-header-text-color);">
                                            <span class="inline-flex h-full w-full items-center justify-center overflow-hidden rounded-full">
                                                <template x-if="previewOption.avatarUrl">
                                                    <img x-bind:src="previewOption.avatarUrl" x-bind:alt="previewOption.label" class="h-full w-full object-cover">
                                                </template>
                                                <template x-if="!previewOption.avatarUrl">
                                                    <span x-text="previewOption.initials"></span>
                                                </template>
                                            </span>
                                            <template x-if="previewOption.providerIcon">
                                                <span
                                                    class="absolute -bottom-1 -right-1 inline-flex h-3.5 w-3.5 items-center justify-center rounded-full border text-[8px]"
                                                    x-bind:style="`border-color: rgba(var(--theme-surface-base-rgb,255,255,255), 0.95); background-color: rgba(var(--theme-surface-base-rgb,255,255,255), 0.98); color: ${previewOption.providerToneText || 'var(--theme-muted-text-color)'};`"
                                                >
                                                    <i x-bind:class="previewOption.providerIcon"></i>
                                                </span>
                                            </template>
                                        </span>
                                        <span x-text="previewOption.label"></span>
                                    </button>
                                </template>
                                <button
                                    type="button"
                                    x-cloak
                                    x-show="!previewOptionsExpanded && hiddenPreviewOptionsCount() > 0"
                                    x-on:click="previewOptionsExpanded = true"
                                    class="inline-flex items-center rounded-full border px-3 py-2 text-xs font-semibold transition hover:bg-slate-900/5"
                                    style="border-color: rgba(var(--theme-border-color-rgb), 0.68); color: var(--theme-muted-text-color);"
                                >
                                    <span x-text="`+${hiddenPreviewOptionsCount()} {{ __('more') }}`"></span>
                                </button>
                            </div>
                            @foreach ($mobilePreviewAccounts as $previewAccount)
                                @php($previewProvider = $providerCardsByKey->get((string) $previewAccount->provider_key, []))
                                @php($previewView = app(\Modules\AppPublishing\Support\PublishingPreviewRegistry::class)->get((string) $previewAccount->provider_key, 'apppublishing::livewire.partials.network-preview-generic'))
                                <div
                                    wire:key="mobile-preview-{{ $previewAccount->id }}-{{ $previewAccount->provider_key }}"
                                    x-cloak
                                    x-show="String(activePreviewAccountId || '') === '{{ (string) $previewAccount->id }}'"
                                    class="flex w-full justify-center"
                                >
                                    @include($previewView, ['composerAccount' => $previewAccount, 'composerProvider' => $previewProvider])
                                </div>
                            @endforeach
                            </div>
                            <div x-cloak x-show="localSelectedPreviewAccountIds.length === 0" class="flex h-full items-start justify-center pt-[50px]">
                                <div class="max-w-xs">
                                    <i class="fa-light fa-rectangle-history-circle-user text-3xl" style="color: var(--theme-muted-text-color);"></i>
                                    <p class="mt-4 text-sm leading-7" style="color: var(--theme-muted-text-color);">{{ __('Choose a channel and enter the post details to render a live preview.') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>
