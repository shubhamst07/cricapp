<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'username',
        'phone',
        'bio',
        'avatar',
        'favorite_team',
        'player_role',
        'batting_style',
        'bowling_style',
        'matches_played',
        'total_runs',
        'highest_score',
        'batting_avg',
        'strike_rate',
        'fifties',
        'hundreds',
        'wickets_taken',
        'best_bowling',
        'economy_rate',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'batting_avg' => 'decimal:2',
            'strike_rate' => 'decimal:2',
            'economy_rate' => 'decimal:2',
        ];
    }

    /**
     * Posts created by this user.
     */
    public function posts(): HasMany
    {
        return $this->hasMany(MatchPost::class, 'user_id')->latest();
    }

    /**
     * Matches played / performances.
     */
    public function matchPerformances(): HasMany
    {
        return $this->hasMany(MatchPlayer::class, 'user_id')->latest();
    }

    /**
     * Users who follow this user.
     */
    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'following_id', 'follower_id')->withTimestamps();
    }

    /**
     * Users that this user follows.
     */
    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'following_id')->withTimestamps();
    }

    /**
     * Check if this user is following another user.
     */
    public function isFollowing(int|User $user): bool
    {
        $targetId = is_object($user) ? $user->id : (int) $user;
        return $this->following()->where('following_id', $targetId)->exists();
    }

    /**
     * Get avatar URL or sleek default avatar.
     */
    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar) {
            if (Str::startsWith($this->avatar, ['http://', 'https://'])) {
                return $this->avatar;
            }
            return asset($this->avatar);
        }

        $initials = urlencode($this->name ?: 'Cricket Fan');
        return "https://ui-avatars.com/api/?name={$initials}&background=0284c7&color=ffffff&bold=true&size=200";
    }

    /**
     * Get formatted username handle.
     */
    public function getHandleAttribute(): string
    {
        if ($this->username) {
            return '@' . ltrim($this->username, '@');
        }
        return '@' . Str::slug($this->name, '_');
    }

    /**
     * Automatically recalculate and sync CricHeroes career passport stats
     * from all match appearances across matches.
     */
    public function recalculateCricketStats(): void
    {
        $cleanPhone = preg_replace('/[^0-9]/', '', $this->phone ?? '');
        $query = MatchPlayer::where('user_id', $this->id);
        if (!empty($cleanPhone) && strlen($cleanPhone) >= 4) {
            $query->orWhere(function ($sub) use ($cleanPhone) {
                $sub->where('player_phone', 'LIKE', "%{$cleanPhone}%")
                    ->orWhereRaw("REPLACE(REPLACE(REPLACE(player_phone, ' ', ''), '-', ''), '+', '') LIKE ?", ["%{$cleanPhone}%"]);
            });
        }

        $performances = $query->get();
        if ($performances->isEmpty()) {
            return;
        }

        $matchesCount = $performances->pluck('match_id')->unique()->count();
        $totalRuns = (int) $performances->sum('runs_scored');
        $totalBalls = (int) $performances->sum('balls_faced');
        $fifties = $performances->filter(fn($p) => $p->runs_scored >= 50 && $p->runs_scored < 100)->count();
        $hundreds = $performances->filter(fn($p) => $p->runs_scored >= 100)->count();
        $highestScore = (int) ($performances->max('runs_scored') ?? 0);

        // Highest score with * if not out
        $highestPerf = $performances->where('runs_scored', $highestScore)->first();
        $highestStr = (string) $highestScore;
        if ($highestPerf && $highestPerf->dismissal === 'not out' && $highestScore > 0) {
            $highestStr .= '*';
        }

        $timesOut = $performances->filter(fn($p) => $p->balls_faced > 0 && $p->dismissal !== 'not out' && $p->dismissal !== 'dnb')->count();
        $battingAvg = $timesOut > 0 ? round($totalRuns / $timesOut, 2) : $totalRuns;
        $strikeRate = $totalBalls > 0 ? round(($totalRuns / $totalBalls) * 100, 2) : 0;

        $totalWickets = (int) $performances->sum('wickets_taken');
        $totalRunsConceded = (int) $performances->sum('runs_conceded');

        // Total legal balls bowled
        $totalBallsBowled = 0;
        foreach ($performances as $perf) {
            if ($perf->overs_bowled) {
                $parts = explode('.', (string) $perf->overs_bowled);
                $ov = (int) ($parts[0] ?? 0);
                $bl = (int) ($parts[1] ?? 0);
                $totalBallsBowled += ($ov * 6) + $bl;
            }
        }
        $economyRate = $totalBallsBowled > 0 ? round(($totalRunsConceded / $totalBallsBowled) * 6, 2) : 0;

        // Best bowling figures
        $bestBowlingPerf = $performances->sortByDesc('wickets_taken')->sortBy('runs_conceded')->first();
        $bestBowling = ($bestBowlingPerf && $bestBowlingPerf->wickets_taken > 0)
            ? "{$bestBowlingPerf->wickets_taken}/{$bestBowlingPerf->runs_conceded}"
            : ($this->best_bowling ?: '0/0');

        $this->update([
            'matches_played' => max($matchesCount, (int) $this->matches_played),
            'total_runs' => max($totalRuns, (int) $this->total_runs),
            'highest_score' => $highestScore > 0 ? $highestStr : ($this->highest_score ?: '0'),
            'fifties' => max($fifties, (int) $this->fifties),
            'hundreds' => max($hundreds, (int) $this->hundreds),
            'batting_avg' => $battingAvg > 0 ? $battingAvg : $this->batting_avg,
            'strike_rate' => $strikeRate > 0 ? $strikeRate : $this->strike_rate,
            'wickets_taken' => max($totalWickets, (int) $this->wickets_taken),
            'best_bowling' => $bestBowling,
            'economy_rate' => $economyRate > 0 ? $economyRate : $this->economy_rate,
        ]);
    }

    public function grounds(): HasMany
    {
        return $this->hasMany(Ground::class);
    }

    public function groundBookings(): HasMany
    {
        return $this->hasMany(GroundBooking::class);
    }

    public function umpireProfile(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(UmpireProfile::class);
    }

    public function umpireBookings(): HasMany
    {
        return $this->hasMany(UmpireBooking::class);
    }
}
