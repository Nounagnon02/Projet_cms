<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;



class Mediable extends Model
{
    protected $fillable = [
        'media_id',
        'mediable_type',
        'mediable_id',
        'collection',
        'order'
    ];

    protected $casts = [
        'order' => 'integer'
    ];

    // Relations
    public function media()
    {
        return $this->belongsTo(Media::class);
    }

    public function mediable()
    {
        return $this->morphTo();
    }

    // Scopes
    public function scopeInCollection($query, string $collection)
    {
        return $query->where('collection', $collection);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }


    protected static function boot()
    {
        parent::boot();

        static::creating(function ($page) {
            if (empty($page->slug)) {
                $page->slug = Str::slug($page->title);
            }
        });
    }

    // Relations
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function parent()
    {
        return $this->belongsTo(Page::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(Page::class, 'parent_id')->orderBy('sort_order');
    }



    public function comments()
    {
        return $this->morphMany(Comment::class, 'commentable');
    }

    public function seoMeta()
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }

    // Scopes
    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }

    public function scopeInMenu($query)
    {
        return $query->where('show_in_menu', true);
    }

    public function scopeRootPages($query)
    {
        return $query->whereNull('parent_id');
    }



    // Accessors
    public function getUrlAttribute(): string
    {
        return route('pages.show', $this->slug);
    }

    public function getMenuTitleAttribute($value): string
    {
        return $value ?: $this->title;
    }

    public function getBreadcrumbAttribute(): array
    {
        $breadcrumb = [];
        $current = $this;

        while ($current) {
            array_unshift($breadcrumb, [
                'title' => $current->title,
                'slug' => $current->slug,
                'url' => $current->url
            ]);
            $current = $current->parent;
        }

        return $breadcrumb;
    }

    // Méthodes utilitaires
    public function incrementViews(): void
    {
        $this->increment('views_count');
    }

    public function getAllChildren(): \Illuminate\Support\Collection
    {
        $children = collect();

        foreach ($this->children as $child) {
            $children->push($child);
            $children = $children->merge($child->getAllChildren());
        }

        return $children;
    }
}


