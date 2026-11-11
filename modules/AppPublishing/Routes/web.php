<?php

use Illuminate\Support\Facades\Route;
use Modules\AppPublishing\Livewire\PublishingCalendar;
use Modules\AppPublishing\Livewire\PublishingCampaigns;
use Modules\AppPublishing\Livewire\PublishingApprovals;
use Modules\AppPublishing\Livewire\PublishingDrafts;
use Modules\AppPublishing\Livewire\PublishingLabels;
use Modules\AppPublishing\Livewire\PublishingQueue;

Route::middleware(['web', 'auth', 'verified'])->group(function (): void {
    Route::livewire('portal/publishing', PublishingCalendar::class)
        ->name('portal.publishing.calendar');
    Route::livewire('portal/publishing/drafts', PublishingDrafts::class)
        ->name('portal.publishing.drafts');
    Route::livewire('portal/publishing/queue', PublishingQueue::class)
        ->name('portal.publishing.queue');
    Route::livewire('portal/publishing/approvals', PublishingApprovals::class)
        ->name('portal.publishing.approvals');
    Route::livewire('portal/publishing/campaigns', PublishingCampaigns::class)
        ->name('portal.publishing.campaigns');
    Route::livewire('portal/publishing/labels', PublishingLabels::class)
        ->name('portal.publishing.labels');
});
