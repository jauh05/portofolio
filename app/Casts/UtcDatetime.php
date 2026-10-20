<?php

namespace App\Casts;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Stores datetimes in UTC so comparisons with now() and the scheduler use the real instant.
 * Naive strings are Asia/Jakarta wall time, which is how the planner, Trent, and the analyst write them.
 */
class UtcDatetime implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        return $value === null ? null : Carbon::parse($value, 'UTC');
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $when = $value instanceof DateTimeInterface
            ? Carbon::instance($value)
            : Carbon::parse($value, 'Asia/Jakarta');

        return $when->utc()->format('Y-m-d H:i:s');
    }
}
