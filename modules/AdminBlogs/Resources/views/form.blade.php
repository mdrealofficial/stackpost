@php($isEditing = $blog->exists)

@component(theme_view('layouts.app', 'app'), ['title' => $isEditing ? __('Edit Blog Post') : __('Create Blog Post')])
<div class="space-y-6">
        
        <x-ui.form-page
            :eyebrow="__('Blogs')"
            :title="$isEditing ? __('Edit Blog Post') : __('Create Blog Post')"
            :description="$isEditing ? __('Refine localized content, metadata, and taxonomy assignments for this post.') : __('Publish a new multilingual blog post with localized content, category, and tags.')"
        >
            <x-slot:actions>
                @if ($isEditing)
                    <form method="POST" action="{{ route('admin-blogs.duplicate', $blog) }}">
                        @csrf
                        <x-ui.button type="submit" variant="outline">
                            <i class="fa-light fa-copy"></i>
                            {{ __('Duplicate') }}
                        </x-ui.button>
                    </form>
                    <x-ui.button :href="route('admin-blogs.preview', $blog)" variant="outline">
                        <i class="fa-light fa-eye"></i>
                        {{ __('Preview') }}
                    </x-ui.button>
                @endif
                <x-ui.button :href="route('admin-blogs.index')" variant="outline" wire:navigate>
                    {{ __('Back to posts') }}
                </x-ui.button>
            </x-slot:actions>
        
            <form method="POST" action="{{ $isEditing ? route('admin-blogs.update', $blog) : route('admin-blogs.store') }}" class="space-y-6">
                @csrf
                @if ($isEditing)
                    @method('PUT')
                @endif

                @include('adminblogs::partials.fields', ['blog' => $blog, 'categories' => $categories, 'tags' => $tags])

                <x-ui.form-actions
                    :cancel-href="route('admin-blogs.index')"
                    :submit-label="__('Save changes')"
                />
            </form>
        </x-ui.form-page>
</div>
@endcomponent
