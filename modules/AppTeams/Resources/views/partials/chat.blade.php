<section class="portal-tool-panel space-y-4" wire:poll.visible.15s="refreshConversation">
    <div class="flex justify-between gap-4 items-center"><div><h2 class="font-semibold">{{ __('Team chat') }}</h2><p class="teams-muted">{{ __('Shared rooms for workspace coordination. For post decisions, use Approvals.') }}</p></div>
    @if($canManageChat)<x-ui.modal width="md" :title="__('New shared room')" close-event="team-conversation-create-modal-close">
        <x-slot:trigger><x-ui.button type="button" variant="outline">{{ __('New room') }}</x-ui.button></x-slot:trigger>
        <form wire:submit="storeConversation" id="new-room" class="space-y-4"><x-ui.input wire:model="conversation_title" name="conversation_title" :label="__('Room name')" /><x-ui.textarea wire:model="conversation_description" name="conversation_description" :label="__('Description')" /><x-ui.textarea wire:model="initial_message" name="initial_message" :label="__('First message (optional)')" /></form>
        <x-slot:footer><x-ui.button type="submit" form="new-room" wire:loading.attr="disabled">{{ __('Create room') }}</x-ui.button></x-slot:footer>
    </x-ui.modal>@endif</div>
    <div class="grid gap-5 lg:grid-cols-[220px_minmax(0,1fr)]">
        <div class="space-y-2">@forelse($conversations as $room)<button type="button" wire:click="openConversation({{ $room->id }})" class="block w-full rounded-lg border p-3 text-left" style="border-color:{{ $activeConversation?->id === $room->id ? 'var(--theme-accent)' : 'var(--theme-border-color)' }}"><span class="text-sm font-semibold">{{ $room->title }}</span><span class="block teams-muted">{{ $room->messages_count }} {{ __('messages') }}</span></button>@empty<p class="teams-muted">{{ __('No rooms available.') }}</p>@endforelse</div>
        <div class="min-w-0">
            @if($activeConversation)
            <div class="flex justify-between gap-3 mb-4"><div><h3 class="font-semibold">{{ $activeConversation->title }}</h3><p class="teams-muted">{{ $activeConversation->description }}</p></div>
            @if($canManageChat)<div class="flex gap-2">
                <x-ui.modal width="md" :title="__('Edit room')" open-event="team-conversation-edit-modal-open" close-event="team-conversation-edit-modal-close">
                    <x-slot:trigger><x-ui.button type="button" variant="outline" size="sm" wire:click="beginEditConversation">{{ __('Edit') }}</x-ui.button></x-slot:trigger>
                    <form wire:submit="updateConversation" id="edit-room" class="space-y-3"><x-ui.input wire:model="edit_conversation_title" name="edit_conversation_title" :label="__('Room name')" /><x-ui.textarea wire:model="edit_conversation_description" name="edit_conversation_description" :label="__('Description')" /></form>
                    <x-slot:footer><x-ui.button type="submit" form="edit-room">{{ __('Save room') }}</x-ui.button></x-slot:footer>
                </x-ui.modal>
                <x-ui.button type="button" variant="danger" size="sm" wire:click="deleteConversation" wire:confirm="{{ __('Delete this room and its messages?') }}">{{ __('Delete') }}</x-ui.button>
            </div>@endif</div>
            <div class="max-h-96 overflow-y-auto" aria-label="{{ __('Messages') }}">@forelse($chatMessages->getCollection()->reverse() as $message)<div class="teams-message" wire:key="message-{{ $message->id }}"><p class="teams-muted"><strong>{{ $message->author?->name ?: __('Former member') }}</strong> · {{ $message->created_at?->format('d M H:i') }}</p><p class="text-sm whitespace-pre-wrap">{{ $message->body }}</p></div>@empty<p class="teams-muted py-6">{{ __('No messages yet.') }}</p>@endforelse</div>
            <div class="py-3">{{ $chatMessages->links() }}</div>
            <form wire:submit="storeMessage" class="space-y-3"><x-ui.textarea wire:model="body" name="body" :label="__('Message')" rows="3" /><x-ui.button type="submit" wire:loading.attr="disabled" wire:target="storeMessage">{{ __('Send message') }}</x-ui.button></form>
            @else<x-ui.empty :title="__('Choose a room')" :description="__('Select a shared room to read its messages.')" />@endif
        </div>
    </div>
</section>
