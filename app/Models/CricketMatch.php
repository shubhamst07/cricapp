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

        // Direct video files should NOT be treated as embed stream links
        if (preg_match('/\.(mp4|webm|mov|mkv)$/i', parse_url($url, PHP_URL_PATH) ?? '') || str_contains($url, 'imagekit.io')) {
            return null;
        }

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

        return null;
    }

    /**
     * Accessor for stream_url: auto-append ?tr=orig for ImageKit videos to bypass transformation limits.
     */
    public function getStreamUrlAttribute($value): ?string
    {
        if (empty($value)) return $value;
        if (str_contains($value, 'imagekit.io') && preg_match('/\.(webm|mp4|mov|mkv)/i', $value)) {
            if (!str_contains($value, 'tr=orig')) {
                return str_contains($value, '?') ? "{$value}&tr=orig" : "{$value}?tr=orig";
            }
        }
        return $value;
    }

    /**
     * Check if the stream_url points to a recorded direct video file (MP4/WebM/storage).
     */
    public function getIsDirectVideoAttribute(): bool
    {
        if (empty($this->stream_url)) return false;
        $url = strtolower(trim($this->stream_url));
        return str_ends_with($url, '.mp4') || str_ends_with($url, '.webm') || str_ends_with($url, '.mov') || str_contains($url, 'imagekit.io') || str_contains($url, '/storage/recordings/') || str_contains($url, '/storage/');
    }

    /**
     * Get all recorded videos for this match.
     */
    public function getVideosAttribute(): array
    {
        $state = $this->live_state ?: [];
        $videos = $state['videos'] ?? [];

        $fixUrl = function(?string $url): ?string {
            if (!$url) return null;
            if (str_contains($url, 'imagekit.io') && preg_match('/\.(webm|mp4|mov|mkv)/i', $url)) {
                if (!str_contains($url, 'tr=orig')) {
                    return str_contains($url, '?') ? "{$url}&tr=orig" : "{$url}?tr=orig";
                }
            }
            return $url;
        };

        foreach ($videos as &$v) {
            if (!empty($v['url'])) {
                $v['url'] = $fixUrl($v['url']);
            }
        }
        unset($v);

        // Ensure legacy stream_url is included if present and is direct video
        $streamUrl = $this->stream_url;
        if (!empty($streamUrl) && $this->is_direct_video) {
            $streamUrl = $fixUrl($streamUrl);
            $exists = false;
            foreach ($videos as $v) {
                if (($v['url'] ?? '') === $streamUrl) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                array_unshift($videos, [
                    'id' => 'vid_legacy_' . $this->id,
                    'url' => $streamUrl,
                    'title' => 'Match Live Recording (Full)',
                    'score_snapshot' => $this->team1_name . ' vs ' . $this->team2_name,
                    'duration' => 'Replay',
                    'file_size' => 'Stored',
                    'created_at' => $this->updated_at?->format('d M Y, h:i A') ?? now()->format('d M Y, h:i A'),
                ]);
            }
        }

        return $videos;
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
        $cleanSearch = strtolower(trim($teamName));
        if (empty($cleanSearch)) {
            return null;
        }

        // 1. Direct exact match on batting_team
        $score = $this->scores->first(function ($s) use ($cleanSearch) {
            return strtolower(trim((string)$s->batting_team)) === $cleanSearch;
        });
        if ($score) {
            return $score;
        }

        // 2. Contains match or initials/code match (e.g. "Shimla Strikers" vs "SS" or "Shimla")
        $score = $this->scores->first(function ($s) use ($cleanSearch) {
            $bat = strtolower(trim((string)$s->batting_team));
            if (empty($bat)) return false;
            return str_contains($bat, $cleanSearch) || str_contains($cleanSearch, $bat);
        });
        if ($score) {
            return $score;
        }

        // 3. Fallback by inning_number:
        // Inning 1 corresponds to Team 1 (unless toss elected to bowl), Inning 2 to Team 2
        $isTeam1 = ($cleanSearch === strtolower(trim((string)$this->team1_name)));
        $isTeam2 = ($cleanSearch === strtolower(trim((string)$this->team2_name)));
        if ($isTeam1) {
            return $this->scores->firstWhere('inning_number', 1) ?? $this->scores->first();
        }
        if ($isTeam2) {
            return $this->scores->firstWhere('inning_number', 2);
        }

        return null;
    }

    /**
     * Get formatted score display like "164/4 (18.2 ov)" or "Yet to Bat".
     */
    public function formatTeamScore(string $teamName): string
    {
        $score = $this->getScoreForTeam($teamName);
        if (!$score || ($score->status === 'scheduled' && $score->runs == 0 && $score->wickets == 0)) {
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
        $activeInning = $this->live_state['inning'] ?? 1;
        $scores = [];

        foreach ($this->scores as $sc) {
            // If Inning 2 is scheduled or has 0 runs, 0 wkts, 0 overs while match is still in Inning 1,
            // don't export it as a started score so frontend does not show a premature 0-0!
            if ($sc->inning_number == 2 && ($sc->status === 'scheduled' || ($activeInning == 1 && $sc->runs == 0 && $sc->wickets == 0 && (empty($sc->overs) || $sc->overs === '0.0' || $sc->overs === '0')))) {
                continue;
            }

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

        // Extract live current batsmen and bowler if match has live state
        $currentBatsmen = [];
        $currentBowler = null;
        if ($this->live_state) {
            $strikerId = $this->live_state['striker_id'] ?? null;
            $nonStrikerId = $this->live_state['non_striker_id'] ?? null;
            $bowlerId = $this->live_state['bowler_id'] ?? null;
            if ($strikerId) {
                $st = $this->players->firstWhere('id', $strikerId);
                if ($st) {
                    $currentBatsmen[] = [
                        'name' => $st->player_name,
                        'runs' => (int) $st->runs_scored,
                        'balls' => (int) $st->balls_faced,
                        'fours' => (int) $st->fours,
                        'sixes' => (int) $st->sixes,
                        'is_striker' => true,
                    ];
                }
            }
            if ($nonStrikerId) {
                $nst = $this->players->firstWhere('id', $nonStrikerId);
                if ($nst) {
                    $currentBatsmen[] = [
                        'name' => $nst->player_name,
                        'runs' => (int) $nst->runs_scored,
                        'balls' => (int) $nst->balls_faced,
                        'fours' => (int) $nst->fours,
                        'sixes' => (int) $nst->sixes,
                        'is_striker' => false,
                    ];
                }
            }
            if ($bowlerId) {
                $bw = $this->players->firstWhere('id', $bowlerId);
                if ($bw) {
                    $currentBowler = [
                        'name' => $bw->player_name,
                        'overs' => (string) $bw->overs_bowled,
                        'maidens' => (int) $bw->maidens,
                        'runs' => (int) $bw->runs_conceded,
                        'wickets' => (int) $bw->wickets_taken,
                    ];
                }
            }
        }

        if ($rawStatus === 'completed' || $rawStatus === 'abandoned' || $isDismissed) {
            $status = 'completed';
            $statusText = $this->match_result ?: ($isDismissed ? 'Match Dismissed' : 'Match Finished');
        } elseif ($rawStatus === 'live') {
            $status = 'live';
            if (!empty($this->match_result)) {
                $statusText = $this->match_result;
            } elseif ($activeInning == 2) {
                $inn1Score = $this->scores->firstWhere('inning_number', 1);
                $inn2Score = $this->scores->firstWhere('inning_number', 2);
                if ($inn1Score) {
                    $target = (int)$inn1Score->runs + 1;
                    $currentRuns = (int)($inn2Score ? $inn2Score->runs : 0);
                    $runsNeeded = max(0, $target - $currentRuns);
                    $totalBalls = (int)($this->total_overs ?: 20) * 6;
                    $ovParts = explode('.', (string)($inn2Score ? $inn2Score->overs : '0.0'));
                    $ballsBowled = ((int)($ovParts[0] ?? 0) * 6) + ((int)($ovParts[1] ?? 0));
                    $ballsLeft = max(0, $totalBalls - $ballsBowled);
                    $statusText = "{$this->team2_name} need {$runsNeeded} runs in {$ballsLeft} balls";
                } else {
                    $statusText = "2nd Innings in progress";
                }
            } elseif (!empty($this->toss_decision)) {
                $statusText = $this->toss_decision;
            } else {
                $statusText = '1st Innings in progress';
            }
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

        $calcCode = function($name) {
            if (!$name) return 'TBA';
            $words = preg_split('/[\s-]+/', trim($name));
            if (count($words) >= 2) {
                return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
            }
            return strtoupper(substr($name, 0, 3));
        };

        $t1Code = $calcCode($this->team1_name);
        $t2Code = $calcCode($this->team2_name);

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
            'team1' => ['name' => $this->team1_name, 'short_name' => $t1Code, 'code' => $t1Code],
            'team2' => ['name' => $this->team2_name, 'short_name' => $t2Code, 'code' => $t2Code],
            'score' => $scores,
            'dateTimeGMT' => $this->match_date ? $this->match_date->format('Y-m-d') : '',
            'match_url' => '/matches/' . $this->id,
            'stream_url' => $this->stream_url,
            'players' => $allPlayerNames,
            'current_batsmen' => $currentBatsmen,
            'current_bowler' => $currentBowler,
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
