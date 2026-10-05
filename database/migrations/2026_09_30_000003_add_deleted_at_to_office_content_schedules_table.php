<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('office_content_schedules') || Schema::hasColumn('office_content_schedules', 'deleted_at')) {
            return;
        }

        Schema::table('office_content_schedules', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('office_content_schedules') || ! Schema::hasColumn('office_content_schedules', 'deleted_at')) {
            return;
        }

        Schema::table('office_content_schedules', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
