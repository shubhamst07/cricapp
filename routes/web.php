<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\LiveScoreController;
use App\Http\Controllers\GroundController;
use App\Http\Controllers\UmpireController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => response('pong', 200)->header('Content-Type', 'text/plain'));

Route::get('/', [LiveScoreController::class, 'index'])->name('home');
Route::get('/app-status', function () {
    $engineResp = null;
    $engineError = null;
    $scoreEngineLog = null;
    try {
        $r = \Illuminate\Support\Facades\Http::timeout(25)->get('http://127.0.0.1:6020/matches');
        $data = $r->json('data') ?? [];
        $targetMatch = collect($data)->first(fn($m) => ($m['id'] ?? '') === '161245' || str_contains(strtolower($m['name'] ?? ''), 'zealand'));
        $engineResp = [
            'status' => $r->status(),
            'total_matches' => count($data),
            'target_161245' => $targetMatch,
        ];
    } catch (\Throwable $e) {
        $engineError = $e->getMessage();
    }

    if (file_exists(storage_path('logs/score-engine.log'))) {
        $scoreEngineLog = substr(file_get_contents(storage_path('logs/score-engine.log')), -1000);
    }

    $laravelLog = null;
    if (file_exists(storage_path('logs/laravel.log'))) {
        $laravelLog = substr(file_get_contents(storage_path('logs/laravel.log')), -3000);
    }

    return response()->json([
        'time' => date('Y-m-d H:i:s'),
        'git_rev' => trim((string) @shell_exec('git rev-parse --short HEAD 2>&1')),
        'provider' => config('services.cricket.provider'),
        'local_url' => config('services.cricket.local_url'),
        'score_engine' => $engineResp,
        'score_engine_error' => $engineError,
        'laravel_log' => $laravelLog,
    ]);
});
Route::get('/live-scores', [LiveScoreController::class, 'index'])->name('live-scores.index');
Route::get('/api/points-table', [LiveScoreController::class, 'pointsTable'])->name('live-scores.points-table');
Route::get('/api/series-schedule', [LiveScoreController::class, 'seriesSchedule'])->name('live-scores.series-schedule');
Route::get('/live-scores/{matchId}', [LiveScoreController::class, 'show'])->name('live-scores.show');
Route::view('/offline', 'offline')->name('offline');

Route::get('/dashboard', [MatchController::class, 'dashboard'])->name('dashboard');

// Public Instagram-style Profiles
Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
Route::get('/profile/{id}', [ProfileController::class, 'show'])->name('profile.user');

// Public CricHeroes Matches Arena (Index)
Route::get('/matches', [MatchController::class, 'index'])->name('matches.index');

// Public Grounds & Umpires Hub
Route::get('/grounds', [GroundController::class, 'index'])->name('grounds.index');
Route::get('/umpires', [UmpireController::class, 'index'])->name('umpires.index');

// About & Contact Us Pages
Route::get('/about', [PageController::class, 'about'])->name('about');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::post('/contact', [PageController::class, 'submitContact'])->name('contact.submit');
Route::get('/privacy-policy', [PageController::class, 'privacy'])->name('privacy');
Route::get('/terms-of-service', [PageController::class, 'terms'])->name('terms');
Route::get('/inquiries', [PageController::class, 'inquiries'])->name('inquiries.index')->middleware('auth');
Route::get('/test-email', [PageController::class, 'testEmail'])->name('test.email');

Route::middleware('auth')->group(function () {
    Route::post('/profile/update', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->name('profile.avatar.update');
    Route::get('/profile-settings', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update.legacy');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/users/{user}/follow', [ProfileController::class, 'toggleFollow'])->name('users.follow.toggle');

    Route::get('/matches/create', [MatchController::class, 'create'])->name('matches.create');
    Route::post('/matches', [MatchController::class, 'store'])->name('matches.store');
    Route::get('/matches/{match}/edit', [MatchController::class, 'edit'])->name('matches.edit');
    Route::put('/matches/{match}', [MatchController::class, 'update'])->name('matches.update');
    Route::delete('/matches/{match}', [MatchController::class, 'destroy'])->name('matches.destroy');
    Route::post('/matches/{match}/quick-score', [MatchController::class, 'quickScore'])->name('matches.quick-score');
    Route::post('/matches/{match}/ball-by-ball', [MatchController::class, 'recordBall'])->name('matches.ball-by-ball');
    Route::post('/matches/{match}/players/{player}/stats', [MatchController::class, 'updatePlayerStats'])->name('matches.players.update-stats');
    Route::post('/matches/{match}/scores', [MatchController::class, 'storeScore'])->name('matches.scores.store');
    Route::put('/matches/{match}/scores/{score}', [MatchController::class, 'updateScore'])->name('matches.scores.update');
    Route::delete('/matches/{match}/scores/{score}', [MatchController::class, 'destroyScore'])->name('matches.scores.destroy');
    Route::post('/matches/{match}/rematch', [MatchController::class, 'rematch'])->name('matches.rematch');

    // Grounds Management & Booking
    Route::get('/grounds/create', [GroundController::class, 'create'])->name('grounds.create');
    Route::post('/grounds', [GroundController::class, 'store'])->name('grounds.store');
    Route::post('/grounds/{ground}/book', [GroundController::class, 'book'])->name('grounds.book');
    Route::patch('/ground-bookings/{booking}/status', [GroundController::class, 'updateBookingStatus'])->name('grounds.bookings.status');

    // Umpires Official Registration & Booking
    Route::get('/umpires/register', [UmpireController::class, 'register'])->name('umpires.register');
    Route::post('/umpires/profile', [UmpireController::class, 'storeProfile'])->name('umpires.profile.store');
    Route::post('/umpires/{umpire}/book', [UmpireController::class, 'book'])->name('umpires.book');
    Route::patch('/umpire-bookings/{booking}/status', [UmpireController::class, 'updateBookingStatus'])->name('umpires.bookings.status');
});

// Show individual ground & umpire (Must be after create/register)
Route::get('/grounds/{ground}', [GroundController::class, 'show'])->name('grounds.show');
Route::get('/umpires/{umpire}', [UmpireController::class, 'show'])->name('umpires.show');

// Show individual match (MUST be after /matches/create so 'create' is not treated as a wildcard ID)
Route::get('/matches/{match}', [MatchController::class, 'show'])->name('matches.show');
Route::get('/matches/{match}/broadcast', [MatchController::class, 'broadcast'])->name('matches.broadcast');
Route::get('/matches/{match}/broadcast/state', [MatchController::class, 'broadcastState'])->name('matches.broadcast.state');

require __DIR__.'/auth.php';
