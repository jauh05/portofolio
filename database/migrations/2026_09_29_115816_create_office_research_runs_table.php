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
        Schema::create('office_research_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignIdFor(\App\Models\OfficeContentBrand::class, 'brand_id')->nullable();
            $table->foreignUuid('command_id')->nullable();
            $table->string('scope')->nullable();
            $table->string('query')->nullable();
            $table->string('status')->default('queued'); // queued, running, completed, failed
            $table->json('sources')->nullable();
            $table->json('findings')->nullable();
            $table->json('trends')->nullable();
            $table->json('content_ideas')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('office_research_runs');
    }
};
