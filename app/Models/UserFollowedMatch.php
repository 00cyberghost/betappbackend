<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFollowedMatch extends Model
{
    protected $fillable = [
        'user_id',
        'fixture_id',
        'topic',
        'home_team_name',
        'away_team_name',
        'home_score',
        'away_score',
        'status_short',
        'last_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'last_checked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
