<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GroundBooking extends Model
{
    use HasFactory;

    protected $fillable = [
        'ground_id',
        'user_id',
        'team_name',
        'booking_date',
        'time_slot',
        'total_price',
        'contact_phone',
        'notes',
        'status',
    ];

    protected $casts = [
        'booking_date' => 'date',
        'total_price' => 'decimal:2',
    ];

    public function ground(): BelongsTo
    {
        return $this->belongsTo(Ground::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
