<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Score extends Model
{
    use HasFactory;

    public $timestamps = true;

    protected $fillable = [
        'match_id',
        'inning_number',
        'batting_team',
        'runs',
        'wickets',
        'overs',
        'status',
    ];

    protected $casts = [
        'inning_number' => 'integer',
        'runs' => 'integer',
        'wickets' => 'integer',
        'overs' => 'string',
    ];

     public function match(): BelongsTo
     {
         return $this->belongsTo(CricketMatch::class);
     }

     public function getFormattedOversAttribute(): string
     {
         return \App\Services\CricketDataService::normalizeCricketOvers($this->overs) ?: ($this->overs ?: '0.0');
     }
}
