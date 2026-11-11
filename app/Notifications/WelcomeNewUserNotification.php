<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNewUserNotification extends Notification
{
    use Queueable;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Welcome to :app', ['app' => config('app.name', 'Stackposts')]))
            ->greeting(__('Welcome, :name!', ['name' => $notifiable->name ?: $notifiable->username ?: __('there')]))
            ->line(__('Your account is ready and you can now start using the platform.'))
            ->action(__('Open dashboard'), route('portal.dashboard'))
            ->line(__('Thank you for joining us.'));
    }
}
