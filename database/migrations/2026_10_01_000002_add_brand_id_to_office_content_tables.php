<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('office_content_schedules', function (Blueprint $table) {
            $table->foreignUuid('brand_id')->nullable()->after('id')->constrained('office_content_brands')->nullOnDelete();
        });
        Schema::table('office_content_items', function (Blueprint $table) {
            $table->foreignUuid('brand_id')->nullable()->after('schedule_id')->constrained('office_content_brands')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('office_content_schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
        });
        Schema::table('office_content_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('brand_id');
        });
    }
};
