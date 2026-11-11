<?php

namespace Modules\AppSupport\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AdminSupport\Models\SupportCategory;
use Modules\AdminSupport\Models\SupportTicket;

#[Title('Support')]
class PortalSupportIndex extends Component
{
    use \Livewire\WithPagination;
    #[\Livewire\Attributes\Url(as: 'q')] public string $search = '';
    #[\Livewire\Attributes\Url(as: 'status')] public string $statusFilter = 'all';
    public function filterTickets(): void { $this->resetPage(); }
    public function resetFilters(): void { $this->search=''; $this->statusFilter='all'; $this->resetPage(); }

    public string $cateId = '';

    public string $title = '';

    public string $content = '';

    public ?string $statusMessage = null;

    public function mount(): void
    {
        $this->statusMessage = session('status');
    }

    public function createTicket(): mixed
    {
        abort_unless($this->supportEnabled(), 404);

        $validated = $this->validate([
            'cateId' => ['required', 'integer', \Illuminate\Validation\Rule::exists('support_categories','id')->where('status',true)],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:5000'],
        ], [], [
            'cateId' => __('category'),
            'title' => __('subject'),
            'content' => __('details'),
        ]);

        $ticket = SupportTicket::query()->create([
            'id_secure' => Str::random(32),
            'uid' => auth()->id(),
            'open_by' => auth()->id(),
            'team_id' => null,
            'cate_id' => (int) $validated['cateId'],
            'type_id' => null,
            'title' => trim($validated['title']),
            'content' => trim($validated['content']),
            'status' => 1,
            'pin' => false,
            'user_read' => false,
            'admin_read' => true,
            'changed' => time(),
            'created' => time(),
        ]);

        log_activity('portal.support.create', 'Created a support ticket.', [
            'subject_type' => SupportTicket::class,
            'subject_id' => $ticket->id,
            'metadata' => [
                'ticket' => $ticket->id_secure,
                'title' => $ticket->title,
            ],
        ]);

        return $this->redirect(route('portal.support.show', $ticket), navigate: true);
    }

    public function resolveTicket(int $ticketId): void
    {
        abort_unless($this->supportEnabled(), 404);

        $ticket = SupportTicket::query()
            ->where('uid', auth()->id())
            ->findOrFail($ticketId);

        if ((int) $ticket->status !== 1) {
            return;
        }

        $ticket->update([
            'status' => 2,
            'changed' => time(),
        ]);

        log_activity('portal.support.resolve', 'Marked a support ticket as resolved.', [
            'subject_type' => SupportTicket::class,
            'subject_id' => $ticket->id,
            'metadata' => [
                'ticket' => $ticket->id_secure,
            ],
        ]);

        $this->dispatch('app-toast', type: 'success', message: __('Ticket marked as resolved.'));
    }

    public function render(): View
    {
        abort_unless($this->supportEnabled(), 404);

        $request = request();
        $query = SupportTicket::query()
            ->with(['category:id,name,color,icon', 'type:id,name,color,icon'])
            ->withCount('comments')
            ->where('uid', auth()->id())
            ->search($this->search)
            ->when(in_array($this->statusFilter, ['1','2','3'], true), fn ($builder) => $builder->where('status', (int) $this->statusFilter))
            ->orderByDesc('changed')
            ->orderByDesc('id');

        $tickets = $query->paginate(15)->withQueryString();
        $allTickets = SupportTicket::query()->where('uid', auth()->id())->get(['status', 'user_read']);

        return view('appsupport::livewire.index', [
            'tickets' => $tickets,
            'categories' => SupportCategory::query()->where('status', true)->orderBy('name')->get(),
            'filters' => [
                'q' => $this->search,
                'status' => $this->statusFilter,
            ],
            'summary' => [
                'total' => $allTickets->count(),
                'open' => $allTickets->where('status', 1)->count(),
                'resolved' => $allTickets->where('status', 2)->count(),
                'unread' => $allTickets->where('user_read', true)->count(),
            ],
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Support'),
        ]);
    }

    protected function supportEnabled(): bool
    {
        $user = auth()->user();

        return ! $user?->plan || $user->hasPlanFeature('support');
    }
}
