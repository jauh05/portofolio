import React, { useEffect, useMemo, useState } from 'react';
import { CalendarDays, ChevronLeft, ChevronRight, Plus, Repeat2, Sparkles, X } from 'lucide-react';

const TZ = 'Asia/Jakarta';
const dateKey = (value) => new Intl.DateTimeFormat('en-CA', { timeZone: TZ }).format(new Date(value));
const prettyDate = (value) => new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: TZ }).format(new Date(value));
const initialForm = (type) => ({
    name: '', schedule_type: type, platform: 'instagram', content_type: 'post', topic: '', brief: '', assigned_agent: '', timezone: TZ,
    scheduled_at: '', frequency: 'weekly', days: [1], time: '09:00', starts_at: new Date().toISOString().slice(0, 10), ends_at: '', generation_mode: 'manual', publishing_mode: 'review', is_active: true,
});

function PlannerForm({ type, onClose, onSave }) {
    const [form, setForm] = useState(() => initialForm(type));
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);
    const change = (key, value) => setForm(current => ({ ...current, [key]: value }));
    const toggleDay = (day) => change('days', form.days.includes(day) ? form.days.filter(item => item !== day) : [...form.days, day]);
    const save = async (event) => { event.preventDefault(); setSaving(true); setError(null); try { await onSave(form); onClose(); } catch (caught) { setError(caught.message); } finally { setSaving(false); } };
    return <div className="planner-modal-wrap"><button className="drawer-scrim" onClick={onClose} aria-label="Tutup formulir" /><form className="planner-modal" onSubmit={save}>
        <header><div><span>{type === 'recurring' ? 'RECURRING SCHEDULE' : 'ONE-TIME SCHEDULE'}</span><h2>{type === 'recurring' ? 'Add recurring content' : 'Schedule content'}</h2></div><button type="button" onClick={onClose}><X size={18} /></button></header>
        <label>Name<input required value={form.name} onChange={e => change('name', e.target.value)} placeholder="Weekly content idea" /></label>
        <div className="planner-form-grid"><label>Platform<select value={form.platform} onChange={e => change('platform', e.target.value)}><option>instagram</option><option>linkedin</option><option>twitter</option><option>blog</option></select></label><label>Content type<input required value={form.content_type} onChange={e => change('content_type', e.target.value)} placeholder="post, article, thread" /></label></div>
        <label>Topic<input value={form.topic} onChange={e => change('topic', e.target.value)} placeholder="Optional topic" /></label><label>Brief<textarea value={form.brief} onChange={e => change('brief', e.target.value)} placeholder="Context for the future content" /></label><label>Assigned agent<input value={form.assigned_agent} onChange={e => change('assigned_agent', e.target.value)} placeholder="Optional worker id" /></label>
        {type === 'one_time' ? <label>Scheduled at<input required type="datetime-local" value={form.scheduled_at} onChange={e => change('scheduled_at', e.target.value)} /></label> : <><div className="planner-form-grid"><label>Frequency<select value={form.frequency} onChange={e => change('frequency', e.target.value)}><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option></select></label><label>Time<input required type="time" value={form.time} onChange={e => change('time', e.target.value)} /></label></div>{form.frequency === 'weekly' && <fieldset><legend>Weekdays</legend><div className="weekday-picker">{['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map((label, index) => <button type="button" className={form.days.includes(index + 1) ? 'selected' : ''} onClick={() => toggleDay(index + 1)} key={label}>{label}</button>)}</div></fieldset>}<div className="planner-form-grid"><label>Starts<input required type="date" value={form.starts_at} onChange={e => change('starts_at', e.target.value)} /></label><label>Ends (optional)<input type="date" value={form.ends_at} onChange={e => change('ends_at', e.target.value)} /></label></div></>}
        <div className="planner-form-grid"><label>Generation<select value={form.generation_mode} onChange={e => change('generation_mode', e.target.value)}><option value="manual">Manual</option><option value="automatic">Automatic (future worker)</option></select></label><label>Publishing<select value={form.publishing_mode} onChange={e => change('publishing_mode', e.target.value)}><option value="review">Review first</option><option value="automatic">Automatic (future worker)</option></select></label></div>
        {error && <p className="planner-form-error">{error}</p>}<footer><small>{TZ} · Scheduling only; no worker is triggered.</small><button disabled={saving}>{saving ? 'Saving…' : 'Save schedule'}</button></footer>
    </form></div>;
}

export function ContentPlanner({ planner, loading, loadPlanner, createSchedule }) {
    const [cursor, setCursor] = useState(() => new Date(new Date().getFullYear(), new Date().getMonth(), 1));
    const [formType, setFormType] = useState(null);
    const start = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
    const end = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 0);
    useEffect(() => { loadPlanner(start.toISOString().slice(0, 10), end.toISOString().slice(0, 10)).catch(() => {}); }, [cursor]);
    const days = useMemo(() => Array.from({ length: end.getDate() }, (_, index) => new Date(cursor.getFullYear(), cursor.getMonth(), index + 1)), [cursor]);
    const byDay = useMemo(() => (planner?.occurrences || []).reduce((result, item) => { const key = dateKey(item.scheduledAt); result[key] = [...(result[key] || []), item]; return result; }, {}), [planner]);
    const monthTitle = new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric' }).format(cursor);
    return <div className="content-view planner-view"><div className="view-heading"><div><span>CONTENT PLANNER</span><h1>Content Planner</h1><p>Calendar-based schedules in {TZ}. Saving a schedule never starts a worker.</p></div><div className="planner-actions"><button onClick={() => setFormType('one_time')}><Plus size={15} /> One-time</button><button onClick={() => setFormType('recurring')}><Repeat2 size={15} /> Recurring</button><button disabled title="Coming next"><Sparkles size={15} /> AI Plan · Coming next</button></div></div>
        <section className="planner-layout"><div className="planner-calendar"><header><div><button onClick={() => setCursor(new Date(cursor.getFullYear(), cursor.getMonth() - 1, 1))} aria-label="Bulan sebelumnya"><ChevronLeft size={18} /></button><strong>{monthTitle}</strong><button onClick={() => setCursor(new Date(cursor.getFullYear(), cursor.getMonth() + 1, 1))} aria-label="Bulan berikutnya"><ChevronRight size={18} /></button></div><small>{planner?.occurrences?.length || 0} visible occurrences</small></header><div className="calendar-weekdays">{['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map(day => <span key={day}>{day}</span>)}</div><div className="calendar-grid">{Array.from({ length: (start.getDay() + 6) % 7 }, (_, index) => <i key={`gap-${index}`} />)}{days.map(day => { const entries = byDay[dateKey(day)] || []; return <article key={day.toISOString()} className={dateKey(day) === dateKey(new Date()) ? 'today' : ''}><time>{day.getDate()}</time>{entries.slice(0, 3).map(item => <div className={`calendar-item ${item.scheduleType}`} title={`${item.name} · ${prettyDate(item.scheduledAt)}`} key={item.id}><b>{item.name}</b><small>{item.scheduleType === 'recurring' ? '↻ ' : ''}{item.platform}</small></div>)}{entries.length > 3 && <small className="calendar-more">+{entries.length - 3} more</small>}</article>; })}</div></div><aside className="planner-upcoming"><header><CalendarDays size={16} /><div><span>UPCOMING</span><strong>Next scheduled content</strong></div></header>{loading ? <p>Loading schedules…</p> : planner?.upcoming?.length ? planner.upcoming.map(item => <article key={item.id}><div><b>{item.name}</b><small>{item.platform} · {item.contentType}</small>{item.topic && <small>{item.topic}</small>}</div><time>{prettyDate(item.scheduledAt)}</time><span className={item.scheduleType}>{item.scheduleType === 'recurring' ? item.recurrenceLabel : 'One-time'}</span></article>) : <p>No content scheduled in this visible month.</p>}</aside></section>
        {formType && <PlannerForm type={formType} onClose={() => setFormType(null)} onSave={createSchedule} />}
    </div>;
}
