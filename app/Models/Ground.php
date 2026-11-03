<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ground extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'city',
        'address',
        'pitch_type',
        'price_per_slot',
        'slot_duration',
        'phone',
        'whatsapp',
        'amenities',
        'image_url',
        'description',
        'status',
    ];

    protected $casts = [
        'amenities' => 'array',
        'price_per_slot' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(GroundBooking::class)->orderByDesc('booking_date');
    }
}
