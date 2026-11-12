<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MatchOver extends Model
{
    protected $table = 'match_overs';

    protected $fillable = [
        'match_id',
        'over_key',
        'over_number',
        'inning',
        'runs',
        'wickets',
        'bowler',
        'batting_team',
        'summary',
        'deliveries',
    ];

    protected $casts = [
        'deliveries' => 'array',
        'over_number' => 'integer',
        'inning' => 'integer',
        'runs' => 'integer',
        'wickets' => 'integer',
    ];
}
