<?php

namespace App\Services;

use App\Models\OfficeContentSchedule;
use Carbon\CarbonImmutable;

/**
 * Kemunculan jadwal berulang dalam sebuah rentang, dihitung dari aturan jadwal saat ini. Dipakai pembuatan konten supaya tidak
 * bergantung pada next_run_at, yang hanya dihitung saat jadwal disimpan dan tidak pernah dimajukan setelah kemunculan lewat.
 * Aturannya sama dengan grid dan kalender (harian, mingguan per hari, bulanan menurut tanggal mulai).
 */
class RecurrenceOccurrences
{
    /** @return list<CarbonImmutable> */
    public static function between(OfficeContentSchedule $schedule, CarbonImmutable $start, CarbonImmutable $end, int $limit = 100): array
    {
        $rule = $schedule->recurrence_rule ?? [];
        if (! $schedule->is_active || $schedule->schedule_type !== 'recurring' || empty($rule['time'])) {
            return [];
        }
        $timezone = $schedule->timezone ?: 'Asia/Jakarta';
        $startsAt = self::wall($schedule->starts_at, $timezone);
        $endsAt = self::wall($schedule->ends_at, $timezone);
        $from = $start->max($startsAt?->startOfDay() ?? $start);
        $until = $endsAt ? $end->min($endsAt->endOfDay()) : $end;

        $found = [];
        for ($day = $from->startOfDay(); $day->lessThanOrEqualTo($until) && count($found) < $limit; $day = $day->addDay()) {
            $matches = match ($rule['frequency'] ?? null) {
                'daily' => true,
                'weekly' => in_array($day->isoWeekday(), array_map('intval', $rule['days'] ?? []), true),
                'monthly' => $day->day === ($startsAt?->day ?? $day->day),
                default => false,
            };
            if (! $matches) {
                continue;
            }
            $when = CarbonImmutable::parse($day->toDateString().' '.$rule['time'], $timezone);
            if ($when->betweenIncluded($start, $end)) {
                $found[] = $when;
            }
        }

        return $found;
    }

    /** Jam dinding yang disimpan di zona jadwal dibaca ulang dalam zona itu (sama dengan planner). */
    private static function wall($value, string $timezone): ?CarbonImmutable
    {
        return $value ? CarbonImmutable::parse($value->format('Y-m-d H:i:s'), $timezone) : null;
    }
}
