const OUTPUTS = {
    feed: { key: 'feed', label: 'Instagram Feed', button: 'Generate Feed Sekarang', workerId: 'jauki-social', worker: 'Social Content Worker', action: 'generate_feed' },
    story: { key: 'story', label: 'Instagram Story', button: 'Generate Story Sekarang', workerId: 'jauki-social', worker: 'Social Content Worker', action: 'generate_story' },
    threads: { key: 'threads', label: 'Threads', button: 'Generate Threads Sekarang', workerId: 'jauki-threads', worker: 'Threads Worker', action: 'generate_threads' },
    article: { key: 'article', label: 'Web Article', button: 'Generate Article Sekarang', workerId: 'jauki-article', worker: 'Article Worker', action: 'generate_article' },
};

const value = (input) => String(input ?? '').trim().toLowerCase();

export function resolvePlannerOutput(schedule = {}) {
    const platform = value(schedule.platform);
    const type = value(schedule.contentType ?? schedule.content_type);
    const key = ['article', 'blog', 'website'].includes(platform) || ['article', 'blog'].includes(type) ? 'article'
        : platform === 'threads' && ['thread', 'threads', 'post'].includes(type) ? 'threads'
        : platform === 'instagram' && type === 'story' ? 'story'
        : platform === 'instagram' && ['feed', 'post'].includes(type) ? 'feed'
        : null;
    const output = key ? OUTPUTS[key] : { key: 'unknown', label: 'Output belum dikenali', button: 'Tentukan Output', workerId: null, worker: 'Belum ditentukan', action: null };
    const assignedAgent = schedule.assignedAgent ?? schedule.assigned_agent;
    return { ...output, assignedAgent, workerMismatch: Boolean(assignedAgent && output.workerId && assignedAgent !== output.workerId) };
}

export function articleReady(output, agents = []) {
    if (output.key !== 'article') return true;
    const worker = agents.find(agent => agent.id === output.workerId);
    return Boolean(worker && ['idle', 'working', 'generating', 'completed'].includes(worker.status) && worker.availableActions?.includes(output.action));
}

const timestamp = (item) => {
    for (const candidate of [item?.updatedAt, item?.updated_at, item?.metadata?.generation_requested_at, item?.generatedAt, item?.generated_at, item?.createdAt, item?.created_at]) {
        const parsed = Date.parse(candidate || '');
        if (Number.isFinite(parsed)) return parsed;
    }
    return 0;
};

export function selectScheduleContent(items = [], scheduleId) {
    return items.filter(item => (item.schedule_id ?? item.scheduleId) === scheduleId)
        .sort((a, b) => timestamp(b) - timestamp(a) || String(b.id).localeCompare(String(a.id)))[0] || null;
}

export function selectGenerationCommand(commands = [], content) {
    if (!content) return null;
    const requested = Date.parse(content.metadata?.generation_requested_at || '');
    return commands.filter(command => (command.contentId ?? command.content_id) === content.id && String(command.action || '').startsWith('generate_'))
        .filter(command => !Number.isFinite(requested) || !Number.isFinite(Date.parse(command.createdAt || '')) || Date.parse(command.createdAt) >= requested - 5000)
        .sort((a, b) => (Date.parse(b.createdAt || '') || 0) - (Date.parse(a.createdAt || '') || 0) || String(b.id).localeCompare(String(a.id)))[0] || null;
}

export function generationStatus(content, command, optimisticQueued = false) {
    if (!content) return optimisticQueued ? 'queued_for_generation' : 'draft';
    if (['preview_ready', 'ready_for_review', 'ready', 'approved', 'published'].includes(content.status)) return content.status;
    if (content.status === 'failed' || command?.status === 'failed') return 'failed';
    if (['running', 'claimed'].includes(command?.status) && ['draft', 'queued_for_generation', 'generating'].includes(content.status)) return 'generating';
    if (optimisticQueued && content.status === 'draft') return 'queued_for_generation';
    return content.status || (optimisticQueued ? 'queued_for_generation' : 'draft');
}

export function shouldPollGeneration(content, command, optimisticQueued = false) {
    return ['queued_for_generation', 'generating'].includes(generationStatus(content, command, optimisticQueued));
}

export const STATUS_LABELS = {
    draft: 'Belum dibuat', queued_for_generation: 'Antre', generating: 'Sedang dibuat',
    preview_ready: 'Preview siap', ready_for_review: 'Siap direview', ready: 'Siap direview',
    approved: 'Disetujui • Menunggu jadwal', published: 'Terbit', failed: 'Gagal', worker_unavailable: 'Worker belum tersedia',
};

export function safeGenerationError(reason) {
    const message = String(reason || '').replace(/\s+/g, ' ').trim();
    if (!message || message.length > 220 || /(?:traceback|stack trace|SQLSTATE|Exception|\btoken\b|secret|password|api[_-]?key|bearer\s|\/Users\/|\/var\/|\.php:\d+|\.py:\d+|https?:\/\/\S+@)/i.test(message)) return 'Worker belum berhasil membuat konten. Periksa status worker lalu coba lagi jika aman.';
    return message;
}

export function safeImageUrl(url) {
    if (typeof url !== 'string') return null;
    const trimmed = url.trim();
    if (/^https?:\/\/[^\s]+$/i.test(trimmed) || /^\/(?!\/)[^\s]*$/.test(trimmed)) return trimmed;
    return null;
}

export function contentPreview(content) {
    if (!content) return { imageUrl: null, text: '' };
    const payload = content.metadata?.generated_payload || {};
    let parsed = {};
    if (typeof content.text === 'string') {
        try { parsed = JSON.parse(content.text); } catch { parsed = {}; }
    }
    const text = typeof content.text === 'string' && !Object.keys(parsed).length ? content.text
        : parsed.text || parsed.caption || parsed.content || payload.text || payload.caption || '';
    const imageUrl = [content.imageUrl, content.image_url, payload.image_url, content.metadata?.visual_asset?.url, content.metadata?.image_url, parsed.image_url]
        .map(safeImageUrl).find(Boolean) || null;
    return { imageUrl, text: String(text || ''), title: content.title || payload.title || parsed.title || '' };
}

export async function runGenerationRequest({ scheduleId, post, refresh, onState, onNotice }) {
    onState({ phase: 'requesting' });
    onNotice('');
    try {
        const result = await post(`/office/api/content-schedules/${scheduleId}/generate`);
        if (result.status === 'generated') onState({ phase: 'queued' });
        else if (result.status === 'skipped' && ['content_exists', 'command_exists'].includes(result.reason)) onState({ phase: 'idle' });
        else throw new Error(result.reason === 'inactive_or_deleted' ? 'Jadwal tidak aktif.' : 'Generation tidak dapat dimulai.');
        try { await refresh([scheduleId]); }
        catch { onNotice('Permintaan sudah masuk, tetapi status belum dapat dimuat. Planner akan mencoba lagi.'); }
        return result;
    } catch (error) {
        onState({ phase: 'error', message: safeGenerationError(error.message) });
        return null;
    }
}
