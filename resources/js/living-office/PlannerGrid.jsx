import React, { useCallback, useEffect, useState } from 'react';
import { CalendarDays, LayoutGrid, Plus } from 'lucide-react';
import { ContentSlotGrid } from './ContentSlotGrid';
import { ContentResultsTable } from './ContentResultsTable';
import { ContentPlannerMenu } from './ContentPlannerMenu';
import { ContentPlanner } from './ContentPlanner';
import { buildChips, CHIP_TYPES, TZ } from './plannerGridModel';
import { readPanelMode, writePanelMode } from './panelPreferences';
import './planner-grid.css';

const DENSITY_KEY = 'plannerResultsDensity';
const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

async function request(url, options = {}) {
    const response = await fetch(url, {
        ...options,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), ...(options.headers || {}) },
    });
    const json = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(json.message || `Permintaan gagal (${response.status}).`);
    return json;
}

const todayKey = () => new Intl.DateTimeFormat('en-CA', { timeZone: TZ }).format(new Date());

const generateNotice = (result) => {
    if (result.status === 'generated') return 'Generate ulang masuk antrean.';
    if (result.status === 'skipped') return `Dilewati: ${result.reason}.`;
    return `Gagal: ${result.reason || 'tidak diketahui'}.`;
};

export function PlannerGrid({ calendarProps }) {
    const [view, setView] = useState('grid');
    const [schedules, setSchedules] = useState([]);
    const [brands, setBrands] = useState([]);
    const [reelsSlots, setReelsSlots] = useState([]);
    const [results, setResults] = useState([]);
    const [density, setDensity] = useState(() => readPanelMode(DENSITY_KEY));
    const [menuOpen, setMenuOpen] = useState(false);
    const [notice, setNotice] = useState('');

    const load = useCallback(async () => {
        try {
            const [sched, slots, rows, brandList] = await Promise.all([
                request('/office/api/content-schedules'),
                request('/office/api/reels-slots'),
                request('/office/api/content-results'),
                request('/office/api/content-brands'),
            ]);
            setSchedules(sched.data ?? []);
            setReelsSlots(slots.slots ?? []);
            setResults(rows.results ?? []);
            setBrands(brandList.data ?? []);
        } catch (error) {
            setNotice(error.message);
        }
    }, []);

    useEffect(() => {
        load();
        const timer = window.setInterval(load, 30000);
        return () => window.clearInterval(timer);
    }, [load]);

    const chips = buildChips(schedules, reelsSlots);

    // Pindah hari tidak mengirim jam, jadi jam kartu tetap sama.
    const moveChip = async (chip, day) => {
        if (chip.source === 'reels') {
            await request(`/office/api/reels-slots/${chip.refId}`, { method: 'PATCH', body: JSON.stringify({ day }) });
        } else {
            await request(`/office/api/content-schedules/${chip.refId}/move`, { method: 'POST', body: JSON.stringify({ from_day: chip.day, day }) });
        }
        setNotice('Kartu dipindah. Jamnya tetap.');
        await load();
    };

    // Jam kosong dikirim sebagai null, dan kartu itu kembali ke bagian "Belum ada jam".
    const setChipTime = async (chip, time) => {
        if (chip.source === 'reels') {
            await request(`/office/api/reels-slots/${chip.refId}`, { method: 'PATCH', body: JSON.stringify({ time }) });
        } else {
            await request(`/office/api/content-schedules/${chip.refId}/move`, { method: 'POST', body: JSON.stringify({ from_day: chip.day, day: chip.day, time }) });
        }
        setNotice(time ? `Jam diubah ke ${time}.` : 'Jam dikosongkan.');
        await load();
    };

    const addFromMenu = async (key) => {
        const type = CHIP_TYPES[key];
        try {
            if (type.contentType === 'reels') {
                await request('/office/api/reels-slots', { method: 'POST', body: JSON.stringify({ label: type.label, day: 1 }) });
            } else {
                const brand = brands.find((item) => item.slug === type.brand);
                await request('/office/api/content-schedules', {
                    method: 'POST',
                    body: JSON.stringify({
                        name: type.label, schedule_type: 'recurring', platform: type.platform, content_type: type.contentType,
                        topic: '', brief: '', assigned_agent: '', timezone: TZ, brand_id: brand?.id ?? null,
                        frequency: 'weekly', days: [1], time: null, starts_at: todayKey(),
                        generation_mode: 'manual', generation_timing: 'lead_time', generation_lead_minutes: 120,
                        publishing_mode: 'automatic', is_active: true,
                    }),
                });
            }
            setNotice(`${type.label} ditambahkan ke Senin. Isi jamnya dengan ikon pensil.`);
            await load();
        } catch (error) {
            setNotice(error.message);
        }
    };

    // Generate ulang untuk satu kemunculan. Baris ini sudah punya tanggal, jadi bukan jadwal berikutnya.
    const regenerate = async (row) => {
        try {
            const result = await request(`/office/api/content-schedules/${row.scheduleId}/generate`, {
                method: 'POST',
                body: JSON.stringify({ occurrence_at: row.scheduledAt, regenerate: true }),
            });
            setNotice(generateNotice(result));
            await load();
        } catch (error) {
            setNotice(error.message);
        }
    };

    const changeDensity = (mode) => setDensity(writePanelMode(DENSITY_KEY, mode));

    if (view === 'calendar') {
        return (
            <div className="planner-grid-page">
                <div className="planner-grid-toolbar">
                    <button type="button" className="planner-toggle" onClick={() => setView('grid')}>
                        <LayoutGrid size={14} /> Kembali ke grid
                    </button>
                </div>
                <ContentPlanner {...calendarProps} />
            </div>
        );
    }

    return (
        <div className="planner-grid-page">
            <header className="planner-grid-toolbar">
                <div className="planner-grid-title">
                    <h2>Content Planner</h2>
                    <p>Seret kartu ke hari lain, jamnya tetap. Ubah jam dengan ikon pensil atau double-click kartu.</p>
                </div>
                <div className="planner-grid-actions">
                    <div className="planner-add">
                        <button type="button" className="planner-add-button" onClick={() => setMenuOpen((open) => !open)} aria-expanded={menuOpen} aria-label="Tambah jadwal">
                            <Plus size={16} />
                        </button>
                        {menuOpen && <ContentPlannerMenu onPick={addFromMenu} onClose={() => setMenuOpen(false)} />}
                    </div>
                    <button type="button" className="planner-toggle" onClick={() => setView('calendar')} aria-label="Buka kalender">
                        <CalendarDays size={14} /> Kalender
                    </button>
                </div>
            </header>
            {notice && <p className="planner-notice" role="status">{notice}</p>}
            <ContentSlotGrid
                chips={chips}
                onMove={(chip, day) => moveChip(chip, day).catch((error) => setNotice(error.message))}
                onSetTime={(chip, time) => setChipTime(chip, time).catch((error) => setNotice(error.message))}
            />
            <ContentResultsTable results={results} density={density} onDensity={changeDensity} onRegenerate={regenerate} />
        </div>
    );
}
