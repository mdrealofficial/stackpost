<?php

namespace Modules\AdminSupport\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AdminSupport\Models\SupportCategory;
use Modules\AdminSupport\Models\SupportTicket;
use Modules\AdminSupport\Models\SupportType;

#[Title('Support')]
class SupportIndex extends Component
{
    public ?string $statusMessage = null;

    public function resolve(int $ticketId): void
    {
        $ticket = SupportTicket::query()->findOrFail($ticketId);

        $ticket->update([
            'status' => 2,
            'changed' => time(),
        ]);

        $this->statusMessage = __('Support ticket marked as resolved.');
    }

    public function render(): View
    {
        $request = request();
        $query = SupportTicket::query()
            ->with(['user:id,name,username,email', 'category:id,name,color,icon', 'type:id,name,color,icon', 'labels:id,name,color,icon'])
            ->withCount('comments')
            ->search($request->string('q')->toString())
            ->when($request->filled('status') && $request->input('status') !== 'all', fn ($builder) => $builder->where('status', (int) $request->input('status')))
            ->when($request->filled('category') && $request->input('category') !== 'all', fn ($builder) => $builder->where('cate_id', (int) $request->input('category')))
            ->when($request->filled('type') && $request->input('type') !== 'all', fn ($builder) => $builder->where('type_id', (int) $request->input('type')))
            ->orderByDesc('pin')
            ->orderByDesc('changed')
            ->orderByDesc('id');

        $tickets = $query->paginate(15)->withQueryString();
        $allTickets = SupportTicket::query()->get(['status', 'admin_read']);

        return view('adminsupport::livewire.index', [
            'tickets' => $tickets,
            'categories' => SupportCategory::query()->where('status', true)->orderBy('name')->get(),
            'types' => SupportType::query()->where('status', true)->orderBy('name')->get(),
            'statusMessage' => $this->statusMessage,
            'filters' => [
                'q' => $request->string('q')->toString(),
                'status' => $request->input('status', 'all'),
                'category' => $request->input('category', 'all'),
                'type' => $request->input('type', 'all'),
            ],
            'summary' => [
                'total' => $allTickets->count(),
                'open' => $allTickets->where('status', 1)->count(),
                'resolved' => $allTickets->where('status', 2)->count(),
                'unread' => $allTickets->where('admin_read', true)->count(),
            ],
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Support'),
        ]);
    }
}
