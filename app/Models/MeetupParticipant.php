<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetupParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_meetup_id',
        'user_id',
        'player_name',
        'player_role',
        'contact_phone',
        'status',
        'notes',
    ];

    public function meetup(): BelongsTo
    {
        return $this->belongsTo(MatchMeetup::class, 'match_meetup_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
