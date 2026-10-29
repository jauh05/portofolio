<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Views dan likes satu media Instagram, diambil dari Instagram Insights. */
class OfficeContentMetric extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['published_at' => 'datetime', 'fetched_at' => 'datetime'];
    }
}
