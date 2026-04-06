<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prediction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'fixture_id',
        'league_id',
        'league_name',
        'country_name',
        'home_team_id',
        'home_team_name',
        'home_team_logo',
        'away_team_id',
        'away_team_name',
        'away_team_logo',
        'match_starts_at',
        'prediction_type',
        'prediction_value',
        'probability',
        'odds',
        'analysis',
        'status',
        'scope',
        'source',
        'category',
        'likes_count',
        'comments_count',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'match_starts_at' => 'datetime',
            'published_at' => 'datetime',
            'probability' => 'integer',
            'odds' => 'decimal:2',
            'likes_count' => 'integer',
            'comments_count' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(PredictionComment::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(PredictionLike::class);
    }
}
