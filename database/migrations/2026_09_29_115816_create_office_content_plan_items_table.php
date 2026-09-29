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
        Schema::create('office_content_plan_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('research_run_id')->nullable();
            $table->foreignIdFor(\App\Models\OfficeContentBrand::class, 'brand_id')->nullable();
            $table->foreignUuid('schedule_id')->nullable();
            $table->foreignUuid('content_item_id')->nullable();
            $table->string('platform');
            $table->string('content_type');
            $table->string('topic');
            $table->text('brief')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('scheduled_at');
            $table->string('generation_mode')->default('manual');
            $table->string('publishing_mode')->default('review');
            $table->string('assigned_agent')->nullable();
            $table->string('status')->default('proposed'); // proposed, approved, scheduled, generating, ready, published, failed, dismissed
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('office_content_plan_items');
    }
};
