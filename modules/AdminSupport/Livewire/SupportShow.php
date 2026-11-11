<?php

namespace Modules\AdminSupport\Livewire;

use DOMDocument;
use DOMElement;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Component;
use Modules\AdminSupport\Models\SupportCategory;
use Modules\AdminSupport\Models\SupportComment;
use Modules\AdminSupport\Models\SupportLabel;
use Modules\AdminSupport\Models\SupportTicket;
use Modules\AdminSupport\Models\SupportType;

class SupportShow extends Component
{
    public SupportTicket $ticket;

    public string $status = '1';

    public string $cateId = '';

    public string $typeId = '';

    public string $pin = '0';

    public array $labelIds = [];

    public string $comment = '';

    public int $replyEditorIteration = 0;

    public ?int $editingCommentId = null;

    public string $editingCommentContent = '';

    public int $editingCommentEditorIteration = 0;

    public ?string $statusMessage = null;

    public function mount(SupportTicket $ticket): void
    {
        $this->ticket = $ticket;
        $this->ticket->forceFill(['admin_read' => false])->save();
        $this->fillFromTicket();
    }

    public function saveSettings(): void
    {
        $validated = $this->validate([
            'status' => ['required', 'in:0,1,2'],
            'cateId' => ['nullable', 'integer', 'exists:support_categories,id'],
            'typeId' => ['nullable', 'integer', 'exists:support_types,id'],
            'pin' => ['required', 'in:0,1'],
            'labelIds' => ['nullable', 'array'],
            'labelIds.*' => ['integer', 'exists:support_labels,id'],
        ]);

        $this->ticket->update([
            'cate_id' => $validated['cateId'] !== '' ? (int) $validated['cateId'] : null,
            'type_id' => $validated['typeId'] !== '' ? (int) $validated['typeId'] : null,
            'status' => (int) $validated['status'],
            'pin' => (bool) ((int) $validated['pin']),
            'changed' => time(),
        ]);

        $this->ticket->labels()->sync($validated['labelIds'] ?? []);
        $this->ticket->refresh();
        $this->fillFromTicket();

        log_activity('admin.support.update', 'Updated a support ticket.', [
            'subject_type' => SupportTicket::class,
            'subject_id' => $this->ticket->id,
            'metadata' => [
                'ticket' => $this->ticket->id_secure,
                'status' => $this->ticket->status,
            ],
        ]);

        $this->statusMessage = __('Support ticket updated successfully.');
    }

    public function sendReply(): void
    {
        $validated = $this->validate([
            'comment' => ['required', 'string', 'max:5000'],
        ]);

        $sanitizedComment = $this->sanitizeCommentHtml($validated['comment']);

        if (! $this->hasMeaningfulHtmlContent($sanitizedComment)) {
            $this->addError('comment', __('The comment field is required.'));

            return;
        }

        SupportComment::query()->create([
            'id_secure' => Str::random(32),
            'ticket_id' => $this->ticket->id,
            'user_id' => auth()->id(),
            'comment' => $sanitizedComment,
            'changed' => time(),
            'created' => time(),
        ]);

        $this->ticket->update([
            'user_read' => true,
            'admin_read' => false,
            'changed' => time(),
        ]);

        log_activity('admin.support.reply', 'Replied to a support ticket.', [
            'subject_type' => SupportTicket::class,
            'subject_id' => $this->ticket->id,
            'metadata' => [
                'ticket' => $this->ticket->id_secure,
            ],
        ]);

        $this->comment = '';
        $this->replyEditorIteration++;
        $this->ticket->refresh();
        $this->statusMessage = __('Reply sent successfully.');
    }

    public function editComment(int $commentId): void
    {
        $comment = $this->findTicketComment($commentId);

        $this->editingCommentId = $comment->id;
        $this->editingCommentContent = (string) $comment->comment;
        $this->editingCommentEditorIteration++;
    }

    public function cancelCommentEdit(): void
    {
        $this->editingCommentId = null;
        $this->editingCommentContent = '';
        $this->editingCommentEditorIteration++;
    }

    public function updateComment(): void
    {
        if (! $this->editingCommentId) {
            return;
        }

        $validated = $this->validate([
            'editingCommentContent' => ['required', 'string', 'max:5000'],
        ], [], [
            'editingCommentContent' => __('comment'),
        ]);

        $sanitizedComment = $this->sanitizeCommentHtml($validated['editingCommentContent']);

        if (! $this->hasMeaningfulHtmlContent($sanitizedComment)) {
            $this->addError('editingCommentContent', __('The comment field is required.'));

            return;
        }

        $comment = $this->findTicketComment($this->editingCommentId);
        $comment->update([
            'comment' => $sanitizedComment,
            'changed' => time(),
        ]);

        $this->ticket->update([
            'changed' => time(),
        ]);

        log_activity('admin.support.comment.update', 'Updated a support comment.', [
            'subject_type' => SupportComment::class,
            'subject_id' => $comment->id,
            'metadata' => [
                'ticket' => $this->ticket->id_secure,
            ],
        ]);

        $this->cancelCommentEdit();
        $this->ticket->refresh();
        $this->statusMessage = __('Reply updated successfully.');
    }

    public function deleteComment(int $commentId): void
    {
        $comment = $this->findTicketComment($commentId);
        $subjectId = $comment->id;
        $comment->delete();

        $this->ticket->update([
            'changed' => time(),
        ]);

        if ($this->editingCommentId === $commentId) {
            $this->cancelCommentEdit();
        }

        log_activity('admin.support.comment.delete', 'Deleted a support comment.', [
            'subject_type' => SupportComment::class,
            'subject_id' => $subjectId,
            'metadata' => [
                'ticket' => $this->ticket->id_secure,
            ],
        ]);

        $this->ticket->refresh();
        $this->statusMessage = __('Reply deleted successfully.');
    }

    public function deleteTicket(): mixed
    {
        $ticketId = $this->ticket->id_secure;
        $subjectId = $this->ticket->id;
        $this->ticket->delete();

        log_activity('admin.support.delete', 'Deleted a support ticket.', [
            'subject_type' => SupportTicket::class,
            'subject_id' => $subjectId,
            'metadata' => [
                'ticket' => $ticketId,
            ],
        ]);

        return $this->redirect(route('admin-support.index'), navigate: true);
    }

    public function render(): View
    {
        $ticket = $this->ticket->load([
            'user:id,name,username,email',
            'opener:id,name,username,email',
            'category:id,name,color,icon',
            'type:id,name,color,icon',
            'team:id,name',
            'labels:id,name,color,icon',
            'comments.user:id,name,username,email',
        ]);

        return view('adminsupport::livewire.show', [
            'ticket' => $ticket,
            'categories' => SupportCategory::query()->where('status', true)->orderBy('name')->get(),
            'types' => SupportType::query()->where('status', true)->orderBy('name')->get(),
            'labels' => SupportLabel::query()->where('status', true)->orderBy('name')->get(),
            'statusMessage' => $this->statusMessage,
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => $ticket->title,
        ]);
    }

    protected function fillFromTicket(): void
    {
        $this->status = (string) ((int) $this->ticket->status);
        $this->cateId = $this->ticket->cate_id ? (string) $this->ticket->cate_id : '';
        $this->typeId = $this->ticket->type_id ? (string) $this->ticket->type_id : '';
        $this->pin = $this->ticket->pin ? '1' : '0';
        $this->labelIds = $this->ticket->labels()->pluck('support_labels.id')->map(fn ($id) => (int) $id)->all();
    }

    protected function findTicketComment(int $commentId): SupportComment
    {
        return SupportComment::query()
            ->where('ticket_id', $this->ticket->id)
            ->findOrFail($commentId);
    }

    protected function sanitizeCommentHtml(string $html): string
    {
        $html = trim($html);

        if ($html === '') {
            return '';
        }

        $previous = libxml_use_internal_errors(true);

        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->loadHTML(
            '<?xml encoding="utf-8" ?><div data-support-comment-root="1">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );

        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = null;

        foreach ($dom->getElementsByTagName('div') as $div) {
            if ($div instanceof DOMElement && $div->getAttribute('data-support-comment-root') === '1') {
                $root = $div;
                break;
            }
        }

        if (! $root instanceof DOMElement) {
            return '';
        }

        $dangerousTags = [
            'script', 'style', 'iframe', 'object', 'embed', 'form', 'input',
            'button', 'textarea', 'select', 'option', 'link', 'meta', 'base',
        ];

        foreach ($dangerousTags as $tag) {
            while (($nodes = $root->getElementsByTagName($tag))->length > 0) {
                $node = $nodes->item(0);
                $node?->parentNode?->removeChild($node);
            }
        }

        $elements = [];
        foreach ($root->getElementsByTagName('*') as $element) {
            if ($element instanceof DOMElement) {
                $elements[] = $element;
            }
        }

        foreach ($elements as $element) {
            $attributes = [];

            foreach ($element->attributes ?? [] as $attribute) {
                $attributes[] = $attribute->nodeName;
            }

            foreach ($attributes as $attributeName) {
                $lowerName = strtolower($attributeName);
                $value = (string) $element->getAttribute($attributeName);

                if (str_starts_with($lowerName, 'on')) {
                    $element->removeAttribute($attributeName);
                    continue;
                }

                if (in_array($lowerName, ['href', 'src'], true) && ! $this->isSafeHtmlUrl($value, $lowerName === 'src')) {
                    $element->removeAttribute($attributeName);
                    continue;
                }

                if ($lowerName === 'style') {
                    $style = $this->sanitizeCommentStyle($value);

                    if ($style === '') {
                        $element->removeAttribute($attributeName);
                    } else {
                        $element->setAttribute('style', $style);
                    }

                    continue;
                }
            }

            if ($element->tagName === 'a') {
                $href = (string) $element->getAttribute('href');

                if ($href !== '' && ! $element->hasAttribute('rel')) {
                    $element->setAttribute('rel', 'nofollow noopener noreferrer');
                }
            }
        }

        $cleanHtml = '';

        foreach ($root->childNodes as $childNode) {
            $cleanHtml .= $dom->saveHTML($childNode);
        }

        return trim($cleanHtml);
    }

    protected function sanitizeCommentStyle(string $style): string
    {
        $allowed = [];

        foreach (explode(';', $style) as $declaration) {
            $declaration = trim($declaration);

            if ($declaration === '' || ! str_contains($declaration, ':')) {
                continue;
            }

            [$property, $value] = array_map('trim', explode(':', $declaration, 2));
            $property = strtolower($property);
            $lowerValue = strtolower($value);

            if (str_contains($lowerValue, 'expression') || str_contains($lowerValue, 'javascript:') || str_contains($lowerValue, 'url(')) {
                continue;
            }

            if ($property === 'text-align' && in_array($lowerValue, ['left', 'right', 'center', 'justify'], true)) {
                $allowed[] = 'text-align: '.$lowerValue;
                continue;
            }

            if (in_array($property, ['color', 'background-color'], true)
                && preg_match('/^(#[0-9a-f]{3,8}|rgb[a]?\([0-9., %]+\)|hsl[a]?\([0-9., %]+\)|[a-z -]+)$/i', $value)
            ) {
                $allowed[] = $property.': '.$value;
            }
        }

        return implode('; ', $allowed);
    }

    protected function isSafeHtmlUrl(string $url, bool $allowImageData = false): bool
    {
        $url = trim($url);

        if ($url === '') {
            return false;
        }

        if ($allowImageData && preg_match('/^data:image\/[a-z0-9.+-]+;base64,[a-z0-9\/+=\s]+$/i', $url)) {
            return true;
        }

        if (str_starts_with($url, '#') || str_starts_with($url, '/')) {
            return true;
        }

        return (bool) preg_match('/^(https?:|mailto:|tel:)/i', $url);
    }

    protected function hasMeaningfulHtmlContent(string $html): bool
    {
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\xc2\xa0", ' ', $text);

        return trim($text) !== '';
    }
}
