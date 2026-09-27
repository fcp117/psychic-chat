<?php
namespace App\Notifications;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
class EmailVerificationCode extends Notification {
    public function __construct(public readonly string $code) {}
    public function via(object $notifiable): array { return ['mail']; }
    public function toMail(object $notifiable): MailMessage {
        return (new MailMessage)->subject('Your Intuition Island verification code')
            ->greeting('Verify your email address')
            ->line('Enter this six-digit code in Intuition Island:')
            ->line($this->code)
            ->line('This code expires in 10 minutes. Do not share it with anyone.')
            ->line('If you did not request this, you can ignore this email.');
    }
}
