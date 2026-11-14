<?php

use App\Http\Controllers\Api\MatchesController;
use App\Http\Controllers\Api\MatchPostController;
use Illuminate\Support\Facades\Route;

Route::get('/matches/summary', [MatchesController::class, 'summary'])->name('api.matches.summary');
Route::get('/matches/current', [MatchesController::class, 'current'])->name('api.matches.current');
Route::match(['get', 'post'], '/cron/sync-live-matches', [MatchesController::class, 'syncLiveMatches'])->name('api.cron.sync-live-matches');
Route::get('/matches/{matchId}', [MatchesController::class, 'show'])->name('api.matches.show');
Route::get('/matches/{matchId}/scorecard', [MatchesController::class, 'scorecard'])->name('api.matches.scorecard');
Route::get('/matches/{matchId}/commentary', [MatchesController::class, 'commentary'])->name('api.matches.commentary');

// CricHeroes Squad & Player WhatsApp Lookup APIs
Route::get('/players/lookup', [MatchesController::class, 'lookupPlayer'])->name('api.players.lookup');
Route::get('/matches/{match}/players', [MatchesController::class, 'getMatchPlayers'])->name('api.matches.players.index');
Route::post('/matches/{match}/players', [MatchesController::class, 'addMatchPlayer'])->name('api.matches.players.store');
Route::delete('/match-players/{player}', [MatchesController::class, 'removeMatchPlayer'])->name('api.matches.players.destroy');

// Match Posts & Social Feed
Route::get('/matches/{matchId}/posts', [MatchPostController::class, 'index'])->name('api.match-posts.index');
Route::post('/matches/{matchId}/posts', [MatchPostController::class, 'store'])->name('api.match-posts.store');
Route::post('/posts/{id}/approve', [MatchPostController::class, 'approve'])->name('api.match-posts.approve');
Route::delete('/posts/{id}', [MatchPostController::class, 'destroy'])->name('api.match-posts.destroy');
Route::post('/posts/{id}/like', [MatchPostController::class, 'like'])->name('api.match-posts.like');
Route::get('/posts/{id}/comments', [MatchPostController::class, 'getComments'])->name('api.match-posts.comments');
Route::post('/posts/{id}/comments', [MatchPostController::class, 'addComment'])->name('api.match-posts.add-comment');

Route::get('/player/{playerId}', [MatchesController::class, 'player'])->name('api.player');
Route::get('/validate-key', [MatchesController::class, 'validateKey'])->name('api.validate-key');
