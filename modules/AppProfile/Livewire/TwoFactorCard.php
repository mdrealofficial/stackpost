<?php

namespace Modules\AppProfile\Livewire;

use Exception;
use Illuminate\Contracts\View\View;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Modules\AdminSettings\Support\OptionStore;

class TwoFactorCard extends Component
{
    #[Locked]
    public bool $canManageTwoFactor = false;

    #[Locked]
    public bool $workspaceAllowsTwoFactor = false;

    #[Locked]
    public bool $twoFactorEnabled = false;

    #[Locked]
    public bool $requiresConfirmation = false;

    #[Locked]
    public string $qrCodeSvg = '';

    #[Locked]
    public string $manualSetupKey = '';

    public bool $showModal = false;

    public bool $showVerificationStep = false;

    #[Validate('required|string|size:6', onUpdate: false)]
    public string $code = '';

    public function mount(DisableTwoFactorAuthentication $disableTwoFactorAuthentication, OptionStore $options): void
    {
        $this->workspaceAllowsTwoFactor = $options->get('auth_two_factor_authentication_status', '1') === '1';
        $this->canManageTwoFactor = $this->workspaceAllowsTwoFactor && Features::canManageTwoFactorAuthentication();

        if (! $this->canManageTwoFactor) {
            return;
        }

        if (Fortify::confirmsTwoFactorAuthentication() && is_null(auth()->user()->two_factor_confirmed_at)) {
            $disableTwoFactorAuthentication(auth()->user());
        }

        $this->twoFactorEnabled = auth()->user()->hasEnabledTwoFactorAuthentication();
        $this->requiresConfirmation = Features::optionEnabled(Features::twoFactorAuthentication(), 'confirm');
    }

    public function enable(EnableTwoFactorAuthentication $enableTwoFactorAuthentication): void
    {
        if (! $this->canManageTwoFactor) {
            return;
        }

        $enableTwoFactorAuthentication(auth()->user());

        if (! $this->requiresConfirmation) {
            $this->twoFactorEnabled = auth()->user()->hasEnabledTwoFactorAuthentication();
        }

        $this->loadSetupData();

        $this->showModal = true;
    }

    public function disable(DisableTwoFactorAuthentication $disableTwoFactorAuthentication): void
    {
        if (! $this->canManageTwoFactor) {
            return;
        }

        $disableTwoFactorAuthentication(auth()->user());

        $this->twoFactorEnabled = false;
    }

    public function showVerificationIfNecessary(): void
    {
        if (! $this->canManageTwoFactor) {
            return;
        }

        if ($this->requiresConfirmation) {
            $this->showVerificationStep = true;
            $this->resetErrorBag();

            return;
        }

        $this->closeModal();
    }

    public function confirmTwoFactor(ConfirmTwoFactorAuthentication $confirmTwoFactorAuthentication): void
    {
        if (! $this->canManageTwoFactor) {
            return;
        }

        $this->validate();

        $confirmTwoFactorAuthentication(auth()->user(), $this->code);

        $this->closeModal();

        $this->twoFactorEnabled = true;
    }

    public function resetVerification(): void
    {
        $this->reset('code', 'showVerificationStep');
        $this->resetErrorBag();
    }

    public function closeModal(): void
    {
        $this->reset(
            'code',
            'manualSetupKey',
            'qrCodeSvg',
            'showModal',
            'showVerificationStep',
        );

        $this->resetErrorBag();

        if (! $this->requiresConfirmation) {
            $this->twoFactorEnabled = auth()->user()->hasEnabledTwoFactorAuthentication();
        }
    }

    public function getModalConfigProperty(): array
    {
        if ($this->twoFactorEnabled) {
            return [
                'title' => __('Two-factor authentication enabled'),
                'description' => __('Scan the QR code or store the setup key in your authenticator app to complete setup.'),
                'buttonText' => __('Close'),
            ];
        }

        if ($this->showVerificationStep) {
            return [
                'title' => __('Verify authentication code'),
                'description' => __('Enter the 6-digit code from your authenticator app to finish enabling 2FA.'),
                'buttonText' => __('Continue'),
            ];
        }

        return [
            'title' => __('Enable two-factor authentication'),
            'description' => __('Pair your authenticator app with this account using the QR code or manual setup key below.'),
            'buttonText' => __('Continue'),
        ];
    }

    private function loadSetupData(): void
    {
        $user = auth()->user();

        try {
            $this->qrCodeSvg = $user?->twoFactorQrCodeSvg();
            $this->manualSetupKey = decrypt($user->two_factor_secret);
        } catch (Exception) {
            $this->addError('setupData', __('Failed to fetch setup data.'));
            $this->reset('qrCodeSvg', 'manualSetupKey');
        }
    }

    public function render(): View
    {
        return view('appprofile::livewire.two-factor-card');
    }
}
