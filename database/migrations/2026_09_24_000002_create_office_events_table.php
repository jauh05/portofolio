<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('office_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('source_event_id')->nullable()->unique();
            $table->string('agent_id')->nullable()->index();
            $table->string('event_type')->index();
            $table->string('activity');
            $table->string('status')->nullable();
            $table->unsignedTinyInteger('progress')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['event_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_events');
    }
};
