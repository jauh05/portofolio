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

    const moveChip = async (chip, day, hour) => {
        const time = `${String(hour).padStart(2, '0')}:00`;
        if (chip.source === 'reels') {
            await request(`/office/api/reels-slots/${chip.refId}`, { method: 'PATCH', body: JSON.stringify({ day, time }) });
        } else {
            const schedule = schedules.find((item) => item.id === chip.refId);
            const days = (schedule?.recurrenceRule?.days || []).map((item) => (item === chip.day ? day : item));
            await request(`/office/api/content-schedules/${chip.refId}`, { method: 'PATCH', body: JSON.stringify({ days: [...new Set(days)], time }) });
        }
        setNotice('Jadwal dipindah.');
        await load();
    };

    const addFromMenu = async (key) => {
        const type = CHIP_TYPES[key];
        try {
            if (type.contentType === 'reels') {
                await request('/office/api/reels-slots', { method: 'POST', body: JSON.stringify({ label: type.label, day: 1, time: '09:00' }) });
            } else {
                const brand = brands.find((item) => item.slug === type.brand);
                await request('/office/api/content-schedules', {
                    method: 'POST',
                    body: JSON.stringify({
                        name: type.label, schedule_type: 'recurring', platform: type.platform, content_type: type.contentType,
                        topic: '', brief: '', assigned_agent: '', timezone: TZ, brand_id: brand?.id ?? null,
                        frequency: 'weekly', days: [1], time: '09:00', starts_at: todayKey(),
                        generation_mode: 'manual', generation_timing: 'lead_time', generation_lead_minutes: 120,
                        publishing_mode: 'automatic', is_active: true,
                    }),
                });
            }
            setNotice(`${type.label} ditambahkan di Senin 09.00. Geser ke hari dan jam yang kamu mau.`);
            await load();
        } catch (error) {
            setNotice(error.message);
        }
    };

    const regenerate = async (row) => {
        try {
            await request(`/office/api/content-schedules/${row.scheduleId}/generate`, { method: 'POST', body: '{}' });
            setNotice('Generate ulang dimulai.');
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
                    <p>Geser chip ke hari dan jam mana pun. Jadwal langsung berubah dan tersimpan.</p>
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
            <ContentSlotGrid chips={chips} onMove={(chip, day, hour) => moveChip(chip, day, hour).catch((error) => setNotice(error.message))} />
            <ContentResultsTable results={results} density={density} onDensity={changeDensity} onRegenerate={regenerate} />
        </div>
    );
}
