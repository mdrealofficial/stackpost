<section class="mt-10 space-y-6">
    <div class="relative mb-5">
        <h3 class="text-lg font-semibold text-zinc-950 dark:text-white">{{ __('Delete account') }}</h3>
        <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ __('Delete your account and all of its resources') }}</p>
    </div>

    <div x-data="{ open: @js($errors->isNotEmpty()) }">
        <x-ui.button variant="danger" x-on:click.prevent="open = true">
            {{ __('Delete account') }}
        </x-ui.button>

        <div x-cloak x-show="open" class="fixed inset-0 z-50 flex items-center justify-center bg-zinc-950/60 p-4">
            <div class="w-full max-w-lg rounded-[1.75rem] border border-zinc-200 bg-white p-6 shadow-2xl dark:border-zinc-800 dark:bg-zinc-900">
                <form method="POST" wire:submit="deleteUser" class="space-y-6">
                    <div>
                        <h4 class="text-lg font-semibold text-zinc-950 dark:text-white">{{ __('Are you sure you want to delete your account?') }}</h4>
                        <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
                        </p>
                    </div>

                    <x-ui.input wire:model="password" :label="__('Password')" type="password" :error="$errors->first('password')" />

                    <div class="flex justify-end gap-2">
                        <x-ui.button type="button" variant="outline" x-on:click="open = false">{{ __('Cancel') }}</x-ui.button>
                        <x-ui.button variant="danger" type="submit">{{ __('Delete account') }}</x-ui.button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>
