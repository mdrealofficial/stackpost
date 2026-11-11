<section class="w-full">
    <x-ui.page-hero
        :eyebrow="__('Backend workspace')"
        :title="__('API Integration')"
        :description="__('Configure provider credentials from modular integration items. Connected accounts stay in the user dashboard.')"
        :count="count($providers)"
        icon="fa-light fa-plug-circle-bolt"
    />

    <div class="mt-6">
        @if (blank($providers))
            @include('appintegrations::livewire.partials.empty-state')
        @else
            @include('appintegrations::livewire.partials.workspace')
        @endif
    </div>
</section>
