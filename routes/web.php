<?php

use App\Http\Controllers\GroundController;
use App\Http\Controllers\LiveScoreController;
use App\Http\Controllers\MatchController;
use App\Http\Controllers\MeetupController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UmpireController;
use App\Http\Controllers\ArticleController;
use App\Http\Controllers\ReelStudioController;
use App\Http\Controllers\SeoController;
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
        'score_engine_log' => $scoreEngineLog,
        'laravel_log' => $laravelLog,
    ]);
});
Route::get('/live-scores', [LiveScoreController::class, 'index'])->name('live-scores.index');
Route::get('/api/points-table', [LiveScoreController::class, 'pointsTable'])->name('live-scores.points-table');
Route::get('/api/series-schedule', [LiveScoreController::class, 'seriesSchedule'])->name('live-scores.series-schedule');
Route::get('/live-scores/{matchId}', [LiveScoreController::class, 'show'])->name('live-scores.show');
Route::view('/offline', 'offline')->name('offline');

// Cricket Feed & Fan Buzz (Renamed from /dashboard to /feed)
Route::get('/feed', [MatchController::class, 'dashboard'])->name('feed');
Route::get('/cricket-feed', fn() => redirect()->route('feed'));
Route::get('/dashboard', fn() => redirect()->route('feed'))->name('dashboard');

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
Route::get('/privacy', [PageController::class, 'privacy']);
Route::get('/terms-of-service', [PageController::class, 'terms'])->name('terms');
Route::get('/terms', [PageController::class, 'terms']);
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
    Route::post('/matches/{match}/rate-umpire', [MatchController::class, 'rateUmpire'])->name('matches.rate-umpire');

    // Grounds Management & Booking
    Route::get('/grounds/create', [GroundController::class, 'create'])->name('grounds.create');
    Route::post('/grounds', [GroundController::class, 'store'])->name('grounds.store');
    Route::get('/grounds/{ground}/edit', [GroundController::class, 'edit'])->name('grounds.edit');
    Route::put('/grounds/{ground}', [GroundController::class, 'update'])->name('grounds.update');
    Route::post('/grounds/{ground}/book', [GroundController::class, 'book'])->name('grounds.book');
    Route::patch('/ground-bookings/{booking}/status', [GroundController::class, 'updateBookingStatus'])->name('grounds.bookings.status');

    // Umpires Official Registration & Booking
    Route::get('/umpires/register', [UmpireController::class, 'register'])->name('umpires.register');
    Route::post('/umpires/profile', [UmpireController::class, 'storeProfile'])->name('umpires.profile.store');
    Route::post('/umpires/{umpire}/book', [UmpireController::class, 'book'])->name('umpires.book');
    Route::patch('/umpire-bookings/{booking}/status', [UmpireController::class, 'updateBookingStatus'])->name('umpires.bookings.status');

    // Cricket Match Meetups & Player Requests ("खिलाड़ी चाहिए")
    Route::get('/meetups/create', [MeetupController::class, 'create'])->name('meetups.create');
    Route::post('/meetups', [MeetupController::class, 'store'])->name('meetups.store');
    Route::get('/meetups/{meetup}/edit', [MeetupController::class, 'edit'])->name('meetups.edit');
    Route::put('/meetups/{meetup}', [MeetupController::class, 'update'])->name('meetups.update');
    Route::delete('/meetups/{meetup}', [MeetupController::class, 'destroy'])->name('meetups.destroy');
    Route::post('/meetups/{meetup}/join', [MeetupController::class, 'join'])->name('meetups.join');
    Route::post('/meetups/{meetup}/leave', [MeetupController::class, 'leave'])->name('meetups.leave');
    Route::delete('/meetups/{meetup}/participants/{participant}', [MeetupController::class, 'removeParticipant'])->name('meetups.participants.remove');

    // City Cricket Circles
    Route::post('/circles', [MeetupController::class, 'storeCircle'])->name('circles.store');
    Route::post('/circles/{circle}/join', [MeetupController::class, 'joinCircle'])->name('circles.join');
    Route::post('/circles/{circle}/leave', [MeetupController::class, 'leaveCircle'])->name('circles.leave');
});

// Cricket Meetups Index & Show (Publicly browseable)
Route::get('/meetups', [MeetupController::class, 'index'])->name('meetups.index');
Route::get('/meetups/{meetup}', [MeetupController::class, 'show'])->name('meetups.show');
Route::get('/meetups/{meetup}/participants/{participant}/career', [MeetupController::class, 'participantCareer'])->name('meetups.participants.career');
Route::get('/users/{user}/career', [MeetupController::class, 'userCareer'])->name('users.career');

// Show individual ground & umpire (Must be after create/register)
Route::get('/grounds/{ground}', [GroundController::class, 'show'])->name('grounds.show');
Route::get('/umpires/{umpire}', [UmpireController::class, 'show'])->name('umpires.show');

// Show individual match (MUST be after /matches/create so 'create' is not treated as a wildcard ID)
Route::get('/matches/{match}', [MatchController::class, 'show'])->name('matches.show');
Route::get('/matches/{match}/players/{player}/career', [MatchController::class, 'playerCareer'])->name('matches.players.career');
Route::get('/matches/{match}/broadcast', [MatchController::class, 'broadcast'])->name('matches.broadcast');
Route::get('/matches/{match}/broadcast/state', [MatchController::class, 'broadcastState'])->name('matches.broadcast.state');
Route::post('/matches/{match}/stream-signal', [MatchController::class, 'updateStreamSignal'])->name('matches.stream-signal');
Route::post('/matches/{match}/recording', [MatchController::class, 'uploadRecording'])->name('matches.upload-recording');
Route::delete('/matches/{match}/recordings/{videoId}', [MatchController::class, 'deleteRecording'])->name('matches.delete-recording');

// Cricket News, Articles & Blog (Public)
Route::get('/articles', [ArticleController::class, 'index'])->name('articles.index');
Route::get('/news', [ArticleController::class, 'index'])->name('news.index');
Route::get('/articles/{slug}', [ArticleController::class, 'show'])->name('articles.show');
Route::post('/articles/{article}/save-translations', [ArticleController::class, 'saveTranslations'])->name('articles.save-translations');

// Direct static fallback for legacy local article images (deterministically picks cricket photo if ephemeral file was cleared)
$legacyArticleImageResponse = function (string $filename) {
    $path = public_path('uploads/articles/' . $filename);
    if (!file_exists($path)) {
        $path = storage_path('app/public/uploads/articles/' . $filename);
    }
    if (file_exists($path)) {
        return response()->file($path, [
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    $fallbacks = [
        'https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1531415074868-036ba1b575da?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1587280501635-68a0e82cd5ff?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1512719994953-eabf50895df7?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1624526267942-ab0ff8a3e972?auto=format&fit=crop&w=1200&q=80',
    ];
    $idx = abs(crc32($filename)) % count($fallbacks);
    return redirect($fallbacks[$idx]);
};

Route::get('/uploads/articles/{filename}', $legacyArticleImageResponse)->where('filename', '[A-Za-z0-9_\-\.]+');
Route::get('/storage/uploads/articles/{filename}', $legacyArticleImageResponse)->where('filename', '[A-Za-z0-9_\-\.]+');

// Google SEO Sitemaps, RSS Feed, IndexNow Key & AdSense ads.txt
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('seo.sitemap');
Route::get('/news-sitemap.xml', [SeoController::class, 'newsSitemap'])->name('seo.news-sitemap');
Route::get('/rss.xml', [SeoController::class, 'rssFeed'])->name('seo.rss');
Route::get('/{key}.txt', [SeoController::class, 'indexNowKey'])
    ->where('key', '[A-Za-z0-9\-]{20,64}')
    ->name('seo.indexnow-key');

Route::get('/ads.txt', function () {
    $adsenseId = (string) config('services.adsense.client_id', '');
    $pubId = preg_replace('/^ca-/', '', trim($adsenseId));
    $content = "# CricApp.in AdSense Verification\n";
    if (!empty($pubId)) {
        $content .= "google.com, {$pubId}, DIRECT, f08c47fec0942fa0\n";
    } else {
        $content .= "# Configure ADSENSE_CLIENT_ID in Render Environment Variables\n";
    }
    return response($content, 200)->header('Content-Type', 'text/plain; charset=utf-8');
});

// Admin Article Management & Cricket Reel Studio
Route::middleware('auth')->group(function () {
    Route::get('/admin/articles/create', [ArticleController::class, 'create'])->name('articles.create');
    Route::post('/admin/articles', [ArticleController::class, 'store'])->name('articles.store');
    Route::post('/admin/articles/generate', [ArticleController::class, 'generateArticle'])->name('articles.generate');
    Route::get('/admin/articles/{article}/edit', [ArticleController::class, 'edit'])->name('articles.edit');
    Route::put('/admin/articles/{article}', [ArticleController::class, 'update'])->name('articles.update');
    Route::delete('/admin/articles/{article}', [ArticleController::class, 'destroy'])->name('articles.destroy');

    Route::get('/admin/reels', [ReelStudioController::class, 'index'])->name('reels.index');
    Route::get('/admin/articles/{article}/reel', [ReelStudioController::class, 'forArticle'])->name('articles.reel');
    Route::post('/admin/reels/refresh', [ReelStudioController::class, 'refreshScript'])->name('reels.refresh');
    Route::post('/admin/reels/tts', [ReelStudioController::class, 'generateTts'])->name('reels.tts');
    Route::post('/admin/reels/translate-script', [ReelStudioController::class, 'translateScript'])->name('reels.translate-script');
    Route::post('/admin/reels/convert-mp4', [ReelStudioController::class, 'convertMp4'])->name('reels.convert-mp4');
    Route::get('/admin/reels/proxy-image', [ReelStudioController::class, 'proxyImage'])->name('reels.proxy-image');
});

require __DIR__.'/auth.php';
