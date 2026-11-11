<?php

namespace Modules\AppSupport\Livewire;

use DOMDocument;
use DOMElement;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Component;
use Modules\AdminSupport\Models\SupportComment;
use Modules\AdminSupport\Models\SupportTicket;

class PortalSupportShow extends Component
{
    public SupportTicket $ticket;

    public string $comment = '';

    public int $replyEditorIteration = 0;

    public ?string $statusMessage = null;

    public function mount(SupportTicket $ticket): void
    {
        abort_unless($this->supportEnabled(), 404);
        abort_unless($ticket->uid === auth()->id(), 404);
        $this->ticket = $ticket;
        $this->ticket->forceFill(['user_read' => false])->save();
        $this->statusMessage = session('status');
    }

    public function sendReply(): void
    {
        abort_unless($this->supportEnabled(), 404);
        abort_unless($this->ticket->uid === auth()->id(), 404);

        if ((int) $this->ticket->status !== 1) {
            $this->addError('comment', __('This ticket is no longer open for replies.'));

            return;
        }

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
            'admin_read' => true,
            'user_read' => false,
            'changed' => time(),
        ]);

        log_activity('portal.support.reply', 'Added a support reply.', [
            'subject_type' => SupportTicket::class,
            'subject_id' => $this->ticket->id,
            'metadata' => [
                'ticket' => $this->ticket->id_secure,
            ],
        ]);

        $this->comment = '';
        $this->replyEditorIteration++;
        $this->dispatch('app-toast', type: 'success', message: __('Reply sent successfully.'));
    }

    public function resolveTicket(): void
    {
        abort_unless($this->supportEnabled(), 404);
        abort_unless($this->ticket->uid === auth()->id(), 404);

        $this->ticket->update([
            'status' => 2,
            'changed' => time(),
        ]);

        log_activity('portal.support.resolve', 'Marked a support ticket as resolved.', [
            'subject_type' => SupportTicket::class,
            'subject_id' => $this->ticket->id,
            'metadata' => [
                'ticket' => $this->ticket->id_secure,
            ],
        ]);

        $this->dispatch('app-toast', type: 'success', message: __('Ticket marked as resolved.'));
    }

    public function render(): View
    {
        $ticket = $this->ticket->load([
            'user:id,name,username,email',
            'category:id,name,color,icon',
            'type:id,name,color,icon',
            'labels:id,name,color,icon',
            'comments.user:id,name,username,email',
        ]);

        return view('appsupport::livewire.show', [
            'ticket' => $ticket,
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => $ticket->title,
        ]);
    }

    protected function supportEnabled(): bool
    {
        $user = auth()->user();

        return ! $user?->plan || $user->hasPlanFeature('support');
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
