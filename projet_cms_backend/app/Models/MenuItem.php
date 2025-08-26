<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MenuItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'menu_id',
        'title',
        'url',
        'route',
        'route_params',
        'linkable_type',
        'linkable_id',
        'parent_id',
        'sort_order',
        'target',
        'css_class',
        'icon',
        'is_active',
        'visibility_rules'
    ];

    protected $casts = [
        'route_params' => 'array',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'visibility_rules' => 'array'
    ];

    // Relations
    public function menu()
    {
        return $this->belongsTo(Menu::class);
    }

    public function parent()
    {
        return $this->belongsTo(MenuItem::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(MenuItem::class, 'parent_id')->orderBy('sort_order');
    }

    public function linkable()
    {
        return $this->morphTo();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeRootItems($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }

    // Accessors
    public function getResolvedUrlAttribute(): string
    {
        // Si URL directe
        if ($this->url) {
            return $this->url;
        }

        // Si route Laravel
        if ($this->route) {
            return route($this->route, $this->route_params ?? []);
        }

        // Si lié à un modèle
        if ($this->linkable) {
            return $this->linkable->url ?? '#';
        }

        return '#';
    }

    public function getIsExternalAttribute(): bool
    {
        return $this->url && (str_starts_with($this->url, 'http://') || str_starts_with($this->url, 'https://'));
    }

    public function getHasChildrenAttribute(): bool
    {
        return $this->children()->count() > 0;
    }

    // Méthodes utilitaires
    public function isVisible(User $user = null): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if (!$this->visibility_rules) {
            return true;
        }

        // Implémenter la logique de visibilité selon les règles
        foreach ($this->visibility_rules as $rule) {
            // Exemple de règles : role, permission, auth_status, etc.
            if (!$this->checkRule($rule, $user)) {
                return false;
            }
        }

        return true;
    }

    private function checkRule(array $rule, User $user = null): bool
    {
        switch ($rule['type'] ?? '') {
            case 'auth':
                return ($rule['value'] === 'logged_in') ? !is_null($user) : is_null($user);

            case 'role':
                return $user && $user->hasRole($rule['value']);

            case 'permission':
                return $user && $user->hasPermission($rule['value']);

            default:
                return true;
        }
    }
}
