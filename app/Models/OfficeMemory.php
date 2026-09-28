<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OfficeMemory extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload_json' => 'array', 'tags_json' => 'array', 'expires_at' => 'datetime', 'confidence' => 'decimal:2'];
    }
}
