<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Str;
use Carbon\Carbon;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'featured_image',
        'status',
        'published_at',
        'author_id',
        'category_id',
        'views_count',
        'likes_count',
        'comments_count',
        'is_featured',
        'allow_comments',
        'is_sticky',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'canonical_url',
        'content_blocks',
        'template'
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'views_count' => 'integer',
        'likes_count' => 'integer',
        'comments_count' => 'integer',
        'is_featured' => 'boolean',
        'allow_comments' => 'boolean',
        'is_sticky' => 'boolean',
        'content_blocks' => 'array'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($post) {
            if (empty($post->slug)) {
                $post->slug = Str::slug($post->title);
            }
            if (empty($post->excerpt)) {
                $post->excerpt = Str::limit(strip_tags($post->content), 160);
            }
        });

        static::updating(function ($post) {
            if ($post->isDirty('title') && empty($post->getOriginal('slug'))) {
                $post->slug = Str::slug($post->title);
            }
            if ($post->isDirty('content') && empty($post->excerpt)) {
                $post->excerpt = Str::limit(strip_tags($post->content), 160);
            }
        });

        // Mettre à jour le compteur de tags
        static::saved(function ($post) {
            if ($post->isDirty('status') && $post->status === 'published') {
                $post->tags->each->incrementUsage();
            }
        });
    }

    // Relations
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function tags()
    {
        return $this->belongsToMany(Tag::class, 'post_tags')->withTimestamps();
    }

    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function approvedComments(): HasMany
    {
        return $this->comments()->where('status', 'approved');
    }

    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'mediable', 'mediables');
    }

    public function featuredImageMedia(): MorphOne
    {
        return $this->morphOne(Media::class, 'mediable', 'mediables')
            ->where('collection', 'featured');
    }

    public function seoMeta(): MorphOne
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }

    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'loggable');
    }

    // Scopes
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
            ->where('published_at', '<=', now());
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled')
            ->where('published_at', '>', now());
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function scopeSticky($query)
    {
        return $query->where('is_sticky', true);
    }

    public function scopeByAuthor($query, $authorId)
    {
        return $query->where('author_id', $authorId);
    }

    public function scopeByCategory($query, $categoryId)
    {
        return $query->where('category_id', $categoryId);
    }

    public function scopeByTag($query, $tagId)
    {
        return $query->whereHas('tags', function ($q) use ($tagId) {
            $q->where('tags.id', $tagId);
        });
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('content', 'like', "%{$search}%")
              ->orWhere('excerpt', 'like', "%{$search}%");
        });
    }

    public function scopePopular($query, $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days))
            ->orderBy('views_count', 'desc');
    }

    public function scopeRecent($query, $limit = 10)
    {
        return $query->orderBy('published_at', 'desc')->limit($limit);
    }

    // Accessors
    public function getIsPublishedAttribute(): bool
    {
        return $this->status === 'published' &&
               $this->published_at &&
               $this->published_at->isPast();
    }

    public function getIsScheduledAttribute(): bool
    {
        return $this->status === 'scheduled' &&
               $this->published_at &&
               $this->published_at->isFuture();
    }

    public function getFeaturedImageUrlAttribute(): ?string
    {
        if ($this->featured_image) {
            return asset('storage/' . $this->featured_image);
        }

        if ($this->featuredImageMedia) {
            return $this->featuredImageMedia->url;
        }

        return null;
    }

    public function getReadingTimeAttribute(): int
    {
        $wordCount = str_word_count(strip_tags($this->content));
        return max(1, ceil($wordCount / 200)); // 200 mots par minute
    }

    public function getUrlAttribute(): string
    {
        return route('posts.show', $this->slug);
    }

    public function getExcerptAttribute($value): string
    {
        if ($value) {
            return $value;
        }

        return Str::limit(strip_tags($this->content), 160);
    }

    public function getStatusLabelAttribute(): string
    {
        return match($this->status) {
            'draft' => 'Brouillon',
            'published' => 'Publié',
            'scheduled' => 'Programmé',
            'archived' => 'Archivé',
            default => ucfirst($this->status)
        };
    }

    // Mutators
    public function setTitleAttribute($value)
    {
        $this->attributes['title'] = $value;
        if (empty($this->attributes['slug'])) {
            $this->attributes['slug'] = Str::slug($value);
        }
    }

    public function setPublishedAtAttribute($value)
    {
        if ($value && $this->status === 'draft') {
            $this->attributes['status'] = Carbon::parse($value)->isFuture() ? 'scheduled' : 'published';
        }
        $this->attributes['published_at'] = $value;
    }

    // Méthodes utilitaires
    public function publish(): self
    {
        $this->update([
            'status' => 'published',
            'published_at' => $this->published_at ?? now()
        ]);

        return $this;
    }

    public function unpublish(): self
    {
        $this->update(['status' => 'draft']);
        return $this;
    }

    public function incrementViews(): void
    {
        $this->increment('views_count');
    }

    public function syncTags(array $tagNames): void
    {
        $tagIds = collect($tagNames)->map(function ($name) {
            return Tag::findOrCreateByName(trim($name))->id;
        });

        $this->tags()->sync($tagIds);

        // Mettre à jour les compteurs d'usage
        Tag::whereIn('id', $tagIds)->each(function ($tag) {
            $tag->updateUsageCount();
        });
    }

    public function duplicate(): self
    {
        $newPost = $this->replicate([
            'slug',
            'views_count',
            'likes_count',
            'comments_count',
            'published_at'
        ]);

        $newPost->title = $this->title . ' (Copie)';
        $newPost->slug = Str::slug($newPost->title);
        $newPost->status = 'draft';
        $newPost->save();

        // Dupliquer les tags
        $newPost->tags()->attach($this->tags->pluck('id'));

        return $newPost;
    }

    public function getRelatedPosts(int $limit = 5)
    {
        return self::published()
            ->where('id', '!=', $this->id)
            ->where(function ($query) {
                $query->where('category_id', $this->category_id)
                      ->orWhereHas('tags', function ($q) {
                          $q->whereIn('tags.id', $this->tags->pluck('id'));
                      });
            })
            ->orderBy('published_at', 'desc')
            ->limit($limit)
            ->get();
    }
}
