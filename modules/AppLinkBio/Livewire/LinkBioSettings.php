<?php

namespace Modules\AppLinkBio\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AdminSettings\Support\OptionStore;
use Modules\AppLinkBio\Support\LinkBioAccess;
use Modules\AppLinkBio\Support\LinkBioTemplateCatalog;

#[Title('Link Bio Settings')]
class LinkBioSettings extends Component
{
    protected OptionStore $options;

    public string $defaultTemplate = 'aurora';

    public string $defaultBrandingText = 'Powered by Link Bio';

    public string $defaultStatus = 'draft';

    public function boot(OptionStore $options): void
    {
        $this->options = $options;
    }

    public function mount(LinkBioAccess $access): void
    {
        $this->defaultTemplate = $access->defaultTemplateKey();
        $this->defaultBrandingText = $access->defaultBrandingText();
        $this->defaultStatus = $access->defaultStatus();
    }

    public function save(): void
    {
        $validated = $this->validate([
            'defaultTemplate' => ['required', Rule::in(collect(LinkBioTemplateCatalog::all())->pluck('key')->all())],
            'defaultBrandingText' => ['nullable', 'string', 'max:160'],
            'defaultStatus' => ['required', Rule::in(['draft', 'published'])],
        ], [], [
            'defaultTemplate' => __('default template'),
            'defaultBrandingText' => __('default branding text'),
            'defaultStatus' => __('default page status'),
        ]);

        $this->options->set('link_bio_default_template', $validated['defaultTemplate']);
        $this->options->set('link_bio_default_branding_text', trim((string) $validated['defaultBrandingText']) ?: 'Powered by Link Bio');
        $this->options->set('link_bio_default_status', $validated['defaultStatus']);

        $this->dispatch('settings-saved');
        $this->dispatch('app-toast', type: 'success', message: __('Link Bio settings saved.'));
    }

    public function setDefaultTemplate(string $key): void
    {
        $keys = collect(LinkBioTemplateCatalog::all())->pluck('key')->all();

        if (! in_array($key, $keys, true)) {
            return;
        }

        $this->defaultTemplate = $key;
    }

    public function render(): View
    {
        return view('applinkbio::settings.index', [
            'templates' => LinkBioTemplateCatalog::all(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Link Bio Settings'),
        ]);
    }
}
