/* Pure helpers for the AI Content Planner chat. No React here, so they can be tested directly. */

const PLATFORM_LABELS = {
    'instagram:feed': 'Instagram Feed',
    'instagram:story': 'Instagram Story',
    'threads:thread': 'Threads',
    'threads:threads': 'Threads',
};

const TIMING_LABELS = {
    'H-1': 'H-1 · otomatis',
    'H-2': 'H-2 · otomatis',
    now: 'Sekarang',
    manual: 'Manual',
};

export const SUGGESTIONS = [
    'Buat 2 konten Instagram Feed besok jam 10',
    'Story promo sekarang',
    'Threads tips AI, generate H-2',
    'Aku ingin konten seminggu sekali',
];

export function platformLabel(item) {
    const key = `${item.platform}:${item.content_type}`;
    return PLATFORM_LABELS[key] || `${item.platform} ${item.content_type}`;
}

export function timingLabel(item) {
    return TIMING_LABELS[item.generation_timing_label] || 'Manual';
}

/* The backend sends Asia/Jakarta wall time without an offset ("Y-m-d H:i:s"). */
export function formatScheduledAt(value) {
    const date = new Date(`${String(value || '').replace(' ', 'T')}+07:00`);
    if (Number.isNaN(date.getTime())) return value || '—';
    return date.toLocaleString('id-ID', { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Jakarta' });
}

/* User turns still waiting for an answer, so Trent can combine them with a clarification reply.
   A proposal or a notice closes the earlier request, so a new request starts fresh. */
export function historyFrom(messages, limit = 6) {
    const pending = [];
    for (let index = messages.length - 1; index >= 0; index -= 1) {
        const message = messages[index];
        if (message.role === 'user') pending.unshift(message.text);
        else if (message.role === 'assistant' && !message.proposal) continue;
        else break;
    }
    return pending.slice(-limit);
}

export function selectAll(items) {
    return items.map(item => item.plan_id);
}

export function toggleSelection(selected, planId) {
    return selected.includes(planId) ? selected.filter(id => id !== planId) : [...selected, planId];
}

export function approvalNotice(count) {
    return `${count} jadwal dibuat. Timing Sekarang langsung masuk antrean generate. H-1 dan H-2 berjalan otomatis sesuai tanggalnya.`;
}

export function dismissNotice() {
    return 'Usulan dibatalkan. Tidak ada jadwal yang dibuat.';
}
