<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class KhatmaCompletedNotification extends Notification
{
    public function __construct(
        private readonly string $khatmaName,
        private readonly string $dashboardUrl,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = trim((string) ($notifiable->name ?? ''));
        $greetingName = $name !== '' ? $name : 'بك';

        return (new MailMessage)
            ->subject('مبروك، أنهيت الختمة | ختمة')
            ->greeting("مبارك {$greetingName}")
            ->line("مبروك عليك إكمال ختمة \"{$this->khatmaName}\".")
            ->line('نسأل الله أن يجعلها في ميزان حسناتك.')
            ->action('الانتقال إلى لوحة التحكم', $this->dashboardUrl)
            ->line('ابدأ ختمة جديدة واستمر في رحلتك المباركة.');
    }
}
