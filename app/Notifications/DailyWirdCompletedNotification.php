<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DailyWirdCompletedNotification extends Notification
{
    public function __construct(
        private readonly string $khatmaName,
        private readonly int $remainingTotalPages,
        private readonly ?string $expectedEndDateLabel,
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

        $mail = (new MailMessage)
            ->subject('مبروك إنجاز ورد اليوم | ختمة')
            ->greeting("مبروك {$greetingName}")
            ->line("أحسنت! أنجزت ورد اليوم في ختمة \"{$this->khatmaName}\".")
            ->line("المتبقي على الختمة: {$this->remainingTotalPages} صفحة.");

        if ($this->expectedEndDateLabel !== null && $this->expectedEndDateLabel !== '') {
            $mail->line("الختم المتوقع: {$this->expectedEndDateLabel}.");
        }

        return $mail
            ->action('متابعة تقدّمك', $this->dashboardUrl)
            ->line('ثباتك اليومي هو سر الوصول.');
    }
}
