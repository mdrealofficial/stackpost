<section class="w-full">
    <x-settings.layout :heading="__('Embed Code')" :subheading="__('Paste third-party scripts such as live chat, widgets, or tracking snippets and choose where they should be injected.')">
        <form wire:submit="save" class="my-6 w-full space-y-6">
            <x-theme.section-card
                :title="__('Embed status')"
                :description="__('Use a single switch to enable or disable all custom embed code blocks.')"
                body-class="space-y-5 p-6"
            >
                <x-ui.field :label="__('Embed code status')" :error="$errors->first('embed_code_status')">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <x-ui.radio name="embed-code-status" value="1" wire:model="embed_code_status" :label="__('Enable')" />
                        <x-ui.radio name="embed-code-status" value="0" wire:model="embed_code_status" :label="__('Disable')" />
                    </div>
                </x-ui.field>
            </x-theme.section-card>

            <x-theme.section-card
                :title="__('Guest site')"
                :description="__('Paste code for the public website. Live chat snippets usually go before the closing body tag.')"
                body-class="space-y-5 p-6"
            >
                <div x-data="{ content: @entangle('embed_code_guest_head').defer }" data-code-editor-sync>
                    <x-ui.code-editor
                        name="embed_code_guest_head_editor"
                        :label="__('Head code')"
                        :value="$embed_code_guest_head"
                        mode="htmlmixed"
                        rows="8"
                    />
                    <textarea x-model="content" wire:model.defer="embed_code_guest_head" class="hidden"></textarea>
                    @if ($errors->first('embed_code_guest_head'))
                        <p class="mt-2 text-sm font-medium" style="color: var(--theme-danger-color);">{{ $errors->first('embed_code_guest_head') }}</p>
                    @else
                        <p class="mt-2 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Injected inside the <head> of guest pages.') }}</p>
                    @endif
                </div>

                <div x-data="{ content: @entangle('embed_code_guest_body_end').defer }" data-code-editor-sync>
                    <x-ui.code-editor
                        name="embed_code_guest_body_end_editor"
                        :label="__('Body end code')"
                        :value="$embed_code_guest_body_end"
                        mode="htmlmixed"
                        rows="10"
                    />
                    <textarea x-model="content" wire:model.defer="embed_code_guest_body_end" class="hidden"></textarea>
                    @if ($errors->first('embed_code_guest_body_end'))
                        <p class="mt-2 text-sm font-medium" style="color: var(--theme-danger-color);">{{ $errors->first('embed_code_guest_body_end') }}</p>
                    @else
                        <p class="mt-2 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Injected right before </body> on guest pages.') }}</p>
                    @endif
                </div>
            </x-theme.section-card>

            <x-theme.section-card
                :title="__('Logged-in app')"
                :description="__('Paste code for the dashboard/app area if you need widgets or scripts after users sign in.')"
                body-class="space-y-5 p-6"
            >
                <div x-data="{ content: @entangle('embed_code_app_head').defer }" data-code-editor-sync>
                    <x-ui.code-editor
                        name="embed_code_app_head_editor"
                        :label="__('Head code')"
                        :value="$embed_code_app_head"
                        mode="htmlmixed"
                        rows="8"
                    />
                    <textarea x-model="content" wire:model.defer="embed_code_app_head" class="hidden"></textarea>
                    @if ($errors->first('embed_code_app_head'))
                        <p class="mt-2 text-sm font-medium" style="color: var(--theme-danger-color);">{{ $errors->first('embed_code_app_head') }}</p>
                    @else
                        <p class="mt-2 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Injected inside the <head> of app pages.') }}</p>
                    @endif
                </div>

                <div x-data="{ content: @entangle('embed_code_app_body_end').defer }" data-code-editor-sync>
                    <x-ui.code-editor
                        name="embed_code_app_body_end_editor"
                        :label="__('Body end code')"
                        :value="$embed_code_app_body_end"
                        mode="htmlmixed"
                        rows="10"
                    />
                    <textarea x-model="content" wire:model.defer="embed_code_app_body_end" class="hidden"></textarea>
                    @if ($errors->first('embed_code_app_body_end'))
                        <p class="mt-2 text-sm font-medium" style="color: var(--theme-danger-color);">{{ $errors->first('embed_code_app_body_end') }}</p>
                    @else
                        <p class="mt-2 text-sm" style="color: var(--theme-muted-text-color);">{{ __('Injected right before </body> on app pages.') }}</p>
                    @endif
                </div>
            </x-theme.section-card>

            <div class="flex items-center gap-4">
                <x-ui.button type="submit">{{ __('Save changes') }}</x-ui.button>

                <x-action-message class="text-emerald-600 dark:text-emerald-400" on="settings-saved">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>
    </x-settings.layout>

    <link rel="stylesheet" href="{{ theme_shared_asset('plugins/codemirror5/lib/codemirror.css') }}">
    <link rel="stylesheet" href="{{ theme_shared_asset('plugins/codemirror5/theme/material-darker.css') }}">
    <script src="{{ theme_shared_asset('plugins/codemirror5/lib/codemirror.js') }}"></script>
    <script src="{{ theme_shared_asset('plugins/codemirror5/mode/xml/xml.js') }}"></script>
    <script src="{{ theme_shared_asset('plugins/codemirror5/mode/javascript/javascript.js') }}"></script>
    <script src="{{ theme_shared_asset('plugins/codemirror5/mode/css/css.js') }}"></script>
    <script src="{{ theme_shared_asset('plugins/codemirror5/mode/htmlmixed/htmlmixed.js') }}"></script>
    <script>
        (() => {
            const initEmbedCodeEditors = () => {
                if (typeof CodeMirror === 'undefined') {
                    return;
                }

                document.querySelectorAll('[data-code-editor-sync]').forEach((wrapper) => {
                    const textarea = wrapper.querySelector('textarea[data-code-editor]');
                    const hiddenField = wrapper.querySelector('textarea[wire\\:model\\.defer]');

                    if (!textarea || !hiddenField || textarea.dataset.editorReady === 'true') {
                        return;
                    }

                    textarea.dataset.editorReady = 'true';

                    const editor = CodeMirror.fromTextArea(textarea, {
                        mode: textarea.dataset.codeEditor,
                        theme: 'material-darker',
                        lineNumbers: true,
                        lineWrapping: true,
                        indentUnit: 4,
                        tabSize: 4,
                        indentWithTabs: false,
                        viewportMargin: Infinity,
                    });

                    editor.setSize(null, 280);
                    editor.on('change', (instance) => {
                        hiddenField.value = instance.getValue();
                        hiddenField.dispatchEvent(new Event('input', { bubbles: true }));
                        hiddenField.dispatchEvent(new Event('change', { bubbles: true }));
                    });
                });
            };

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initEmbedCodeEditors, { once: true });
            } else {
                initEmbedCodeEditors();
            }

            document.addEventListener('livewire:navigated', initEmbedCodeEditors);
        })();
    </script>
</section>
