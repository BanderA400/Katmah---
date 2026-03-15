<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\HistoryExportController;
use App\Http\Controllers\ControlRecoveryController;
use App\Support\AppSettings;
use App\Support\LandingVisitTracker;
use Filament\Auth\Http\Controllers\EmailVerificationController as FilamentEmailVerificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SocialAuthController;


Route::get('/', function (Request $request) {
    $totalVisits = 0;
    $todayUniqueVisitors = 0;
    $landingSettings = AppSettings::getMany([
        AppSettings::KEY_LANDING_CONTACT_EMAIL => null,
        AppSettings::KEY_LANDING_X_URL => null,
        AppSettings::KEY_LANDING_SHOW_VISIT_COUNTER => true,
    ]);
    $landingContactEmail = $landingSettings[AppSettings::KEY_LANDING_CONTACT_EMAIL] ?? null;
    $landingXUrl = $landingSettings[AppSettings::KEY_LANDING_X_URL] ?? null;
    $landingShowVisitCounter = (bool) ($landingSettings[AppSettings::KEY_LANDING_SHOW_VISIT_COUNTER] ?? true);

    if ($landingShowVisitCounter) {
        try {
            $metrics = LandingVisitTracker::track($request);
            $totalVisits = (int) ($metrics['totalVisits'] ?? 0);
            $todayUniqueVisitors = (int) ($metrics['todayUniqueVisitors'] ?? 0);
        } catch (\Throwable $exception) {
            report($exception);
        }
    }

    return view('landing.index', [
        'totalVisits' => $totalVisits,
        'todayUniqueVisitors' => $todayUniqueVisitors,
        'landingShowVisitCounter' => $landingShowVisitCounter,
        'landingContactEmail' => is_string($landingContactEmail) ? $landingContactEmail : null,
        'landingXUrl' => is_string($landingXUrl) ? $landingXUrl : null,
    ]);
});

Route::redirect('/admin', '/app');
Route::redirect('/admin/dashboard', '/app/dashboard');
Route::redirect('/admin/login', '/app/login');
Route::redirect('/admin/register', '/app/register');

Route::redirect('/admin/history', '/app/history');
Route::redirect('/admin/khatmas', '/app/khatmas');
Route::redirect('/admin/khatmas/create', '/app/khatmas/create');

Route::get('/admin/khatmas/{record}/edit', function (string $record) {
    return redirect('/app/khatmas/'.$record.'/edit');
});

Route::redirect('/admin/password-reset/request', '/forgot-password');

Route::get('/admin/password-reset/reset', function (Request $request) {
    $token = (string) $request->query('token', '');
    $email = (string) $request->query('email', '');

    if ($token !== '' && $email !== '') {
        return redirect('/reset-password/'.$token.'?email='.urlencode($email));
    }

    return redirect('/forgot-password');
});

Route::redirect('/admin/email-verification/prompt', '/verify-email');
Route::get('/admin/email-verification/verify/{id}/{hash}', FilamentEmailVerificationController::class)
    ->middleware(['auth', 'signed', 'throttle:6,1']);

Route::middleware('guest')->group(function (): void {
    Route::get('/control/recovery', [ControlRecoveryController::class, 'show'])
        ->name('control.recovery.show');

    Route::post('/control/recovery', [ControlRecoveryController::class, 'store'])
        ->middleware('throttle:3,15')
        ->name('control.recovery.store');
});

Route::get('/dashboard', function () {
    return redirect()->route('filament.admin.pages.dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/app/history/export/csv', [HistoryExportController::class, 'csv'])->name('history.export.csv');
    Route::get('/app/history/export/print', [HistoryExportController::class, 'print'])->name('history.export.print');
});

Route::middleware(['guest', 'throttle:30,1'])->group(function (): void {
    Route::get('/auth/{provider}', [SocialAuthController::class, 'redirect'])
        ->whereIn('provider', ['google', 'twitter'])
        ->name('social.redirect');

    Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])
        ->whereIn('provider', ['google', 'twitter'])
        ->name('social.callback');
});

require __DIR__.'/auth.php';
