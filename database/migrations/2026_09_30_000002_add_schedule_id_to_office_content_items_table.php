<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('office_content_items', function (Blueprint $table) {
            $table->uuid('schedule_id')->nullable()->index()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('office_content_items', function (Blueprint $table) {
            $table->dropIndex(['schedule_id']);
            $table->dropColumn('schedule_id');
        });
    }
};
