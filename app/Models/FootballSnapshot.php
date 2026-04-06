<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FootballSnapshot extends Model
{
    protected $fillable = [
        'key',
        'payload',
        'refreshed_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'refreshed_at' => 'datetime',
        ];
    }
}
