<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TarotCard extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['is_active' => 'boolean', 'is_archived' => 'boolean'];
    protected $appends = ['image_url', 'is_ready'];

    public const CONTENT_FIELDS = ['category', 'keywords', 'meaning', 'guidance', 'reflection'];

    public function getIsReadyAttribute(): bool
    {
        foreach (array_merge(['name', 'image_path'], self::CONTENT_FIELDS) as $field) {
            if (trim((string) $this->$field) === '') return false;
        }
        return true;
    }

    public function scopeDrawable($query)
    {
        $query->where('is_active', true)->where('is_archived', false);
        foreach (array_merge(['name', 'image_path'], self::CONTENT_FIELDS) as $field) $query->whereRaw('TRIM('.$field.") <> ''");
        return $query;
    }

    public function getImageUrlAttribute(): string
    {
        // Relative URLs work on both the Forge hostname and custom domain.
        return str_starts_with($this->image_path, 'images/tarot/')
            ? '/'.$this->image_path : '/storage/'.$this->image_path;
    }

    public function reading(): array
    {
        return $this->only(['name', 'category', 'keywords', 'meaning', 'guidance', 'reflection', 'image_url']);
    }
}
