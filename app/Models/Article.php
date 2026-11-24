<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Article extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'title_hi',
        'title_hinglish',
        'slug',
        'category',
        'category_hi',
        'category_hinglish',
        'excerpt',
        'excerpt_hi',
        'excerpt_hinglish',
        'content',
        'content_hi',
        'content_hinglish',
        'featured_image',
        'author_name',
        'tags',
        'status',
        'views_count',
        'reading_time',
        'is_featured',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'views_count' => 'integer',
            'reading_time' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Get safe cover image URL with category-based fallback.
     */
    public function getCoverImageUrlAttribute(): string
    {
        if (!empty($this->featured_image)) {
            $img = trim($this->featured_image);
            if (Str::startsWith($img, ['http://', 'https://', '//'])) {
                return $img;
            }
            if (Str::startsWith($img, '/')) {
                return $img;
            }
            if (Str::startsWith($img, 'uploads/')) {
                return '/' . $img;
            }
            if (Str::startsWith($img, 'storage/')) {
                return '/' . $img;
            }
            return asset('storage/' . $img);
        }

        // Beautiful curated cricket background fallbacks based on category
        $fallbacks = [
            'Match Report' => 'https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?auto=format&fit=crop&w=1200&q=80',
            'Tournament News' => 'https://images.unsplash.com/photo-1531415074868-036b1c57e3ce?auto=format&fit=crop&w=1200&q=80',
            'Cricket Tips' => 'https://images.unsplash.com/photo-1624526267942-ab0ff8a3e972?auto=format&fit=crop&w=1200&q=80',
            'Local Stories' => 'https://images.unsplash.com/photo-1512719994953-eabf50895df7?auto=format&fit=crop&w=1200&q=80',
            'Opinion & Analysis' => 'https://images.unsplash.com/photo-1587280501635-68a0e82cd5ff?auto=format&fit=crop&w=1200&q=80',
        ];

        return $fallbacks[$this->category] ?? 'https://images.unsplash.com/photo-1531415074868-036b1c57e3ce?auto=format&fit=crop&w=1200&q=80';
    }

    /**
     * Alias for cover_image_url.
     */
    public function getFeaturedImageUrlAttribute(): string
    {
        return $this->cover_image_url;
    }

    /**
     * Get dynamic reading time in minutes.
     */
    public function getEstimatedReadTimeAttribute(): int
    {
        if ($this->reading_time > 0) {
            return $this->reading_time;
        }
        $words = str_word_count(strip_tags($this->content ?? ''));
        return max(1, (int) ceil($words / 200));
    }

    /**
     * Formatted publish date.
     */
    public function getFormattedDateAttribute(): string
    {
        $dt = $this->published_at ?? $this->created_at;
        return $dt ? $dt->format('d M Y') : 'Recent';
    }

    /**
     * Tailwind color badge for category.
     */
    public function getCategoryColorAttribute(): string
    {
        return match ($this->category) {
            'Match Report' => 'bg-rose-500/15 text-rose-400 border-rose-500/30',
            'Tournament News' => 'bg-amber-500/15 text-amber-400 border-amber-500/30',
            'Cricket Tips' => 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30',
            'Local Stories' => 'bg-indigo-500/15 text-indigo-400 border-indigo-500/30',
            'Opinion & Analysis' => 'bg-purple-500/15 text-purple-400 border-purple-500/30',
            default => 'bg-blue-500/15 text-blue-400 border-blue-500/30',
        };
    }
}
