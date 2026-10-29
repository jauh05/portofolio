<?php

namespace App\Models;

use App\Casts\UtcDatetime;
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
            'scheduled_at' => UtcDatetime::class,
            'starts_at' => UtcDatetime::class,
            'ends_at' => UtcDatetime::class,
            'last_generated_at' => UtcDatetime::class,
            'next_run_at' => UtcDatetime::class,
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
