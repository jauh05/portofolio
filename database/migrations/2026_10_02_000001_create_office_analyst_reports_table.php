<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('office_analyst_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->date('report_date')->index();
            $table->string('report_type')->index();
            $table->string('status')->default('draft')->index();
            $table->text('summary');
            $table->json('findings')->nullable();
            $table->text('conclusion')->nullable();
            $table->json('recommendations')->nullable();
            $table->json('next_actions')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('generated_at')->nullable()->index();
            $table->timestamps();
            $table->index(['report_date', 'report_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_analyst_reports');
    }
};
