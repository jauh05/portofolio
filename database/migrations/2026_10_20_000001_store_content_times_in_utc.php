<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Content times were written as Asia/Jakarta wall clock but read as UTC, which shifted them 7 hours.
 * Stored values now follow the UtcDatetime cast (UTC), so existing rows move back 7 hours.
 */
return new class extends Migration
{
    private array $columns = [
        'office_content_schedules' => ['scheduled_at', 'starts_at', 'ends_at', 'last_generated_at', 'next_run_at'],
        'office_content_plan_items' => ['scheduled_at'],
    ];

    public function up(): void
    {
        $this->shift(-7);
    }

    public function down(): void
    {
        $this->shift(7);
    }

    private function shift(int $hours): void
    {
        foreach ($this->columns as $table => $columns) {
            foreach ($columns as $column) {
                DB::table($table)->whereNotNull($column)->orderBy('id')->chunkById(500, function ($rows) use ($table, $column, $hours) {
                    foreach ($rows as $row) {
                        DB::table($table)->where('id', $row->id)->update([
                            $column => Carbon::parse($row->{$column}, 'UTC')->addHours($hours)->format('Y-m-d H:i:s'),
                        ]);
                    }
                }, 'id');
            }
        }
    }
};
