<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('office_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('deduplication_key')->nullable()->unique();
            $table->string('type')->index();
            $table->string('agent_id')->nullable()->index();
            $table->string('title');
            $table->text('message');
            $table->string('severity')->default('info')->index();
            $table->json('data')->nullable();
            $table->timestamp('read_at')->nullable()->index();
            $table->timestamp('created_at')->useCurrent()->index();
            $table->index(['read_at', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('office_notifications');
    }
};
