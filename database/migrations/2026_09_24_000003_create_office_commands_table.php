<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('office_commands', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('agent_id')->index();
            $table->string('action');
            $table->json('payload')->nullable();
            $table->string('status')->default('queued')->index();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
            $table->index(['agent_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_commands');
    }
};
