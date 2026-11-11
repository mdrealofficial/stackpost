@php($isEditing = $category->exists)

@component(theme_view('layouts.app', 'app'), ['title' => $isEditing ? __('Edit Blog Category') : __('Create Blog Category')])
    <x-ui.form-page
            :eyebrow="__('Blogs')"
            :title="$isEditing ? __('Edit Blog Category') : __('Create Blog Category')"
            :description="$isEditing ? __('Update labels, localization, and visibility for this category.') : __('Create a reusable category for organizing multilingual blog posts.')"
    >
        <x-slot:actions>
            <x-ui.button :href="route('admin-blog-categories.index')" variant="outline" wire:navigate>
                {{ __('Back to categories') }}
            </x-ui.button>
        </x-slot:actions>

        <form method="POST" action="{{ $isEditing ? route('admin-blog-categories.update', $category) : route('admin-blog-categories.store') }}" class="space-y-6">
            @csrf
            @if ($isEditing)
                @method('PUT')
            @endif

            @include('adminblogcategories::partials.fields', ['category' => $category])

            <x-ui.form-actions
                :cancel-href="route('admin-blog-categories.index')"
                :submit-label="__('Save changes')"
            />
        </form>
    </x-ui.form-page>
@endcomponent
