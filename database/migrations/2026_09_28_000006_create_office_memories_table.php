<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('office_memories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('brand_id')->nullable()->index();
            $table->string('source_agent')->index();
            $table->string('memory_type')->index();
            $table->string('title');
            $table->text('summary');
            $table->json('payload_json')->nullable();
            $table->text('source_url')->nullable();
            $table->string('source_reference')->nullable();
            $table->unsignedDecimal('confidence', 5, 2)->nullable();
            $table->unsignedTinyInteger('importance')->default(50);
            $table->json('tags_json')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
            $table->index(['brand_id', 'memory_type', 'created_at']);
        });
    }

    public function down(): void { Schema::dropIfExists('office_memories'); }
};
