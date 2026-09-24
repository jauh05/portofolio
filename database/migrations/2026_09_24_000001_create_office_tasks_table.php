<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('office_tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('agent_id')->index();
            $table->string('external_id')->nullable();
            $table->string('title');
            $table->string('type')->default('task');
            $table->string('status')->default('running')->index();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('target')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
            $table->index(['agent_id', 'status']);
            $table->index('started_at');
            $table->unique(['agent_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_tasks');
    }
};
