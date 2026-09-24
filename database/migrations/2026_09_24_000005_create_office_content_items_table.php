<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('office_content_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('deduplication_key')->unique();
            $table->string('agent_id')->index();
            $table->string('platform')->index();
            $table->string('content_type')->index();
            $table->string('title')->nullable();
            $table->text('text')->nullable();
            $table->text('image_url')->nullable();
            $table->string('external_id')->nullable()->index();
            $table->text('public_url')->nullable();
            $table->string('status')->index();
            $table->json('metadata')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['platform', 'generated_at']);
            $table->unique(['platform', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_content_items');
    }
};
