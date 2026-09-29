<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('office_content_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('content_item_id')->index();
            $table->integer('version');
            $table->string('source')->default('manual');
            $table->text('revision_instruction')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_content_revisions');
    }
};
