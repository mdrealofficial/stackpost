<?php

namespace Modules\AppPayments\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentEventNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $subject,
        protected string $message,
        protected ?string $actionUrl = null,
        protected ?string $actionText = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->subject)
            ->greeting(__('Hello, :name', [
                'name' => $notifiable->name ?: $notifiable->username ?: __('there'),
            ]));

        foreach ($this->lines($this->message) as $line) {
            $mail->line($line);
        }

        if ($this->actionUrl) {
            $mail->action($this->actionText ?: __('Open billing'), $this->actionUrl);
        }

        return $mail;
    }

    /**
     * @return array<int, string>
     */
    protected function lines(string $message): array
    {
        return collect(preg_split("/(\r\n|\n|\r){2,}/", trim($message)) ?: [])
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->values()
            ->all();
    }
}
