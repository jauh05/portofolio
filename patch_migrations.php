<?php
$files = glob('database/migrations/*_create_office_research_runs_table.php');
$file = $files[0];
$content = file_get_contents($file);
$content = str_replace(
    "\$table->id();",
    "\$table->uuid('id')->primary();
            \$table->foreignIdFor(\App\Models\ContentBrand::class, 'brand_id')->nullable();
            \$table->foreignUuid('command_id')->nullable();
            \$table->string('scope')->nullable();
            \$table->string('query')->nullable();
            \$table->string('status')->default('queued'); // queued, running, completed, failed
            \$table->json('sources')->nullable();
            \$table->json('findings')->nullable();
            \$table->json('trends')->nullable();
            \$table->json('content_ideas')->nullable();
            \$table->timestamp('started_at')->nullable();
            \$table->timestamp('completed_at')->nullable();
            \$table->json('metadata')->nullable();",
    $content
);
file_put_contents($file, $content);

$files = glob('database/migrations/*_create_office_content_plan_items_table.php');
$file = $files[0];
$content = file_get_contents($file);
$content = str_replace(
    "\$table->id();",
    "\$table->uuid('id')->primary();
            \$table->foreignUuid('research_run_id')->nullable();
            \$table->foreignIdFor(\App\Models\ContentBrand::class, 'brand_id')->nullable();
            \$table->foreignUuid('schedule_id')->nullable();
            \$table->foreignUuid('content_item_id')->nullable();
            \$table->string('platform');
            \$table->string('content_type');
            \$table->string('topic');
            \$table->text('brief')->nullable();
            \$table->text('reason')->nullable();
            \$table->timestamp('scheduled_at');
            \$table->string('generation_mode')->default('manual');
            \$table->string('publishing_mode')->default('review');
            \$table->string('assigned_agent')->nullable();
            \$table->string('status')->default('proposed'); // proposed, approved, scheduled, generating, ready, published, failed, dismissed",
    $content
);
file_put_contents($file, $content);
