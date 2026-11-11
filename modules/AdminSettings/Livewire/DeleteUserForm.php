<?php

namespace Modules\AdminSettings\Livewire;

use App\Concerns\PasswordValidationRules;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Modules\AdminSettings\Actions\Logout;

class DeleteUserForm extends Component
{
    use PasswordValidationRules;

    public string $password = '';

    public function deleteUser(Logout $logout): void
    {
        $this->validate([
            'password' => $this->currentPasswordRules(),
        ]);

        tap(Auth::user(), $logout(...))->delete();

        $this->redirect('/', navigate: true);
    }

    public function render(): View
    {
        return view('adminsettings::livewire.delete-user-form');
    }
}
