@component(theme_view('layouts.auth', 'guest'), ['title' => __('Reset password')])
    @livewire(\App\Livewire\Auth\ResetPasswordPage::class, ['token' => (string) request()->route('token'), 'email' => (string) request('email')])
@endcomponent
