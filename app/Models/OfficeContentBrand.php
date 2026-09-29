<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OfficeContentBrand extends Model
{
    use HasUuids;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'metadata' => 'array'];
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(OfficeContentSchedule::class, 'brand_id');
    }

    public function contentItems(): HasMany
    {
        return $this->hasMany(OfficeContentItem::class, 'brand_id');
    }
}
