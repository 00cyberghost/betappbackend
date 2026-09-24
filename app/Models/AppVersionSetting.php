<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppVersionSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'platform',
        'latest_version',
        'latest_build',
        'minimum_version',
        'minimum_build',
        'update_url',
        'message',
        'is_required',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'latest_build' => 'integer',
            'minimum_build' => 'integer',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
