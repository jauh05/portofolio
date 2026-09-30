<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('office_content_schedules', function (Blueprint $table) {
            if (! Schema::hasColumn('office_content_schedules', 'generation_timing')) {
                $table->string('generation_timing')->default('manual')->after('generation_mode')->index();
            }
            if (! Schema::hasColumn('office_content_schedules', 'generation_lead_minutes')) {
                $table->integer('generation_lead_minutes')->nullable()->after('generation_timing');
            }
        });
    }

    public function down(): void
    {
        Schema::table('office_content_schedules', function (Blueprint $table) {
            if (Schema::hasColumn('office_content_schedules', 'generation_lead_minutes')) {
                $table->dropColumn('generation_lead_minutes');
            }
            if (Schema::hasColumn('office_content_schedules', 'generation_timing')) {
                $table->dropColumn('generation_timing');
            }
        });
    }
};
