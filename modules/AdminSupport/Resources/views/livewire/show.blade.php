<div class="mx-auto max-w-[92rem] space-y-6">
    @if ($statusMessage)
        <x-ui.alert :title="__('Saved')" :description="$statusMessage" variant="success" dismissible />
    @endif

    <x-ui.page-hero
        :eyebrow="__('Support ticket')"
        :title="$ticket->title"
        :description="$ticket->id_secure"
        icon="fa-light fa-message-lines"
    >
        <x-slot:actions>
            <x-ui.button href="{{ route('admin-support.index') }}" variant="outline" wire:navigate>{{ __('Back to queue') }}</x-ui.button>
        </x-slot:actions>
    </x-ui.page-hero>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.68fr)_340px]">
        <div class="space-y-6">
            <x-ui.card class="overflow-hidden border shadow-[0_18px_40px_rgba(15,23,42,0.04)]" style="border-color: var(--theme-border-color); background:
                radial-gradient(circle at top left, color-mix(in srgb, var(--theme-accent) 9%, transparent), transparent 34%),
                linear-gradient(180deg, color-mix(in srgb, var(--theme-surface-soft) 82%, transparent), var(--theme-surface-base));">
                <div class="space-y-5 px-6 pb-6 pt-4 lg:px-7">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="flex min-w-0 items-start gap-4">
                            <x-ui.avatar :src="$ticket->user?->avatar_url" :name="$ticket->user?->name" :seed="$ticket->user?->id ?? $ticket->user?->email ?? $ticket->user?->name" size="lg" />
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h2 class="text-[1.5rem] font-semibold tracking-[-0.04em]" style="color: var(--theme-header-text-color);">
                                        {{ $ticket->user?->name ?: __('Unknown user') }}
                                    </h2>
                                    <x-ui.badge :variant="$ticket->statusVariant()">{{ $ticket->statusLabel() }}</x-ui.badge>
                                    @if ($ticket->pin)
                                        <x-ui.badge variant="warning">{{ __('Pinned') }}</x-ui.badge>
                                    @endif
                                </div>
                                <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ $ticket->user?->email ?: __('No email') }}</p>
                            </div>
                        </div>

                        @if ($ticket->labels->isNotEmpty())
                            <div class="flex flex-wrap items-center gap-2">
                                @foreach ($ticket->labels as $label)
                                    <span class="inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold" style="border-color: color-mix(in srgb, {{ $label->color }} 22%, var(--theme-border-color)); color: {{ $label->color }}; background-color: color-mix(in srgb, {{ $label->color }} 9%, transparent);">
                                        <i class="{{ $label->icon }}"></i>
                                        {{ $label->name }}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="grid gap-3 md:grid-cols-3">
                        <div class="rounded-[1.05rem] border px-4 py-3.5" style="border-color: color-mix(in srgb, var(--theme-border-color) 88%, transparent); background-color: color-mix(in srgb, var(--theme-surface-soft) 22%, transparent);">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Category') }}</p>
                            <div class="mt-2 flex items-center gap-2">
                                @if ($ticket->category?->icon)
                                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full text-xs" style="background-color: color-mix(in srgb, {{ $ticket->category->color ?: 'var(--theme-accent)' }} 12%, transparent); color: {{ $ticket->category->color ?: 'var(--theme-accent)' }};">
                                        <i class="{{ $ticket->category->icon }}"></i>
                                    </span>
                                @endif
                                <p class="font-semibold" style="color: var(--theme-header-text-color);">{{ $ticket->category?->name ?: __('Unassigned') }}</p>
                            </div>
                        </div>

                        <div class="rounded-[1.05rem] border px-4 py-3.5" style="border-color: color-mix(in srgb, var(--theme-border-color) 88%, transparent); background-color: color-mix(in srgb, var(--theme-surface-soft) 22%, transparent);">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Type') }}</p>
                            <div class="mt-2 flex items-center gap-2">
                                @if ($ticket->type?->icon)
                                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full text-xs" style="background-color: color-mix(in srgb, {{ $ticket->type->color ?: 'var(--theme-accent)' }} 12%, transparent); color: {{ $ticket->type->color ?: 'var(--theme-accent)' }};">
                                        <i class="{{ $ticket->type->icon }}"></i>
                                    </span>
                                @endif
                                <p class="font-semibold" style="color: var(--theme-header-text-color);">{{ $ticket->type?->name ?: __('None') }}</p>
                            </div>
                        </div>

                        <div class="rounded-[1.05rem] border px-4 py-3.5" style="border-color: color-mix(in srgb, var(--theme-border-color) 88%, transparent); background-color: color-mix(in srgb, var(--theme-surface-soft) 22%, transparent);">
                            <p class="text-[11px] font-semibold uppercase tracking-[0.18em]" style="color: var(--theme-muted-text-color);">{{ __('Updated') }}</p>
                            <p class="mt-2 font-semibold" style="color: var(--theme-header-text-color);">{{ $ticket->changedAtFormatted() ?: __('N/A') }}</p>
                            <p class="mt-1 text-xs" style="color: var(--theme-muted-text-color);">{{ $ticket->changedFromHuman() ?: __('No recent activity') }}</p>
                        </div>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card padding="none" class="overflow-hidden border shadow-[0_18px_40px_rgba(15,23,42,0.04)]" style="border-color: var(--theme-border-color); background:
                linear-gradient(180deg, color-mix(in srgb, var(--theme-surface-soft) 46%, transparent), var(--theme-surface-base) 18rem);">
                <div class="border-b px-6 pb-5 pt-4 lg:px-7" style="border-color: var(--theme-border-color);">
                    <div class="flex flex-wrap items-end justify-between gap-3">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ __('Conversation') }}</p>
                            <h3 class="mt-2 text-[1.2rem] font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">{{ __('Thread') }}</h3>
                            <p class="mt-1 text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Customer message and every reply in one compact timeline.') }}</p>
                        </div>
                        <span class="inline-flex items-center rounded-full border px-3 py-1 text-xs font-medium" style="border-color: var(--theme-border-color); color: var(--theme-muted-text-color);">
                            {{ 1 + $ticket->comments->count() }} {{ __('messages') }}
                        </span>
                    </div>
                </div>

                <div class="px-6 py-6 lg:px-7">
                    <div class="relative space-y-5 before:absolute before:bottom-0 before:left-[1.1rem] before:top-2 before:w-px before:content-['']" style="--tw-before-bg: color-mix(in srgb, var(--theme-border-color) 82%, transparent);">
                        <div class="relative flex gap-4">
                            <div class="relative z-[1] hidden pt-1 sm:block">
                                <x-ui.avatar :src="$ticket->user?->avatar_url" :name="$ticket->user?->name" :seed="$ticket->user?->id ?? $ticket->user?->email ?? $ticket->user?->name" size="sm" />
                            </div>
                            <div class="min-w-0 flex-1 rounded-[1.3rem] border px-5 py-4 shadow-[0_14px_36px_-30px_rgba(15,23,42,0.26)]" style="border-color: color-mix(in srgb, var(--theme-accent) 18%, var(--theme-border-color)); background:
                                linear-gradient(180deg, color-mix(in srgb, var(--theme-accent) 6%, transparent), transparent),
                                color-mix(in srgb, var(--theme-surface-soft) 16%, transparent);">
                                <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                    <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $ticket->user?->name ?: __('Unknown user') }}</p>
                                    <span class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em]" style="border-color: color-mix(in srgb, var(--theme-accent) 20%, var(--theme-border-color)); color: var(--theme-accent); background-color: color-mix(in srgb, var(--theme-accent) 8%, transparent);">{{ __('Original') }}</span>
                                </div>
                                <p class="mt-3 text-sm leading-7" style="color: var(--theme-header-text-color);">{{ $ticket->content }}</p>
                            </div>
                        </div>

                        @forelse ($ticket->comments as $threadComment)
                            <div class="relative flex gap-4">
                                <div class="relative z-[1] hidden pt-1 sm:block">
                                    <x-ui.avatar :src="$threadComment->user?->avatar_url" :name="$threadComment->user?->name" :seed="$threadComment->user?->id ?? $threadComment->user?->email ?? $threadComment->user?->name" size="sm" />
                                </div>
                                <div class="group min-w-0 flex-1 rounded-[1.3rem] border px-5 py-4 shadow-[0_14px_36px_-30px_rgba(15,23,42,0.18)]" style="border-color: var(--theme-border-color); background:
                                    linear-gradient(180deg, color-mix(in srgb, var(--theme-surface-soft) 50%, transparent), transparent),
                                    color-mix(in srgb, var(--theme-surface-soft) 12%, transparent);">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                            <p class="text-sm font-semibold" style="color: var(--theme-header-text-color);">{{ $threadComment->user?->name ?: __('Unknown user') }}</p>
                                            <p class="text-xs" style="color: var(--theme-muted-text-color);">{{ $threadComment->createdAtFormatted() ?: __('N/A') }}</p>
                                        </div>

                                        <div class="flex items-center gap-2">
                                            @if ($editingCommentId === $threadComment->id)
                                                <span class="inline-flex rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em]" style="border-color: color-mix(in srgb, var(--theme-accent) 20%, var(--theme-border-color)); color: var(--theme-accent); background-color: color-mix(in srgb, var(--theme-accent) 8%, transparent);">
                                                    {{ __('Editing') }}
                                                </span>
                                            @endif

                                            <div class="flex items-center gap-1 transition-opacity duration-150 {{ $editingCommentId === $threadComment->id ? 'opacity-100' : 'opacity-0 group-hover:opacity-100' }}">
                                                <x-ui.button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    class="h-9 w-9 !rounded-full !px-0"
                                                    wire:click="editComment({{ $threadComment->id }})"
                                                >
                                                    <i class="fa-light fa-pen-to-square"></i>
                                                </x-ui.button>

                                                <x-ui.dialog :title="__('Delete this reply?')" :description="__('This removes the selected reply from the thread.')" width="sm" dismissible>
                                                    <x-slot:trigger>
                                                        <x-ui.button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            class="h-9 w-9 !rounded-full !px-0 border-rose-200 text-rose-600 hover:bg-rose-50"
                                                        >
                                                            <i class="fa-light fa-trash-can"></i>
                                                        </x-ui.button>
                                                    </x-slot:trigger>
                                                    <x-slot:footer>
                                                        <div class="flex justify-end gap-3">
                                                            <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                                            <x-ui.button type="button" variant="danger" wire:click="deleteComment({{ $threadComment->id }})" x-on:click="open = false">{{ __('Delete') }}</x-ui.button>
                                                        </div>
                                                    </x-slot:footer>
                                                </x-ui.dialog>
                                            </div>
                                        </div>
                                    </div>

                                    @if ($editingCommentId === $threadComment->id)
                                        @php($editingEditorName = 'support_edit_comment_editor_'.$threadComment->id.'_'.$editingCommentEditorIteration)
                                        <div
                                            wire:key="support-edit-comment-{{ $threadComment->id }}-{{ $editingCommentEditorIteration }}"
                                            class="mt-4"
                                            x-data="{}"
                                            x-init="$nextTick(() => {
                                                window.dispatchEvent(new CustomEvent('html-editor:set', { detail: { name: '{{ $editingEditorName }}', content: @js((string) $editingCommentContent) } }));
                                                if ($refs.editHidden) {
                                                    $refs.editHidden.value = @js((string) $editingCommentContent);
                                                }
                                            })"
                                            x-on:html-editor:change.window="
                                                if (($event.detail?.name || '') === '{{ $editingEditorName }}') {
                                                    if ($refs.editHidden) {
                                                        $refs.editHidden.value = String($event.detail?.content || '');
                                                        $refs.editHidden.dispatchEvent(new Event('input', { bubbles: true }));
                                                        $refs.editHidden.dispatchEvent(new Event('change', { bubbles: true }));
                                                    }
                                                }
                                            "
                                        >
                                            <x-ui.html-editor
                                                :name="$editingEditorName"
                                                :value="$editingCommentContent"
                                                :error="$errors->first('editingCommentContent')"
                                                rows="6"
                                            />
                                            <input type="hidden" x-ref="editHidden" wire:model.defer="editingCommentContent">
                                        </div>

                                        <div class="mt-4 flex justify-end gap-3">
                                            <x-ui.button type="button" variant="outline" wire:click="cancelCommentEdit">{{ __('Cancel') }}</x-ui.button>
                                            <x-ui.button type="button" wire:click="updateComment">{{ __('Save changes') }}</x-ui.button>
                                        </div>
                                    @else
                                        <div class="html-editor-surface mt-3 text-sm leading-7 break-words [&>*:first-child]:mt-0 [&>*:last-child]:mb-0" style="color: var(--theme-header-text-color);">
                                            {!! $threadComment->comment !!}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @empty
                        @endforelse

                        @if ($ticket->comments->isEmpty())
                            <div class="pl-0 sm:pl-[3.25rem]">
                                <x-ui.empty icon="fa-light fa-comments" :title="__('No replies yet.')" :description="__('The thread only contains the original customer message for now.')" />
                            </div>
                        @endif
                    </div>
                </div>

                <div class="border-t px-6 py-6 lg:px-7" style="border-color: var(--theme-border-color); background:
                    linear-gradient(180deg, color-mix(in srgb, var(--theme-surface-soft) 38%, transparent), var(--theme-surface-base));">
                    <form wire:submit="sendReply" class="space-y-4">
                        <div>
                            <p class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ __('Reply') }}</p>
                            <p class="mt-1 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Send the next response directly into this thread.') }}</p>
                        </div>
                        @php($replyEditorName = 'support_reply_editor_'.$replyEditorIteration)

                        <div
                            wire:key="support-reply-editor-{{ $replyEditorIteration }}"
                            x-data="{}"
                            x-init="$nextTick(() => {
                                window.dispatchEvent(new CustomEvent('html-editor:set', { detail: { name: '{{ $replyEditorName }}', content: @js((string) $this->comment) } }));
                                if ($refs.replyHidden) {
                                    $refs.replyHidden.value = @js((string) $this->comment);
                                }
                            })"
                            x-on:html-editor:change.window="
                                if (($event.detail?.name || '') === '{{ $replyEditorName }}') {
                                    if ($refs.replyHidden) {
                                        $refs.replyHidden.value = String($event.detail?.content || '');
                                        $refs.replyHidden.dispatchEvent(new Event('input', { bubbles: true }));
                                        $refs.replyHidden.dispatchEvent(new Event('change', { bubbles: true }));
                                    }
                                }
                            "
                        >
                            <x-ui.html-editor
                                :name="$replyEditorName"
                                :value="$this->comment"
                                :error="$errors->first('comment')"
                                rows="8"
                            />
                            <input type="hidden" x-ref="replyHidden" wire:model.defer="comment">
                        </div>
                        <div class="flex justify-end">
                            <x-ui.button type="submit">{{ __('Send reply') }}</x-ui.button>
                        </div>
                    </form>
                </div>
            </x-ui.card>
        </div>

        <aside class="space-y-5 xl:sticky xl:top-24 self-start">
            <x-ui.card padding="none" class="overflow-hidden border shadow-[0_18px_40px_rgba(15,23,42,0.05)]" style="border-color: var(--theme-border-color); background-color: var(--theme-surface-base);">
                <div class="border-b px-5 py-4" style="border-color: var(--theme-border-color); background-color: color-mix(in srgb, var(--theme-surface-soft) 54%, transparent);">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ __('Controls') }}</p>
                    <h3 class="mt-2 text-[1.1rem] font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">{{ __('Ticket settings') }}</h3>
                </div>

                <form wire:submit="saveSettings" class="space-y-4 p-5">
                    <x-ui.field :label="__('Status')" :error="$errors->first('status')">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <x-ui.radio name="status" value="1" :label="__('Open')" wire:model="status" />
                            <x-ui.radio name="status" value="2" :label="__('Resolved')" wire:model="status" />
                            <x-ui.radio name="status" value="0" :label="__('Closed')" wire:model="status" />
                        </div>
                    </x-ui.field>

                    <div class="grid gap-4">
                        <x-ui.select wire:model="cateId" name="cateId" :label="__('Category')" :error="$errors->first('cateId')">
                            <option value="">{{ __('Unassigned') }}</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </x-ui.select>

                        <x-ui.select wire:model="typeId" name="typeId" :label="__('Type')" :error="$errors->first('typeId')">
                            <option value="">{{ __('None') }}</option>
                            @foreach ($types as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </x-ui.select>
                    </div>

                    <x-ui.field :label="__('Priority')" :error="$errors->first('pin')">
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <x-ui.radio name="pin" value="0" :label="__('Normal')" wire:model="pin" />
                            <x-ui.radio name="pin" value="1" :label="__('Pinned')" wire:model="pin" />
                        </div>
                    </x-ui.field>

                    <x-ui.field :label="__('Labels')" :error="$errors->first('labelIds') ?: $errors->first('labelIds.*')">
                        <div class="grid gap-x-5 gap-y-3 sm:grid-cols-2">
                            @foreach ($labels as $label)
                                <x-ui.checkbox :label="$label->name" value="{{ $label->id }}" wire:model="labelIds" />
                            @endforeach
                        </div>
                    </x-ui.field>

                    <div class="flex justify-end border-t pt-4" style="border-color: var(--theme-border-color);">
                        <x-ui.button type="submit">{{ __('Save changes') }}</x-ui.button>
                    </div>
                </form>
            </x-ui.card>

            <x-ui.card padding="none" class="overflow-hidden border shadow-[0_18px_40px_rgba(15,23,42,0.05)]" style="border-color: color-mix(in srgb, var(--theme-danger-color) 18%, var(--theme-border-color)); background-color: var(--theme-surface-base);">
                <div class="border-b px-5 py-4" style="border-color: color-mix(in srgb, var(--theme-danger-color) 16%, var(--theme-border-color)); background-color: color-mix(in srgb, var(--theme-danger-color) 4%, transparent);">
                    <p class="text-[11px] font-semibold uppercase tracking-[0.2em]" style="color: var(--theme-muted-text-color);">{{ __('Cleanup') }}</p>
                    <h3 class="mt-2 text-[1.05rem] font-semibold tracking-[-0.03em]" style="color: var(--theme-header-text-color);">{{ __('Delete ticket') }}</h3>
                </div>

                <div class="space-y-4 p-5">
                    <p class="text-sm leading-6" style="color: var(--theme-muted-text-color);">{{ __('Use this only when the ticket must be permanently removed from support history.') }}</p>
                    <x-ui.dialog :title="__('Delete support ticket?')" :description="__('This permanently removes the ticket and all replies attached to it.')" width="sm" dismissible>
                        <x-slot:trigger>
                            <x-ui.button type="button" variant="danger">{{ __('Delete ticket') }}</x-ui.button>
                        </x-slot:trigger>
                        <x-slot:footer>
                            <div class="flex justify-end gap-3">
                                <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                                <x-ui.button type="button" variant="danger" wire:click="deleteTicket" x-on:click="open = false">{{ __('Delete') }}</x-ui.button>
                            </div>
                        </x-slot:footer>
                    </x-ui.dialog>
                </div>
            </x-ui.card>
        </aside>
    </div>
</div>
