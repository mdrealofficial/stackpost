<div class="space-y-4">
    <x-ui.choice-group
        name="status"
        :label="__('Status')"
        :value="(int) old('status', (int) ($faq->status ?? true))"
        :error="$errors->first('status')"
        :options="[
            ['value' => 1, 'label' => __('Enable')],
            ['value' => 0, 'label' => __('Disable')],
        ]"
    />

    <x-ui.input name="title" :label="__('Title')" :value="old('title', $faq->title)" :error="$errors->first('title')" required autofocus />
    <x-ui.input name="slug" :label="__('Slug')" :value="old('slug', $faq->slug)" :error="$errors->first('slug')" :help="__('Leave empty to generate from the title.')" />
    <x-ui.textarea name="content" :label="__('Content')" :error="$errors->first('content')" rows="10">{{ old('content', $faq->content) }}</x-ui.textarea>
</div>
