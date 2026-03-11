<?php

namespace App\Providers;

use App\Models\User;
use App\Notifications\WelcomeUserNotification;
use Filament\Auth\Events\Registered as FilamentRegistered;
use Filament\Auth\Notifications\ResetPassword as FilamentResetPassword;
use Filament\Auth\Notifications\VerifyEmail as FilamentVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        config([
            'mail.default' => env('MAIL_MAILER', 'resend'),
            'mail.from.address' => env('MAIL_FROM_ADDRESS', 'noreply@getkhatmah.com'),
            'mail.from.name' => env('MAIL_FROM_NAME', 'ختمة'),
        ]);

        Event::listen(FilamentRegistered::class, function (FilamentRegistered $event): void {
            $user = $event->getUser();

            if (! $user instanceof User) {
                return;
            }

            $user->notify(new WelcomeUserNotification());
        });

        FilamentResetPassword::toMailUsing(function (object $notifiable, string $token): MailMessage {
            $email = method_exists($notifiable, 'getEmailForPasswordReset')
                ? (string) $notifiable->getEmailForPasswordReset()
                : (string) ($notifiable->email ?? '');

            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $email,
            ], false));

            $name = trim((string) ($notifiable->name ?? ''));
            $greetingName = $name !== '' ? $name : 'بك';

            return (new MailMessage)
                ->subject('إعادة تعيين كلمة المرور | ختمة')
                ->greeting("أهلًا {$greetingName}")
                ->line('وصلنا طلب إعادة تعيين كلمة المرور لحسابك في ختمة.')
                ->action('إعادة تعيين كلمة المرور', $url)
                ->line('رابط الاستعادة مؤقت وينتهي خلال فترة قصيرة.')
                ->line('إذا لم تطلب إعادة التعيين، تجاهل هذه الرسالة.');
        });

        FilamentVerifyEmail::toMailUsing(function (object $notifiable, string $url): MailMessage {
            $name = trim((string) ($notifiable->name ?? ''));
            $greetingName = $name !== '' ? $name : 'بك';

            return (new MailMessage)
                ->subject('تأكيد البريد الإلكتروني | ختمة')
                ->greeting("أهلًا {$greetingName}")
                ->line('اضغط الزر التالي لتأكيد بريدك الإلكتروني في ختمة.')
                ->action('تأكيد البريد الإلكتروني', $url)
                ->line('إذا لم تنشئ هذا الحساب، تجاهل هذه الرسالة.');
        });
    }
}
