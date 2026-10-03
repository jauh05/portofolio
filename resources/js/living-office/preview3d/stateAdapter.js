// API identity/status/task are domain data. Coordinates and pose never go back to API.
const ACTIVE = new Set(['working', 'generating', 'planning', 'analyzing', 'monitoring', 'reporting']);
export function adaptWorker(rows, { stale = false, error = false } = {}) {
    const raw = Array.isArray(rows) ? rows.find(a => a.id === 'trent') : null;
    if (!raw) return { available: false, stale, error, reason: error ? 'Koneksi belum tersedia' : 'Data belum tersedia' };
    const available = !['not_installed', 'not_connected', 'offline'].includes(raw.status);
    const task = typeof raw.currentTask === 'string' ? raw.currentTask : (raw.currentTask?.title || '');
    return { id: raw.id, name: 'Trent', room: 'open', available, status: raw.status, working: available && ACTIVE.has(raw.status), task: task.slice(0, 1000), progress: Number.isFinite(raw.progress) ? Math.max(0, Math.min(100, raw.progress)) : null, stale, error };
}
export const fixtureWorker = (task = 'Meninjau ringkasan konten — simulasi lokal') => [{ id: 'trent', status: 'working', currentTask: task, progress: 35 }];
