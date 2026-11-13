<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MatchPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        'user_id',
        'user_name',
        'avatar_color',
        'content',
        'post_type',
        'media_url',
        'insta_url',
        'team_tag',
        'likes_count',
        'is_approved',
        'over_metric',
        'ball_metric',
    ];

    protected $casts = [
        'likes_count' => 'integer',
        'is_approved' => 'boolean',
        'ball_metric' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PostComment::class, 'match_post_id')->latest();
    }
}
