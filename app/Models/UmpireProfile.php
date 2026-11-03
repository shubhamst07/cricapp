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
}
