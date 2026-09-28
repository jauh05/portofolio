<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('office_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('agent_id')->index();
            $table->string('brand_id')->nullable()->index();
            $table->string('report_type')->index();
            $table->string('title');
            $table->text('summary');
            $table->json('body_json')->nullable();
            $table->uuid('source_command_id')->nullable()->index();
            $table->timestamps();
            $table->index(['agent_id', 'report_type', 'created_at']);
        });
    }

    public function down(): void { Schema::dropIfExists('office_reports'); }
};
