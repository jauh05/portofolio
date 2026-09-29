<?php
$file = 'app/Services/OfficeAnalystReportService.php';
$content = file_get_contents($file);

$oldPrompt = "'You are an internal Living Office analyst. Seluruh output yang dibaca pengguna wajib menggunakan Bahasa Indonesia. Gunakan bahasa profesional, natural, ringkas, dan mudah dipahami. Jangan menggunakan Bahasa Inggris kecuali nama brand, platform, model AI, nama field teknis, atau istilah yang memang tidak perlu diterjemahkan. Return JSON only with this schema: {\"summary\":\"\",\"findings\":[{\"type\":\"issue|attention|opportunity|status\",\"title\":\"\",\"description\":\"\",\"evidence\":\"\"}],\"conclusion\":\"\",\"recommendations\":[{\"priority\":\"high|medium|low\",\"action\":\"\",\"reason\":\"\"}],\"next_actions\":[{\"action_type\":\"task|content_schedule\",\"description\":\"\",\"reason\":\"\",\"brand_slug\":\"\",\"schedule\":{}}]}. Use only supplied internal Office context. Never invent metrics, performance, external facts, tool results, actions or outcomes. performance_available is false: include exactly \"Data performa belum tersedia.\" in summary. Actions are proposals only. A content_schedule must be complete, manual and review-only; otherwise propose a task.'";

$newPrompt = "'You are an internal Living Office analyst. Seluruh output yang dibaca pengguna wajib menggunakan Bahasa Indonesia. Gunakan bahasa profesional, natural, ringkas, dan mudah dipahami. Return JSON only with this schema: {\"summary\":\"\",\"findings\":[{\"type\":\"issue|attention|opportunity|status\",\"title\":\"\",\"description\":\"\",\"evidence\":\"\"}],\"conclusion\":\"\",\"recommendations\":[{\"priority\":\"high|medium|low\",\"action\":\"\",\"reason\":\"\"}],\"content_plan\":[{\"brand_slug\":\"\",\"platform\":\"\",\"content_type\":\"\",\"topic\":\"\",\"brief\":\"\",\"reason\":\"\",\"scheduled_at\":\"YYYY-MM-DD HH:mm:ss\",\"generation_mode\":\"automatic|manual\",\"publishing_mode\":\"automatic|review\"}]}. Gunakan data riset eksternal (Agent-Reach) dan internal Office. performance_available is false: masukkan kalimat \"Data performa belum tersedia.\" pada summary. Buat rencana konten 7 hari ke depan (content_plan) yang spesifik berdasarkan riset dan hindari tabrakan jadwal. Jadwal harus menggunakan timezone Asia/Jakarta.'";

$content = str_replace($oldPrompt, $newPrompt, $content);

$oldNormalize = "        if (\$findings === [] || \$recommendations === []) throw new \UnexpectedValueException('Incomplete analyst report.');
        return compact('summary', 'findings', 'conclusion', 'recommendations', 'nextActions') + ['next_actions' => \$nextActions];";

$newNormalize = "        if (\$findings === [] || \$recommendations === []) throw new \UnexpectedValueException('Incomplete analyst report.');
        \$contentPlan = collect(\$raw['content_plan'] ?? [])->filter(fn(\$item) => is_array(\$item))->values()->all();
        return compact('summary', 'findings', 'conclusion', 'recommendations', 'nextActions') + ['next_actions' => \$nextActions, 'content_plan' => \$contentPlan];";

$content = str_replace($oldNormalize, $newNormalize, $content);
file_put_contents($file, $content);
