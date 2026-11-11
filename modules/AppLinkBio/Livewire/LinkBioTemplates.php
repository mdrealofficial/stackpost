<?php

namespace Modules\AppLinkBio\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Modules\AppLinkBio\Models\LinkBioPage;
use Modules\AppLinkBio\Support\LinkBioAccess;
use Modules\AppLinkBio\Support\LinkBioTemplateCatalog;

#[Title('Link Bio Templates')]
class LinkBioTemplates extends Component
{
    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'category')]
    public string $category = 'All';

    public ?int $pageId = null;

    public function mount(Request $request): void
    {
        $id = $request->integer('page');

        if ($id > 0) {
            $page = \Modules\AppTeams\Support\PortalLibraryAccess::query(LinkBioPage::query())
                ->findOrFail($id);

            $this->pageId = (int) $page->id;
        }
    }

    public function render(LinkBioAccess $access): View
    {
        abort_unless($access->enabled(auth()->user()), 404);

        $templates = collect(LinkBioTemplateCatalog::all())
            ->when($this->category !== 'All', fn ($collection) => $collection->where('category', $this->category))
            ->when($this->search !== '', function ($collection) {
                $term = mb_strtolower($this->search);

                return $collection->filter(function (array $template) use ($term): bool {
                    return str_contains(mb_strtolower($template['label']), $term)
                        || str_contains(mb_strtolower($template['description']), $term)
                        || str_contains(mb_strtolower($template['category']), $term);
                });
            })
            ->values()
            ->all();

        return view('applinkbio::livewire.templates', [
            'templates' => $templates,
            'categories' => LinkBioTemplateCatalog::categories(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Link Bio Templates'),
        ]);
    }
}
