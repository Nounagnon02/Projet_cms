<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'color',
        'is_active',
        'usage_count'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'usage_count' => 'integer'
    ];

    // Boot method
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($tag) {
            if (empty($tag->slug)) {
                $tag->slug = Str::slug($tag->name);
            }
        });

        static::updating(function ($tag) {
            if ($tag->isDirty('name') && empty($tag->slug)) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    // Relations
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_tags')->withTimestamps();
    }

    public function publishedPosts(): BelongsToMany
    {
        return $this->posts()->where('status', 'published')->where('published_at', '<=', now());
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopePopular($query, int $limit = 10)
    {
        return $query->orderBy('usage_count', 'desc')->limit($limit);
    }

    public function scopeAlphabetical($query)
    {
        return $query->orderBy('name');
    }

    // Méthodes utilitaires
    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    public function decrementUsage(): void
    {
        if ($this->usage_count > 0) {
            $this->decrement('usage_count');
        }
    }

    public function updateUsageCount(): void
    {
        $this->update([
            'usage_count' => $this->posts()->count()
        ]);
    }

    // Méthodes statiques
    public static function findOrCreateByName(string $name): self
    {
        $slug = Str::slug($name);

        return self::firstOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'slug' => $slug]
        );
    }

    public static function getMostUsed(int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return self::active()
            ->popular($limit)
            ->get();
    }
}
