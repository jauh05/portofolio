<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfficeContentSchedule extends Model
{
    use HasUuids, SoftDeletes;

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

    protected static function booted(): void
    {
        // Setiap perubahan jadwal langsung ditulis ke file slot Kauiz, supaya worker ikut grid.
        static::saved(fn () => \App\Services\KauizSlotSync::sync());
        static::deleted(fn () => \App\Services\KauizSlotSync::sync());
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(OfficeContentBrand::class, 'brand_id');
    }
}
