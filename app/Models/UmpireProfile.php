<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UmpireProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'city',
        'experience_years',
        'matches_umpired',
        'certification_level',
        'match_types',
        'fee_per_match',
        'phone',
        'whatsapp',
        'bio',
        'photo_url',
        'rating',
        'status',
    ];

    protected $casts = [
        'match_types' => 'array',
        'fee_per_match' => 'decimal:2',
        'rating' => 'decimal:1',
        'experience_years' => 'integer',
        'matches_umpired' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(UmpireBooking::class)->orderByDesc('match_date');
    }

    public function matches(): HasMany
    {
        return $this->hasMany(CricketMatch::class, 'umpire_id')->orderByDesc('match_date');
    }

    /**
     * Recalculate matches umpired and rating from completed matches and bookings.
     */
    public function recalculateStats(): void
    {
        $completedMatchesCount = $this->matches()->where('status', 'completed')->count();
        $completedBookingsCount = $this->bookings()->where('status', 'completed')->count();
        $totalMatches = max((int) $this->matches_umpired, $completedMatchesCount + $completedBookingsCount);

        // Rating calculation from matches with umpire_rating
        $matchRatings = $this->matches()->whereNotNull('umpire_rating')->pluck('umpire_rating');
        $newRating = $this->rating ?: 4.8;
        if ($matchRatings->isNotEmpty()) {
            $avg = round($matchRatings->avg(), 1);
            $newRating = $matchRatings->count() >= 3 ? $avg : round(($this->rating + $avg) / 2, 1);
        }

        $this->update([
            'matches_umpired' => $totalMatches,
            'rating' => $newRating,
        ]);
    }
}
