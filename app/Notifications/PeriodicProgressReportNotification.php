<?php

namespace App\Notifications;

use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PeriodicProgressReportNotification extends Notification
{
    /**
     * @param  array{
     *     pages_completed: int,
     *     done_days: int,
     *     missed_days: int,
     *     commitment_rate: float,
     *     completed_khatmas: int,
     *     active_khatmas: int,
     *     has_progress: bool,
     *     encouragement_message: string
     * }  $metrics
     */
    public function __construct(
        private readonly string $period,
        private readonly string $periodLabel,
        private readonly string $rangeLabel,
        private readonly array $metrics,
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
        $title = $this->period === 'monthly'
            ? 'تقريرك الشهري'
            : 'تقريرك الأسبوعي';

        $body = $this->metrics['has_progress']
            ? "أنجزت {$this->metrics['pages_completed']} صفحة، ونسبة الالتزام {$this->metrics['commitment_rate']}٪."
            : (string) $this->metrics['encouragement_message'];

        return FilamentNotification::make()
            ->title($title)
            ->body($body)
            ->info()
            ->getDatabaseMessage();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = trim((string) ($notifiable->name ?? ''));
        $greetingName = $name !== '' ? $name : 'بك';
        $title = $this->period === 'monthly'
            ? 'تقريرك الشهري | ختمة'
            : 'تقريرك الأسبوعي | ختمة';

        $mail = (new MailMessage)
            ->subject($title)
            ->greeting("أهلًا {$greetingName}")
            ->line("ملخص {$this->periodLabel}")
            ->line("الفترة: {$this->rangeLabel}");

        if ($this->metrics['has_progress']) {
            $mail
                ->line("الصفحات المنجزة: {$this->metrics['pages_completed']} صفحة")
                ->line("الأيام المنجزة: {$this->metrics['done_days']} يوم")
                ->line("الأيام المتأخرة: {$this->metrics['missed_days']} يوم")
                ->line("نسبة الالتزام: {$this->metrics['commitment_rate']}٪")
                ->line("الختمات المكتملة: {$this->metrics['completed_khatmas']}")
                ->line("الختمات النشطة: {$this->metrics['active_khatmas']}");
        } else {
            $mail->line((string) $this->metrics['encouragement_message']);
        }

        return $mail
            ->action('فتح لوحة التحكم', $this->dashboardUrl)
            ->line('استمر بخطى ثابتة، والإنجاز يتراكم يومًا بعد يوم.');
    }
}
