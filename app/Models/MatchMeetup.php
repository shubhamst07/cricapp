<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MatchMeetup extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ground_id',
        'cricket_circle_id',
        'title',
        'city',
        'location_name',
        'location_address',
        'match_date',
        'match_time',
        'ball_type',
        'match_format',
        'skill_level',
        'total_players',
        'slots_needed',
        'cost_per_player',
        'contact_phone',
        'contact_whatsapp',
        'whatsapp_group_link',
        'description',
        'status',
    ];

    protected $casts = [
        'match_date' => 'date',
        'total_players' => 'integer',
        'slots_needed' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function ground(): BelongsTo
    {
        return $this->belongsTo(Ground::class);
    }

    public function circle(): BelongsTo
    {
        return $this->belongsTo(CricketCircle::class, 'cricket_circle_id');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(MeetupParticipant::class);
    }

    public function activeParticipants(): HasMany
    {
        return $this->hasMany(MeetupParticipant::class)->where('status', 'confirmed');
    }

    /**
     * Number of open slots left for this match.
     */
    public function slotsLeft(): int
    {
        // Total slots available is slots_needed. As participants join (excluding organizer if in list), slots decrease.
        $joinedCount = $this->activeParticipants()->where('user_id', '!=', $this->user_id)->count();
        $left = $this->slots_needed - $joinedCount;
        return max(0, $left);
    }

    /**
     * Check if a specific user has joined this meetup.
     */
    public function isUserJoined(?int $userId): bool
    {
        if (!$userId) return false;
        return $this->participants()
            ->where('user_id', $userId)
            ->where('status', 'confirmed')
            ->exists();
    }

    /**
     * Check if meetup is full.
     */
    public function isFull(): bool
    {
        return $this->status === 'full' || $this->slotsLeft() <= 0;
    }

    /**
     * Formatted date string (e.g. "Sunday, 22 Nov").
     */
    public function getFormattedDateAttribute(): string
    {
        return $this->match_date ? $this->match_date->format('D, d M Y') : '';
    }

    /**
     * Formatted relative timing (e.g. "This Sunday", "Tomorrow", "Today").
     */
    public function getTimingBadgeAttribute(): string
    {
        if (!$this->match_date) return '';
        if ($this->match_date->isToday()) return '🔴 Today';
        if ($this->match_date->isTomorrow()) return '⚡ Tomorrow';
        if ($this->match_date->isCurrentWeek()) return '🏏 This ' . $this->match_date->format('l');
        return '📅 ' . $this->match_date->format('D, d M');
    }

    /**
     * Direct WhatsApp click-to-chat URL.
     */
    public function getWhatsAppUrlAttribute(): ?string
    {
        $phone = preg_replace('/[^0-9]/', '', (string)$this->contact_whatsapp);
        if (empty($phone)) {
            $phone = preg_replace('/[^0-9]/', '', (string)$this->contact_phone);
        }
        if (empty($phone)) {
            return null;
        }

        // Auto prefix 91 if 10-digit Indian mobile number
        if (strlen($phone) === 10) {
            $phone = '91' . $phone;
        }

        $text = rawurlencode("Hi! I saw your match meetup '{$this->title}' in {$this->city} on {$this->formatted_date}. I want to play!");
        return "https://wa.me/{$phone}?text={$text}";
    }

    /**
     * Scope for upcoming meetups.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('match_date', '>=', Carbon::today())
                     ->orderBy('match_date', 'asc');
    }

    /**
     * Scope for specific city.
     */
    public function scopeForCity($query, ?string $city)
    {
        if (empty($city) || strtolower($city) === 'all') {
            return $query;
        }
        return $query->where('city', 'LIKE', '%' . trim($city) . '%');
    }
}
