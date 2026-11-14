<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CricketMatch extends Model
{
    use HasFactory;

    protected $table = 'matches';

    protected $fillable = [
        'user_id',
        'team1_id',
        'team2_id',
        'team1_name',
        'team2_name',
        'match_date',
        'venue',
        'ground_id',
        'umpire_id',
        'umpire_name',
        'umpire_rating',
        'status', // scheduled, live, completed, abandoned
        'external_match_id',
        'tournament_name',
        'match_type',
        'total_overs',
        'ball_type',
        'toss_decision',
        'match_result',
        'player_of_match',
        'stream_url',
        'live_state',
    ];

    protected $casts = [
        'match_date' => 'date',
        'total_overs' => 'integer',
        'live_state' => 'array',
    ];

    /**
     * Convert YouTube / Facebook / Twitch link to standard responsive embed URL.
     */
    public function getEmbedStreamUrlAttribute(): ?string
    {
        if (empty($this->stream_url)) return null;
        $url = trim($this->stream_url);

        // YouTube: youtu.be/ID, youtube.com/watch?v=ID, youtube.com/live/ID, etc.
        if (preg_match('/(?:youtube\.com\/(?:watch\?.*v=|live\/|embed\/|v\/)|youtu\.be\/)([a-zA-Z0-9_-]+)/i', $url, $matches)) {
            return "https://www.youtube-nocookie.com/embed/{$matches[1]}?autoplay=1&mute=1&playsinline=1&rel=0";
        }

        // Facebook Video:
        if (str_contains($url, 'facebook.com') || str_contains($url, 'fb.watch')) {
            return "https://www.facebook.com/plugins/video.php?href=" . urlencode($url) . "&show_text=false&autoplay=true";
        }

        // Twitch:
        if (preg_match('/twitch\.tv\/([a-zA-Z0-9_]+)/i', $url, $matches)) {
            $host = request()->getHost() ?: 'localhost';
            return "https://player.twitch.tv/?channel={$matches[1]}&parent={$host}";
        }

        return $url;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function umpire(): BelongsTo
    {
        return $this->belongsTo(UmpireProfile::class, 'umpire_id');
    }

    public function ground(): BelongsTo
    {
        return $this->belongsTo(Ground::class, 'ground_id');
    }

    public function team1(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team1_id');
    }

    public function team2(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'team2_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(Score::class, 'match_id')->orderBy('inning_number');
    }

    public function players(): HasMany
    {
        return $this->hasMany(MatchPlayer::class, 'match_id');
    }

    public function team1Players(): HasMany
    {
        return $this->hasMany(MatchPlayer::class, 'match_id')->where('team_number', 1);
    }

    public function team2Players(): HasMany
    {
        return $this->hasMany(MatchPlayer::class, 'match_id')->where('team_number', 2);
    }

    public function scopeLive($query)
    {
        return $query->where('status', 'live');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled');
    }

    /**
     * Get score for a specific team.
     */
    public function getScoreForTeam(string $teamName): ?Score
    {
        return $this->scores->first(function ($score) use ($teamName) {
            return strtolower(trim($score->batting_team)) === strtolower(trim($teamName));
        });
    }

    /**
     * Get formatted score display like "164/4 (18.2 ov)" or "Yet to Bat".
     */
    public function formatTeamScore(string $teamName): string
    {
        $score = $this->getScoreForTeam($teamName);
        if (!$score) {
            return 'Yet to bat';
        }

        $normOvers = \App\Services\CricketDataService::normalizeCricketOvers($score->overs);
        $oversStr = $normOvers ? "({$normOvers} ov)" : '';
        return "{$score->runs}/{$score->wickets} {$oversStr}";
    }

    /**
     * Automatically synchronize stats for linked Umpire, Ground, and local Players when match concludes.
     */
    public function syncOfficialsAndGroundStats(): void
    {
        try {
            if ($this->umpire_id && $this->umpire) {
                $this->umpire->recalculateStats();
            }

            if ($this->ground_id && $this->ground) {
                $this->ground->recalculateStats();
            }

            // Sync all registered players career stats
            $this->loadMissing('players.user');
            foreach ($this->players as $mp) {
                if ($mp->user) {
                    $mp->user->recalculateCricketStats();
                } elseif (!empty($mp->player_phone)) {
                    $clean = preg_replace('/[^0-9]/', '', $mp->player_phone);
                    if (strlen($clean) >= 6) {
                        $user = User::whereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') LIKE ?", ["%{$clean}%"])->first();
                        if ($user) {
                            $mp->update(['user_id' => $user->id]);
                            $user->recalculateCricketStats();
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Error syncing officials/ground stats: ' . $e->getMessage());
        }
    }

    /**
     * Format a CricketMatch instance for Live Scores Center (matching frontend expectations).
     */
    public function formatForLiveCenter(?int $currentUserId = null, ?string $currentUserPhone = null): array
    {
        $scores = [];
        foreach ($this->scores as $sc) {
            $normOvers = \App\Services\CricketDataService::normalizeCricketOvers($sc->overs);
            $scores[] = [
                'r' => (int) $sc->runs,
                'w' => (int) $sc->wickets,
                'o' => $normOvers ?: (string) $sc->overs,
                'inning' => $sc->batting_team,
                'batting_team' => $sc->batting_team,
                'runs' => (int) $sc->runs,
                'wickets' => (int) $sc->wickets,
                'overs' => $normOvers ?: (string) $sc->overs,
                'display_score' => "{$sc->runs}/{$sc->wickets}",
            ];
        }

        $allPlayerNames = $this->players->pluck('player_name')->filter()->map(fn($n) => trim($n))->values()->toArray();

        $isNetworkMatch = false;
        if ($currentUserId) {
            $isNetworkMatch = ($this->user_id === $currentUserId)
                || $this->players->contains(function ($p) use ($currentUserId, $currentUserPhone) {
                    return ($p->user_id && $p->user_id === $currentUserId)
                        || ($currentUserPhone && !empty($p->player_phone) && $p->player_phone === $currentUserPhone);
                });
        }

        $rawStatus = strtolower(trim((string)$this->status));
        $rawResult = strtolower(trim((string)$this->match_result));
        $isDismissed = $rawStatus === 'abandoned' || str_contains($rawResult, 'dismiss') || str_contains($rawStatus, 'dismiss');

        if ($rawStatus === 'completed' || $rawStatus === 'abandoned' || $isDismissed) {
            $status = 'completed';
            $statusText = $this->match_result ?: ($isDismissed ? 'Match Dismissed' : 'Match Finished');
        } elseif ($rawStatus === 'live') {
            $status = 'live';
            $statusText = $this->match_result ?: 'Live in progress';
        } else {
            $status = 'scheduled';
            $statusText = $this->match_result ?: 'Match Scheduled';
        }

        // Local live match auto-dismissal:
        // Non-Test matches live for > 1 day -> Match Dismissed (moved to Recent/Completed)
        // Test matches live for > 5 days -> Match Dismissed (moved to Recent/Completed)
        if ($status === 'live' && $this->match_date) {
            $isTest = str_contains(strtolower($this->match_type ?? ''), 'test')
                || str_contains(strtolower($this->tournament_name ?? ''), 'test');
            $maxDays = $isTest ? 5 : 1;

            $matchDateCarbon = \Carbon\Carbon::parse($this->match_date)->startOfDay();
            $today = \Carbon\Carbon::now()->startOfDay();
            $daysDiff = $matchDateCarbon->diffInDays($today, false);

            if ($daysDiff > $maxDays) {
                $status = 'completed';
                $statusText = 'Match Dismissed';
                $isDismissed = true;
                try {
                    $this->newQuery()->where('id', $this->id)->update([
                        'status' => 'abandoned',
                        'match_result' => 'Match Dismissed'
                    ]);
                } catch (\Throwable $e) {}
            }
        }

        return [
            'id' => 'local_' . $this->id,
            'local_id' => $this->id,
            'is_local' => true,
            'is_dismissed' => $isDismissed,
            'matchEnded' => ($status === 'completed'),
            'matchStarted' => in_array($status, ['live', 'completed']),
            'is_network_match' => $isNetworkMatch,
            'name' => "{$this->team1_name} vs {$this->team2_name}",
            'status' => $status,
            'status_text' => $statusText,
            'series' => $this->tournament_name ?: 'Corporate & Club Matches',
            'series_name' => $this->tournament_name ?: 'Corporate & Club Matches',
            'venue' => $this->venue ?: 'Ground Venue',
            'matchType' => $this->match_type ?: ($this->total_overs ? "{$this->total_overs} Overs" : 'Match'),
            'teams' => [$this->team1_name, $this->team2_name],
            'score' => $scores,
            'dateTimeGMT' => $this->match_date ? $this->match_date->format('Y-m-d') : '',
            'matchStarted' => in_array($status, ['live', 'completed']),
            'matchEnded' => $status === 'completed',
            'match_url' => route('matches.show', $this->id),
            'stream_url' => $this->stream_url,
            'players' => $allPlayerNames,
        ];
    }

    /**
     * Get all local matches formatted for the Live Scores Center, sorted with user's network matches first.
     */
    public static function getAllFormattedForLiveCenter(?int $currentUserId = null, ?string $currentUserPhone = null): array
    {
        $cacheKey = 'local_platform_matches_' . ($currentUserId ?: 'guest');
        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 60, function () use ($currentUserId, $currentUserPhone) {
            try {
                $matches = static::with(['scores', 'players', 'team1Players', 'team2Players', 'user'])
                    ->latest('id')
                    ->limit(50)
                    ->get();

                $formatted = $matches->map(function ($m) use ($currentUserId, $currentUserPhone) {
                    return $m->formatForLiveCenter($currentUserId, $currentUserPhone);
                })->toArray();

                // Sort so user associated / network matches appear on top
                if ($currentUserId) {
                    usort($formatted, function ($a, $b) {
                        if ($a['is_network_match'] === $b['is_network_match']) {
                            return 0;
                        }
                        return $a['is_network_match'] ? -1 : 1;
                    });
                }

                return $formatted;
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Database connection error in getAllFormattedForLiveCenter: ' . $e->getMessage());
                return [];
            }
        });
    }
}
