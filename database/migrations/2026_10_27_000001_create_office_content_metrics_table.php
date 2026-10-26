<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('office_content_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('platform', 50);
            $table->string('media_id', 100);
            $table->string('brand_slug', 50)->nullable()->index();
            $table->string('topic', 255)->nullable();
            $table->unsignedBigInteger('views')->default(0);
            $table->unsignedBigInteger('likes')->default(0);
            $table->dateTime('published_at')->nullable()->index();
            $table->dateTime('fetched_at');
            $table->timestamps();
            $table->unique(['platform', 'media_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_content_metrics');
    }
};
