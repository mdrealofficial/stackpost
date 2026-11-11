@component(theme_view('layouts.auth', 'guest'), ['title' => __('Log in')])
    @livewire(\App\Livewire\Auth\LoginPage::class)
@endcomponent
