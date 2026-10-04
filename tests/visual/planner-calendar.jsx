import React, { useState } from 'react';
import { createRoot } from 'react-dom/client';
import { PlannerCalendar } from '../../resources/js/living-office/PlannerCalendar';
import { calendarRange, readCalendarView, shiftCalendarDate, todayKey, writeCalendarView } from '../../resources/js/living-office/calendarViewModel';
import '../../resources/js/living-office/living-office.css';
import '../../resources/js/living-office/office-redesign.css';
import '../../resources/js/living-office/office-pages.css';
import '../../resources/js/living-office/office-refinement.css';
import '../../resources/js/living-office/planner-generation.css';
import '../../resources/js/living-office/planner-calendar.css';

// Synthetic visual QA. No Office API or production data is accessed.
const events = [
    ['a', '2026-10-05T09:00:00+07:00', 'Instagram Feed', 'instagram', 'feed'],
    ['b', '2026-10-05T14:00:00+07:00', 'Instagram Story', 'instagram', 'story'],
    ['c', '2026-10-05T18:00:00+07:00', 'Threads: belajar rutin', 'threads', 'thread'],
    ['d', '2026-10-05T20:00:00+07:00', 'Feed malam', 'instagram', 'feed'],
    ['e', '2026-10-07T10:00:00+07:00', 'Latihan singkat', 'instagram', 'feed'],
    ['f', '2026-10-11T15:00:00+07:00', 'Refleksi mingguan', 'threads', 'thread'],
    ['g', '2026-11-01T09:00:00+07:00', 'Awal bulan', 'instagram', 'feed'],
].map(([id, scheduledAt, name, platform, contentType]) => ({ id, scheduleId: id, scheduledAt, name, topic: name, platform, contentType, status: 'scheduled' }));

function Fixture() {
    const [view, setView] = useState(readCalendarView);
    const [date, setDate] = useState('2026-10-05');
    const [dark, setDark] = useState(false);
    const { start, end } = calendarRange(view, date);
    const visible = events.filter(item => item.scheduledAt.slice(0, 10) >= start && item.scheduledAt.slice(0, 10) <= end);
    return <div className="app-shell office-redesign" data-theme={dark ? 'dark' : 'light'} style={{ minHeight: '100dvh', padding: 16 }}><main style={{ maxWidth: 1440, margin: '0 auto' }}><div style={{ display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: 10, flexWrap: 'wrap', marginBottom: 12 }}><div><small>DEV FIXTURE · synthetic records · no API</small><h1 style={{ margin: '4px 0' }}>Kalender Content Planner</h1></div><button type="button" style={{ border: '1px solid var(--border)', borderRadius: 8, padding: '7px 10px', background: 'var(--surface)', color: 'var(--text)' }} onClick={() => setDark(value => !value)}>Tema {dark ? 'Terang' : 'Gelap'}</button></div><PlannerCalendar view={view} selectedDate={date} occurrences={visible} statusFor={() => 'draft'} onViewChange={next => setView(writeCalendarView(next))} onPrevious={() => setDate(current => shiftCalendarDate(view, current, -1))} onNext={() => setDate(current => shiftCalendarDate(view, current, 1))} onToday={() => setDate(todayKey())} onDateSelect={selected => { setDate(selected); setView(writeCalendarView('day')); }} onOpenOccurrence={() => {}} /></main></div>;
}

if (import.meta.env.DEV) {
    const root = import.meta.hot?.data.root || createRoot(document.getElementById('root'));
    if (import.meta.hot) import.meta.hot.data.root = root;
    root.render(<Fixture />);
}
