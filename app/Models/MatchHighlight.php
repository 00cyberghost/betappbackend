<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatchHighlight extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'youtube_url',
        'youtube_video_id',
        'thumbnail_url',
        'description',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }
}
