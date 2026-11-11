@component(theme_view('layouts.auth', 'guest'), ['title' => __('Forgot password')])
    @livewire(\App\Livewire\Auth\ForgotPasswordPage::class)
@endcomponent
