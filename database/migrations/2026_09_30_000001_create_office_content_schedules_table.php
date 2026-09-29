<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('office_content_schedules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('schedule_type')->index();
            $table->string('platform')->index();
            $table->string('content_type')->index();
            $table->string('topic')->nullable();
            $table->text('brief')->nullable();
            $table->string('assigned_agent')->nullable()->index();
            $table->string('timezone')->default('Asia/Jakarta');
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->json('recurrence_rule')->nullable();
            $table->string('recurrence_label')->nullable();
            $table->string('generation_mode')->default('manual');
            $table->string('publishing_mode')->default('review');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('last_generated_at')->nullable();
            $table->timestamp('next_run_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['is_active', 'schedule_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_content_schedules');
    }
};
