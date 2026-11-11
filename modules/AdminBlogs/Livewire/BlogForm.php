<?php

namespace Modules\AdminBlogs\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Component;
use Modules\AdminBlogCategories\Models\BlogCategory;
use Modules\AdminBlogTags\Models\BlogTag;
use Modules\AdminBlogs\Models\Blog;
use Modules\AppFiles\Models\AppFile;

class BlogForm extends Component
{
    public Blog $blog;

    public bool $isEditing = false;

    public function mount(?Blog $blog = null): void
    {
        $this->blog = $blog?->exists ? $blog->load('tags:id') : new Blog(['status' => true]);
        $this->isEditing = $this->blog->exists;
    }

    public function render(): View
    {
        $user = auth()->user();

        return view('adminblogs::livewire.form', [
            'blog' => $this->blog,
            'categories' => BlogCategory::query()->where('status', true)->orderBy('sort_order')->orderBy('name')->get(),
            'tags' => BlogTag::query()->where('status', true)->orderBy('name')->get(),
            'authorFolders' => $user
                ? AppFile::query()->ownedBy($user)->where('is_folder', true)->orderBy('name')->get(['id', 'id_secure', 'name'])
                : collect(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => $this->isEditing ? __('Edit Blog Post') : __('Create Blog Post'),
        ]);
    }
}
