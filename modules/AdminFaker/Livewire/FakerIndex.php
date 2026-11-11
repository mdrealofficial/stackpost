<?php

namespace Modules\AdminFaker\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Modules\AdminFaker\Support\AdminFakerService;
use Modules\AdminUser\Models\User;

#[Title('Admin Faker')]
class FakerIndex extends Component
{
    public bool $clearBeforeSeed = true;

    public ?string $statusMessage = null;

    public string $statusVariant = 'success';

    /** @var array<string, mixed> */
    public array $result = [];

    public function seedDemoData(AdminFakerService $faker): void
    {
        $this->result = $faker->seedForFirstUser($this->clearBeforeSeed);

        $this->statusVariant = 'success';
        $this->statusMessage = __('Demo workspace generated successfully.');
    }

    public function clearDemoData(AdminFakerService $faker): void
    {
        $this->result = $faker->clearForFirstUser();
        $this->statusVariant = 'success';
        $this->statusMessage = __('Demo workspace data cleared successfully.');
    }

    public function render(): View
    {
        return view('adminfaker::index', [
            'previewUser' => User::query()->orderBy('id')->first(),
        ])->layout(theme_view('layouts.app', 'app'), [
            'title' => __('Admin Faker'),
        ]);
    }
}
