<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPassword extends Notification
{
    use Queueable;

    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = url(route('password.reset', ['token' => $this->token]).'?email='.urlencode($notifiable->email));

        return (new MailMessage)
            ->subject(__('emails.reset.subject'))
            ->markdown('mail.password-reset', [
                'user' => $notifiable,
                'url' => $url,
            ]);
    }
}
