<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UmpireBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'umpire_profile_id',
        'user_id',
        'tournament_name',
        'match_date',
        'match_time',
        'venue',
        'teams',
        'ball_type',
        'total_fee',
        'contact_phone',
        'notes',
        'status',
    ];

    protected $casts = [
        'match_date' => 'date',
        'total_fee' => 'decimal:2',
    ];

    public function umpireProfile(): BelongsTo
    {
        return $this->belongsTo(UmpireProfile::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
