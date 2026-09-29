<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OfficeContentSchedule extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'last_generated_at' => 'datetime',
            'next_run_at' => 'datetime',
            'recurrence_rule' => 'array',
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
