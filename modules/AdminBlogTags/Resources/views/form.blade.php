@php($isEditing = $tag->exists)

@component(theme_view('layouts.app', 'app'), ['title' => $isEditing ? __('Edit Blog Tag') : __('Create Blog Tag')])
    <x-ui.form-page
            :eyebrow="__('Blogs')"
            :title="$isEditing ? __('Edit Blog Tag') : __('Create Blog Tag')"
            :description="$isEditing ? __('Update labels, styling, and localization for this tag.') : __('Create a reusable multilingual tag for filtering blog content.')"
    >
        <x-slot:actions>
            <x-ui.button :href="route('admin-blog-tags.index')" variant="outline" wire:navigate>
                {{ __('Back to tags') }}
            </x-ui.button>
        </x-slot:actions>

        <form method="POST" action="{{ $isEditing ? route('admin-blog-tags.update', $tag) : route('admin-blog-tags.store') }}" class="space-y-6">
            @csrf
            @if ($isEditing)
                @method('PUT')
            @endif

            @include('adminblogtags::partials.fields', ['tag' => $tag])

            <x-ui.form-actions
                :cancel-href="route('admin-blog-tags.index')"
                :submit-label="__('Save changes')"
            />
        </form>
    </x-ui.form-page>
@endcomponent
