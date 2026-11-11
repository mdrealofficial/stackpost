@props([
    'title' => __('Ready to publish changes?'),
    'description' => __('Save the active theme configuration.'),
    'resetLabel' => __('Set default'),
    'saveLabel' => __('Save changes'),
    'formId' => null,
    'intentModel' => null,
])

<div class="sticky bottom-4 z-20 flex justify-end">
    <div class="inline-flex items-center gap-4 rounded-[0.9rem] border border-slate-200 bg-white/95 px-4 py-3 shadow-lg shadow-slate-900/5 backdrop-blur dark:border-slate-800 dark:bg-slate-950/95">
        <div class="hidden text-right sm:block">
            <p class="text-sm font-semibold text-slate-950 dark:text-white">{{ $title }}</p>
            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $description }}</p>
        </div>
        @if ($intentModel)
            <x-ui.button
                type="submit"
                variant="secondary"
                name="intent"
                value="reset_defaults"
                :form="$formId"
                x-on:click="{{ $intentModel }} = 'reset_defaults'"
            >
                {{ $resetLabel }}
            </x-ui.button>
            <x-ui.button
                type="submit"
                :form="$formId"
                x-on:click="{{ $intentModel }} = ''"
            >
                {{ $saveLabel }}
            </x-ui.button>
        @else
            <x-ui.button
                type="submit"
                variant="secondary"
                name="intent"
                value="reset_defaults"
                :form="$formId"
            >
                {{ $resetLabel }}
            </x-ui.button>
            <x-ui.button
                type="submit"
                :form="$formId"
            >
                {{ $saveLabel }}
            </x-ui.button>
        @endif
    </div>
</div>
