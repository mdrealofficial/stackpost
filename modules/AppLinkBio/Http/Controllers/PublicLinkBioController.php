<?php

namespace Modules\AppLinkBio\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Modules\AppLinkBio\Models\LinkBioEvent;
use Modules\AppLinkBio\Models\LinkBioPage;
use Modules\AppLinkBio\Support\LinkBioTemplateCatalog;

class PublicLinkBioController extends Controller
{
    public function __invoke(Request $request, string $slug): View
    {
        $page = LinkBioPage::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        $this->recordEvent($request, $page, 'view');

        return view('applinkbio::public.show', [
            'page' => $page,
            'theme' => LinkBioTemplateCatalog::find((string) $page->template_key),
        ]);
    }

    public function click(Request $request, string $slug, int $block, int $item): RedirectResponse
    {
        $page = LinkBioPage::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        $url = trim((string) data_get($page->blocks ?? [], $block.'.items.'.$item.'.url', ''));

        if ($url === '') {
            return redirect()->route('link-bio.public.show', ['slug' => $page->slug]);
        }

        $this->recordEvent($request, $page, 'click', $block, $item, $url);

        return redirect()->away($url);
    }

    protected function recordEvent(Request $request, LinkBioPage $page, string $type, ?int $block = null, ?int $item = null, ?string $url = null): void
    {
        LinkBioEvent::query()->create([
            'link_bio_page_id' => (int) $page->id,
            'type' => $type,
            'block_index' => $block,
            'item_index' => $item,
            'url' => $url,
            'ip_hash' => $request->ip() ? hash('sha256', (string) $request->ip()) : null,
            'user_agent' => Str::limit((string) ($request->userAgent() ?? ''), 255, ''),
        ]);
    }
}
