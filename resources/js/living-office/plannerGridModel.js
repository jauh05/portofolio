export const PLANNER_DAYS = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
export const PLANNER_HOURS = Array.from({ length: 18 }, (_, index) => index + 6);
export const TZ = 'Asia/Jakarta';

// Jenis chip. Kunci = brand + tipe konten. Warna dipakai lewat kelas tone-* di planner-grid.css.
export const CHIP_TYPES = {
    'jauki-reels': { label: 'Reels Jauki', tone: 'reels-jauki', platform: 'instagram', contentType: 'reels', brand: 'jauki' },
    'jauki-feed': { label: 'Feed Jauki', tone: 'feed-jauki', platform: 'instagram', contentType: 'feed', brand: 'jauki' },
    'jauki-story': { label: 'Story Jauki', tone: 'story-jauki', platform: 'instagram', contentType: 'story', brand: 'jauki' },
    'kauiz-feed': { label: 'Feed Kauiz', tone: 'feed-kauiz', platform: 'instagram', contentType: 'feed', brand: 'kauiz' },
    'kauiz-story': { label: 'Story Kauiz', tone: 'story-kauiz', platform: 'instagram', contentType: 'story', brand: 'kauiz' },
    'jauki-threads': { label: 'Threads Jauki', tone: 'threads', platform: 'threads', contentType: 'thread', brand: 'jauki' },
    'jauki-article': { label: 'Artikel Jauki', tone: 'article', platform: 'article', contentType: 'article', brand: 'jauki' },
};

export function chipKey(brandSlug, contentType) {
    if (contentType === 'reels') return 'jauki-reels';
    const key = `${brandSlug || 'jauki'}-${contentType || 'feed'}`;
    return CHIP_TYPES[key] ? key : 'jauki-feed';
}

export const chipLabel = (key) => CHIP_TYPES[key]?.label ?? key;
export const chipTone = (key) => CHIP_TYPES[key]?.tone ?? 'other';
export const hourOf = (time) => Number(String(time || '00:00').split(':')[0]);

// Jadwal mingguan dari database (satu chip per hari) dan slot Reels dari file.
export function buildChips(schedules, reelsSlots) {
    const chips = [];
    schedules.forEach((schedule) => {
        const rule = schedule.recurrenceRule || {};
        if (rule.frequency !== 'weekly' || !rule.time) return;
        (rule.days || []).forEach((day) => chips.push({
            id: `db:${schedule.id}:${day}`,
            source: 'db',
            refId: schedule.id,
            day,
            time: rule.time,
            key: chipKey(schedule.brand?.slug, schedule.contentType),
            label: schedule.name,
        }));
    });
    reelsSlots.forEach((slot) => chips.push({
        id: `reels:${slot.id}`,
        source: 'reels',
        refId: slot.id,
        day: Number(slot.day),
        time: slot.time,
        key: 'jauki-reels',
        label: slot.label || 'Reels Jauki',
    }));
    return chips;
}

const dateParts = (iso) => {
    if (!iso) return null;
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) return null;
    return {
        weekday: new Intl.DateTimeFormat('id-ID', { weekday: 'long', timeZone: TZ }).format(date),
        date: new Intl.DateTimeFormat('id-ID', { day: '2-digit', month: 'short', timeZone: TZ }).format(date),
        time: new Intl.DateTimeFormat('id-ID', { hour: '2-digit', minute: '2-digit', hour12: false, timeZone: TZ }).format(date).replace('.', ':'),
    };
};

export const resultDateParts = dateParts;

export function resultStatus(row) {
    if (row.publishedAt || row.status === 'published') return { label: 'Berhasil', tone: 'ok' };
    if (row.status === 'failed' || row.error) return { label: row.error ? `Gagal: ${row.error}` : 'Gagal', tone: 'bad' };
    if (row.status === 'generated' || row.status === 'ready' || row.status === 'ready_for_review' || row.status === 'approved') return { label: 'Siap', tone: 'ready' };
    return { label: String(row.status || 'menunggu').replace(/_/g, ' '), tone: 'wait' };
}
