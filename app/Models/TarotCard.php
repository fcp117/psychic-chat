<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TarotCard extends Model
{
    protected $guarded = ['id'];
    protected $casts = ['is_active' => 'boolean'];
    protected $appends = ['image_url'];

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
