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
        'images',
        'description',
        'status',
    ];

    protected $casts = [
        'amenities' => 'array',
        'images' => 'array',
        'price_per_slot' => 'decimal:2',
    ];

    /**
     * Get all images list with fallback to image_url or default cricket turf image
     */
    public function getImagesListAttribute(): array
    {
        $list = [];
        if (!empty($this->images) && is_array($this->images)) {
            $list = array_values(array_filter($this->images));
        } elseif (!empty($this->image_url)) {
            $list = [$this->image_url];
        }
        return !empty($list) ? $list : [
            'https://images.unsplash.com/photo-1531415074868-036b1c57e329?q=80&w=1200&auto=format&fit=crop'
        ];
    }

    /**
     * Primary preview image
     */
    public function getPrimaryImageAttribute(): string
    {
        return $this->images_list[0] ?? 'https://images.unsplash.com/photo-1531415074868-036b1c57e329?q=80&w=1200&auto=format&fit=crop';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(GroundBooking::class)->orderByDesc('booking_date');
    }
}
