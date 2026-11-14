<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchPlayer extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        'user_id',
        'team_number',
        'team_name',
        'player_name',
        'player_phone',
        'player_role',
        'is_captain',
        'is_wicketkeeper',
        'runs_scored',
        'balls_faced',
        'fours',
        'sixes',
        'dismissal',
        'overs_bowled',
        'maidens',
        'runs_conceded',
        'wickets_taken',
    ];

    protected $casts = [
        'is_captain' => 'boolean',
        'is_wicketkeeper' => 'boolean',
        'runs_scored' => 'integer',
        'balls_faced' => 'integer',
        'fours' => 'integer',
        'sixes' => 'integer',
        'maidens' => 'integer',
        'runs_conceded' => 'integer',
        'wickets_taken' => 'integer',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(CricketMatch::class, 'match_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Match batting strike rate.
     */
    public function getStrikeRateAttribute(): float
    {
        if ($this->balls_faced <= 0) return 0.0;
        return round(($this->runs_scored / $this->balls_faced) * 100, 1);
    }

    /**
     * Match bowling economy rate.
     */
    public function getEconomyRateAttribute(): float
    {
        if (empty($this->overs_bowled) || $this->overs_bowled === '0.0') return 0.0;
        $parts = explode('.', (string) $this->overs_bowled);
        $ov = (int) ($parts[0] ?? 0);
        $bl = (int) ($parts[1] ?? 0);
        $balls = ($ov * 6) + $bl;
        if ($balls <= 0) return 0.0;
        return round(($this->runs_conceded / $balls) * 6, 2);
    }

    public function getFormattedOversAttribute(): string
    {
        return \App\Services\CricketDataService::normalizeCricketOvers($this->overs_bowled, true) ?: ($this->overs_bowled ?: '0.0');
    }
}
