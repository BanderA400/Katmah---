<?php

namespace App\Notifications;

use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DelayedWirdReminderNotification extends Notification
{
    /**
     * @param  array<int, array{name: string, remaining_today: int, remaining_total: int}>  $items
     */
    public function __construct(
        private readonly array $items,
        private readonly string $dashboardUrl,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $count = count($this->items);
        $first = $this->items[0] ?? null;

        $body = $count === 1 && is_array($first)
            ? "متبقي لك اليوم {$first['remaining_today']} صفحة في ختمة \"{$first['name']}\"."
            : "لديك {$count} ختمات متأخرة اليوم. افتح اللوحة لإكمال وردك.";

        return FilamentNotification::make()
            ->title('تذكير ورد اليوم')
            ->warning()
            ->body($body)
            ->getDatabaseMessage();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = trim((string) ($notifiable->name ?? ''));
        $greetingName = $name !== '' ? $name : 'بك';
        $count = count($this->items);
        $top = array_slice($this->items, 0, 3);

        $mail = (new MailMessage)
            ->subject('تذكير ورد اليوم | ختمة')
            ->greeting("أهلًا {$greetingName}")
            ->line('هذا تذكير ودي لإكمال وردك اليوم.')
            ->line($count === 1 ? 'لديك ختمة متأخرة اليوم:' : "لديك {$count} ختمات متأخرة اليوم:");

        foreach ($top as $item) {
            $mail->line("• {$item['name']}: المتبقي اليوم {$item['remaining_today']} صفحة");
        }

        if ($count > count($top)) {
            $mail->line('• ... يوجد ختمات أخرى متأخرة');
        }

        return $mail
            ->action('فتح لوحة الورد', $this->dashboardUrl)
            ->line('استمر، والقليل اليومي يصنع إنجازًا كبيرًا.');
    }
}
