<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('office_content_brands', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('default_agent')->nullable();
            $table->string('primary_platform')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        $now = now();
        foreach ([
            ['name' => 'Kauiz', 'slug' => 'kauiz'],
            ['name' => 'Jauki', 'slug' => 'jauki'],
            ['name' => 'Personal Brand', 'slug' => 'personal-brand'],
        ] as $brand) {
            DB::table('office_content_brands')->insertOrIgnore(array_merge($brand, [
                'id' => (string) Str::uuid(), 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('office_content_brands');
    }
};
