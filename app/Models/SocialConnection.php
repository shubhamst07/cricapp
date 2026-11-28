<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class SocialConnection extends Model
{
    protected $fillable = [
        'user_id',
        'provider',
        'account_id',
        'account_name',
        'page_id',
        'page_name',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'meta',
        'is_active',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'meta' => 'array',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /**
     * Fetch the active connection for a provider safely even before migrations run.
     */
    public static function activeFor(string $provider): ?self
    {
        try {
            if (!Schema::hasTable('social_connections')) {
                return null;
            }
            return static::where('provider', $provider)
                ->where('is_active', true)
                ->latest('updated_at')
                ->first();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
