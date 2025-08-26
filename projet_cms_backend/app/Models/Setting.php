<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'label',
        'description',
        'options',
        'is_public',
        'sort_order'
    ];

    protected $casts = [
        'options' => 'array',
        'is_public' => 'boolean',
        'sort_order' => 'integer'
    ];

    // Scopes
    public function scopeByGroup($query, string $group)
    {
        return $query->where('group', $group);
    }

    public function scopePublic($query)
    {
        return $query->where('is_public', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('group')->orderBy('sort_order');
    }

    // Accessors
    public function getTypedValueAttribute()
    {
        return match($this->type) {
            'boolean' => (bool) $this->value,
            'integer' => (int) $this->value,
            'float' => (float) $this->value,
            'array', 'json' => json_decode($this->value, true),
            default => $this->value
        };
    }

    // Méthodes statiques
    public static function get(string $key, $default = null)
    {
        $setting = self::where('key', $key)->first();
        return $setting ? $setting->typed_value : $default;
    }

    public static function set(string $key, $value, string $type = 'string'): self
    {
        $encodedValue = match($type) {
            'array', 'json' => json_encode($value),
            'boolean' => $value ? '1' : '0',
            default => (string) $value
        };

        return self::updateOrCreate(
            ['key' => $key],
            [
                'value' => $encodedValue,
                'type' => $type
            ]
        );
    }

    public static function getByGroup(string $group): \Illuminate\Support\Collection
    {
        return self::byGroup($group)->ordered()->get()->mapWithKeys(function ($setting) {
            return [$setting->key => $setting->typed_value];
        });
    }

    public static function getPublicSettings(): array
    {
        return self::public()->get()->mapWithKeys(function ($setting) {
            return [$setting->key => $setting->typed_value];
        })->toArray();
    }
}
