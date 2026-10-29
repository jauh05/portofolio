<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficeContentPlanItem extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(OfficeContentBrand::class, 'brand_id');
    }
}
