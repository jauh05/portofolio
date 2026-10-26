<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('office_analyst_reports', function (Blueprint $table) {
            $table->timestamp('hidden_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::table('office_analyst_reports', function (Blueprint $table) {
            $table->dropColumn('hidden_at');
        });
    }
};
