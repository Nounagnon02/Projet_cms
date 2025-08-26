<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;


class Comment extends Model
{
    use HasFactory;

    protected $fillable = [
        'content',
        'commentable_type',
        'commentable_id',
        'user_id',
        'guest_name',
        'guest_email',
        'guest_website',
        'ip_address',
        'user_agent',
        'parent_id',
        'status',
        'approved_at',
        'approved_by',
        'likes_count',
        'replies_count'
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'likes_count' => 'integer',
        'replies_count' => 'integer'
    ];

    // Relations
    public function commentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id')->orderBy('created_at', 'asc');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    // Scopes
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeSpam($query)
    {
        return $query->where('status', 'spam');
    }

    public function scopeRootComments($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeReplies($query)
    {
        return $query->whereNotNull('parent_id');
    }

    // Accessors
    public function getAuthorNameAttribute(): string
    {
        return $this->user ? $this->user->full_name : $this->guest_name;
    }

    public function getAuthorEmailAttribute(): ?string
    {
        return $this->user ? $this->user->email : $this->guest_email;
    }

    public function getIsGuestAttribute(): bool
    {
        return is_null($this->user_id);
    }

    public function getIsApprovedAttribute(): bool
    {
        return $this->status === 'approved';
    }

    public function getAvatarUrlAttribute(): string
    {
        if ($this->user && $this->user->avatar) {
            return $this->user->avatar_url;
        }

        $email = $this->author_email;
        return $email
            ? 'https://www.gravatar.com/avatar/' . md5(strtolower($email)) . '?s=50&d=mp'
            : 'https://ui-avatars.com/api/?name=' . urlencode($this->author_name) . '&size=50';
    }

    // Méthodes utilitaires
    public function approve(User $approver = null): self
    {
        $this->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $approver ? $approver->id : auth()->id()
        ]);

        // Incrémenter le compteur sur le commentable
        $this->commentable->increment('comments_count');

        return $this;
    }

    public function reject(): self
    {
        $this->update(['status' => 'rejected']);
        return $this;
    }

    public function markAsSpam(): self
    {
        $this->update(['status' => 'spam']);
        return $this;
    }

    public function getAllReplies(): \Illuminate\Support\Collection
    {
        $replies = collect();

        foreach ($this->replies as $reply) {
            $replies->push($reply);
            $replies = $replies->merge($reply->getAllReplies());
        }

        return $replies;
    }

    protected static function boot()
    {
        parent::boot();

        static::created(function ($comment) {
            // Incrémenter le compteur de réponses du parent
            if ($comment->parent_id) {
                Comment::find($comment->parent_id)->increment('replies_count');
            }
        });

        static::deleted(function ($comment) {
            // Décrémenter les compteurs
            if ($comment->parent_id) {
                Comment::find($comment->parent_id)->decrement('replies_count');
            }
            if ($comment->status === 'approved') {
                $comment->commentable->decrement('comments_count');
            }
        });
    }
}
