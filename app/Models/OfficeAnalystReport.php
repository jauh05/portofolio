<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class OfficeAnalystReport extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['report_date' => 'date', 'findings' => 'array', 'recommendations' => 'array', 'next_actions' => 'array', 'metadata' => 'array', 'generated_at' => 'datetime'];
    }
}
