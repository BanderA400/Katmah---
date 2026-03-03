<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\HistoryExportController;
use App\Models\LandingVisit;
use App\Support\AppSettings;
use Filament\Auth\Http\Controllers\EmailVerificationController as FilamentEmailVerificationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

Route::get('/', function (Request $request) {
    $totalVisits = 0;
    $todayUniqueVisitors = 0;
    $landingContactEmail = AppSettings::get(
        AppSettings::KEY_LANDING_CONTACT_EMAIL,
        'contact@khatma.app',
    );
    $landingXUrl = AppSettings::get(
        AppSettings::KEY_LANDING_X_URL,
        'https://x.com/khatma_app',
    );
    $landingShowVisitCounter = (bool) AppSettings::get(
        AppSettings::KEY_LANDING_SHOW_VISIT_COUNTER,
        true,
    );

    if ($landingShowVisitCounter) {
        $visitorId = $request->cookie('khatma_vid');

        if (! is_string($visitorId) || $visitorId === '') {
            $visitorId = (string) Str::uuid();
            Cookie::queue(cookie('khatma_vid', $visitorId, 60 * 24 * 365));
        }

        try {
            $today = now()->toDateString();
            $fingerprint = hash('sha256', $visitorId);

            $alreadyVisitedToday = LandingVisit::query()
                ->where('visited_on', $today)
                ->where('fingerprint', $fingerprint)
                ->exists();

            LandingVisit::query()->create([
                'fingerprint' => $fingerprint,
                'visited_on' => $today,
                'is_unique' => ! $alreadyVisitedToday,
            ]);

            $totalVisits = LandingVisit::query()->count();
            $todayUniqueVisitors = LandingVisit::query()
                ->where('visited_on', $today)
                ->where('is_unique', true)
                ->count();
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

require __DIR__.'/auth.php';
