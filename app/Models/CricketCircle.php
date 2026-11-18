<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CricketCircle extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'city',
        'description',
        'cover_image',
        'user_id',
        'whatsapp_link',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'circle_members')
                    ->withPivot('role')
                    ->withTimestamps();
    }

    public function meetups(): HasMany
    {
        return $this->hasMany(MatchMeetup::class);
    }

    public function isUserMember(?int $userId): bool
    {
        if (!$userId) return false;
        return $this->members()->where('user_id', $userId)->exists();
    }

    public function memberCount(): int
    {
        return $this->members()->count();
    }
}
