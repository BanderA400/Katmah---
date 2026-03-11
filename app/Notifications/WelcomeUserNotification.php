<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeUserNotification extends Notification
{
    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = trim((string) ($notifiable->name ?? ''));
        $greetingName = $name !== '' ? $name : 'بك';

        return (new MailMessage)
            ->subject('مرحبًا بك في ختمة')
            ->greeting("أهلًا {$greetingName}")
            ->line('تم إنشاء حسابك بنجاح في منصة ختمة.')
            ->line('نقدر تبدأ الآن مباشرة بتنظيم ختمتك اليومية ومتابعة تقدمك.')
            ->action('الدخول إلى المنصة', url('/app'))
            ->line('إذا احتجت أي مساعدة، تواصل معنا في أي وقت.');
    }
}
