<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'category'
    ];

    // Relations
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permissions')->withTimestamps();
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_permissions')->withTimestamps();
    }

    // Scopes
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByName($query, string $name)
    {
        return $query->where('name', $name);
    }

    // Accessors
    public function getFormattedNameAttribute(): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $this->name));
    }

    // Méthodes statiques pour créer des permissions facilement
    public static function createPermission(string $name, string $displayName = null, string $description = null, string $category = 'general'): self
    {
        return self::create([
            'name' => $name,
            'display_name' => $displayName ?? ucwords(str_replace(['_', '-'], ' ', $name)),
            'description' => $description,
            'category' => $category
        ]);
    }

    public static function getByCategory(): array
    {
        return self::all()->groupBy('category')->toArray();
    }
}

