import React from 'react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { CALENDAR_VIEWS, calendarRange, calendarTime, calendarTitle, datesInRange, groupCalendarOccurrences, todayKey } from './calendarViewModel';
import { resolvePlannerOutput } from './plannerGeneration';

const VIEW_LABELS = { day: 'Hari', week: 'Minggu', month: 'Bulan' };
const WEEKDAYS = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
const statusLabel = status => ({ draft: 'Draf', scheduled: 'Direncanakan', queued_for_generation: 'Menunggu Generasi', generating: 'Sedang Dibuat', preview_ready: 'Siap Ditinjau', ready_for_review: 'Siap Ditinjau', ready: 'Siap Ditinjau', approved: 'Disetujui', published: 'Terbit', failed: 'Gagal' })[status] || 'Direncanakan';
const dateLabel = key => new Intl.DateTimeFormat('id-ID', { timeZone: 'UTC', weekday: 'long', day: 'numeric', month: 'long' }).format(new Date(`${key}T12:00:00Z`));
const dateNumber = key => Number(key.slice(8, 10));
const weekdayIndex = key => (new Date(`${key}T12:00:00Z`).getUTCDay() + 6) % 7;

function EventButton({ item, compact = false, statusFor, onOpen }) {
    const output = resolvePlannerOutput(item).label.replace('Instagram ', '');
    const time = calendarTime(item.scheduledAt);
    const status = statusLabel(statusFor(item));
    return <button type="button" className={`pc-event ${compact ? 'compact' : ''}`} onClick={() => onOpen(item)} title={`${time} · ${output} · ${item.name} · ${status}`}>
        <time>{time}</time><span><b>{output}</b>{!compact && <><strong>{item.topic || item.name}</strong><small>{status}</small></>}</span>
    </button>;
}

export function PlannerCalendar({ view, selectedDate, occurrences = [], loading = false, onViewChange, onPrevious, onNext, onToday, onDateSelect, onOpenOccurrence, statusFor }) {
    const range = calendarRange(view, selectedDate);
    const today = todayKey();
    const byDay = groupCalendarOccurrences(occurrences);
    const selectedEntries = byDay[selectedDate] || [];
    const weekDates = datesInRange(range.start, range.end);
    const monthDates = view === 'month' ? datesInRange(range.start, range.end) : [];
    return <section className={`planner-calendar pc-calendar pc-${view}`} aria-label="Kalender Content Planner">
        <header className="pc-toolbar">
            <div className="pc-navigation"><button type="button" onClick={onPrevious} aria-label={`Sebelumnya, ${VIEW_LABELS[view]}`}><ChevronLeft size={18} /></button><h2 aria-live="polite">{calendarTitle(view, selectedDate)}</h2><button type="button" onClick={onNext} aria-label={`Berikutnya, ${VIEW_LABELS[view]}`}><ChevronRight size={18} /></button><button type="button" className="pc-today-button" onClick={onToday}>Hari Ini</button></div>
            <div className="pc-view-switch" role="group" aria-label="Tampilan kalender">{CALENDAR_VIEWS.map(mode => <button type="button" key={mode} aria-pressed={view === mode} onClick={() => onViewChange(mode)}>{VIEW_LABELS[mode]}</button>)}</div>
        </header>
        {loading && <p className="pc-loading" role="status">Memuat jadwal…</p>}
        {view === 'day' && <div className="pc-day-body">{selectedEntries.length ? selectedEntries.map(item => <EventButton key={item.id} item={item} statusFor={statusFor} onOpen={onOpenOccurrence} />) : <p className="pc-empty">{selectedDate === today ? 'Belum ada jadwal pada hari ini.' : 'Belum ada jadwal pada tanggal ini.'}</p>}</div>}
        {view === 'week' && <div className="pc-week-grid">{weekDates.map((key, index) => <section key={key} className={`pc-week-day ${key === today ? 'is-today' : ''} ${key === selectedDate ? 'is-selected' : ''}`}><button type="button" className="pc-date-button" aria-label={dateLabel(key)} aria-current={key === today ? 'date' : undefined} onClick={() => onDateSelect(key)}><span>{WEEKDAYS[index]}</span><strong>{dateNumber(key)}</strong></button><div className="pc-day-events">{(byDay[key] || []).map(item => <EventButton key={item.id} item={item} statusFor={statusFor} onOpen={onOpenOccurrence} />)}{!byDay[key]?.length && <span className="pc-no-events">—</span>}</div></section>)}</div>}
        {view === 'month' && <><div className="pc-weekday-heading">{WEEKDAYS.map(day => <span key={day}>{day}</span>)}</div><div className="pc-month-grid">{Array.from({ length: weekdayIndex(range.start) }, (_, index) => <span className="pc-month-gap" key={`gap-${index}`} aria-hidden="true" />)}{monthDates.map(key => { const entries = byDay[key] || []; return <div key={key} className={`pc-month-day ${key === today ? 'is-today' : ''} ${key === selectedDate ? 'is-selected' : ''}`}><button type="button" className="pc-month-date" aria-label={dateLabel(key)} aria-current={key === today ? 'date' : undefined} onClick={() => onDateSelect(key)}>{dateNumber(key)}</button><div className="pc-month-events">{entries.slice(0, 2).map(item => <EventButton key={item.id} item={item} compact statusFor={statusFor} onOpen={onOpenOccurrence} />)}{entries.length > 2 && <button type="button" className="pc-more" onClick={() => onDateSelect(key)}>+{entries.length - 2} lainnya</button>}</div></div>; })}</div></>}
    </section>;
}
