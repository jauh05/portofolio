import React, { useEffect, useMemo, useState } from 'react';
import { CalendarDays, ChevronLeft, ChevronRight, Plus, Repeat2, Sparkles, X } from 'lucide-react';
import { ContentReview } from './ContentReview';

const TZ = 'Asia/Jakarta';
const dateKey = (value) => new Intl.DateTimeFormat('en-CA', { timeZone: TZ }).format(new Date(value));
const prettyDate = (value) => new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: TZ }).format(new Date(value));
const initialForm = (type, brandId = '') => ({
    name: '', schedule_type: type, platform: 'instagram', content_type: 'post', topic: '', brief: '', assigned_agent: '', timezone: TZ,
    brand_id: brandId, scheduled_at: '', frequency: 'weekly', days: [1], time: '09:00', starts_at: new Date().toISOString().slice(0, 10), ends_at: '', generation_mode: 'manual', generation_timing: 'manual', generation_lead_minutes: null, publishing_mode: 'review', is_active: true,
});

const mapScheduleToForm = (schedule) => {
    return {
        id: schedule.id,
        name: schedule.name || '',
        schedule_type: schedule.scheduleType,
        platform: schedule.platform,
        content_type: schedule.contentType,
        topic: schedule.topic || '',
        brief: schedule.brief || '',
        assigned_agent: schedule.assignedAgent || '',
        timezone: schedule.timezone || TZ,
        brand_id: schedule.brandId || '',
        scheduled_at: schedule.scheduledAt ? schedule.scheduledAt.slice(0, 16) : '',
        frequency: schedule.recurrenceRule?.frequency || 'weekly',
        days: schedule.recurrenceRule?.days || [1],
        time: schedule.recurrenceRule?.time || '09:00',
        starts_at: schedule.startsAt ? schedule.startsAt.slice(0, 10) : new Date().toISOString().slice(0, 10),
        ends_at: schedule.endsAt ? schedule.endsAt.slice(0, 10) : '',
        generation_mode: schedule.generationMode || 'manual',
        generation_timing: schedule.generationTiming || 'manual',
        generation_lead_minutes: schedule.generationLeadMinutes || null,
        publishing_mode: schedule.publishingMode || 'review',
        is_active: schedule.isActive,
    };
};

function PlannerForm({ type, brands, onClose, onSave, initialData }) {
    const [form, setForm] = useState(() => initialData ? mapScheduleToForm(initialData) : initialForm(type, brands[0]?.id));
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);
    const change = (key, value) => setForm(current => ({ ...current, [key]: value }));
    const toggleDay = (day) => change('days', form.days.includes(day) ? form.days.filter(item => item !== day) : [...form.days, day]);
    const save = async (event) => { event.preventDefault(); setSaving(true); setError(null); try { const payload = { ...form }; if (payload.generation_timing === 'h1' || payload.generation_timing === 'h2') payload.generation_timing = 'lead_time'; await onSave(payload); onClose(); } catch (caught) { setError(caught.message); } finally { setSaving(false); } };
    const datePart = form.scheduled_at.slice(0, 10);
    const timePart = form.scheduled_at.slice(11, 16);
    const setWhen = (part, value) => change('scheduled_at', `${part === 'date' ? value : datePart}T${part === 'time' ? value : timePart}`);
    const brand = brands.find(item => item.id === form.brand_id);
    const summary = type === 'one_time' ? (form.scheduled_at ? prettyDate(form.scheduled_at) : 'Choose a date and time') : `${form.frequency === 'weekly' ? `Every ${form.days.map(day => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][day - 1]).join(', ')}` : form.frequency === 'monthly' ? 'Every month' : 'Every day'} at ${form.time}`;
    return <div className="planner-modal-wrap"><button type="button" className="planner-modal-scrim" onClick={onClose} aria-label="Tutup formulir" /><form className="planner-modal" onSubmit={save}>
        <header><div><span>{type === 'recurring' ? 'RECURRING SCHEDULE' : 'ONE-TIME SCHEDULE'}</span><h2>{type === 'recurring' ? 'Add recurring content' : 'Schedule content'}</h2></div><button type="button" onClick={onClose}><X size={18} /></button></header>
        <section className="planner-form-section"><span>BASIC</span><label>Workspace / Brand<select required value={form.brand_id} onChange={e => change('brand_id', e.target.value)}><option value="" disabled>Select a workspace</option>{brands.map(item => <option value={item.id} key={item.id}>{item.name}</option>)}</select></label><div className="planner-form-grid"><label>Platform<select value={form.platform} onChange={e => change('platform', e.target.value)}><option>instagram</option><option>linkedin</option><option>twitter</option><option>blog</option></select></label><label>Content type<input required value={form.content_type} onChange={e => change('content_type', e.target.value)} placeholder="feed, story, article" /></label></div><label>Topic / title<input required value={form.name} onChange={e => { change('name', e.target.value); change('topic', e.target.value); }} placeholder="Active Recall Tips" /></label></section>
        {type === 'one_time' ? <section className="planner-form-section"><span>WHEN</span><div className="planner-form-grid"><label>Date<input required type="date" value={datePart} onChange={e => setWhen('date', e.target.value)} /></label><label>Time<input required type="time" value={timePart} onChange={e => setWhen('time', e.target.value)} /></label></div></section> : <section className="planner-form-section"><span>SCHEDULE</span><div className="planner-form-grid"><label>Frequency<select value={form.frequency} onChange={e => change('frequency', e.target.value)}><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option></select></label><label>Time<input required type="time" value={form.time} onChange={e => change('time', e.target.value)} /></label></div>{form.frequency === 'weekly' && <fieldset><legend>Weekdays</legend><div className="weekday-picker">{['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map((label, index) => <button type="button" className={form.days.includes(index + 1) ? 'selected' : ''} onClick={() => toggleDay(index + 1)} key={label}>{label}</button>)}</div></fieldset>}<label>{form.frequency === 'monthly' ? 'Starts on (day of month repeats)' : 'Starts'}<input required type="date" value={form.starts_at} onChange={e => change('starts_at', e.target.value)} /></label></section>}
        <details className="planner-more-options"><summary>More options</summary><div><label>Brief<textarea value={form.brief} onChange={e => change('brief', e.target.value)} placeholder="Optional context" /></label><label>Assigned agent<input value={form.assigned_agent} onChange={e => change('assigned_agent', e.target.value)} placeholder="Optional worker id" /></label>{type === 'recurring' && <label>End date<input type="date" value={form.ends_at} onChange={e => change('ends_at', e.target.value)} /></label>}<div className="planner-form-grid"><label>Generation timing<select value={form.generation_timing} onChange={e => { const v = e.target.value; change('generation_timing', v); change('generation_mode', v === 'manual' ? 'manual' : 'automatic'); change('generation_lead_minutes', v === 'h1' ? 1440 : v === 'h2' ? 2880 : null); }}><option value="manual">Manual</option><option value="immediate">Generate sekarang</option><option value="h1">H-1</option><option value="h2">H-2</option></select></label><label>Publishing<select value={form.publishing_mode} onChange={e => change('publishing_mode', e.target.value)}><option value="review">Review first</option><option value="automatic">Automatic preference</option></select></label></div></div></details>
        <p className="planner-summary"><b>{summary}</b><span>{brand?.name || 'Choose a workspace'} · {form.platform} · {form.content_type}</span></p>
        <footer><small>{TZ} · Manual = schedule saved only. Automatic = sent to worker.</small><button disabled={saving}>{saving ? 'Saving…' : 'Save schedule'}</button></footer>
    </form></div>;
}

function AiPlanModal({ brands, onClose, onAnalyze, onSave }) {
    const [prompt, setPrompt] = useState('');
    const [result, setResult] = useState(null);
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState(null);
    const analyze = async (event) => { event.preventDefault(); setLoading(true); setError(null); try { setResult(await onAnalyze(prompt)); } catch (caught) { setError(caught.message || 'Planner could not analyze this request.'); } finally { setLoading(false); } };
    const confirm = async () => { setSaving(true); setError(null); try { for (const item of result.plan.items) await onSave(item); onClose(); } catch (caught) { setError(caught.message || 'Could not save this schedule preview.'); } finally { setSaving(false); } };
    return <div className="planner-modal-wrap"><button type="button" className="planner-modal-scrim" onClick={onClose} aria-label="Close AI Plan" /><section className="planner-modal ai-plan-modal"><header><div><span>AI PLAN</span><h2>Plan content with AI</h2></div><button type="button" onClick={onClose}><X size={18} /></button></header>{!result ? <form onSubmit={analyze}><p>Tell the Analyst what you want to schedule. It will only create a preview.</p><textarea required minLength="8" value={prompt} onChange={event => setPrompt(event.target.value)} placeholder="Every Monday create a Kauiz feed at 19:00 and a story at 12:00 about study tips." /><small>Schedules are never generated or published from this step.</small>{error && <p className="planner-form-error">{error}</p>}<footer><span /><button disabled={loading}>{loading ? 'Analyzing…' : 'Analyze plan'}</button></footer></form> : <div className="ai-plan-preview">{result.clarifications?.length > 0 && <div className="ai-clarifications"><b>Needs clarification</b>{result.clarifications.map(item => <p key={item}>{item}</p>)}</div>}{result.plan.items.map((item, index) => { const brand = brands.find(candidate => candidate.id === item.brand_id); return <article key={`${item.name}-${index}`}><em>{brand?.name || 'Workspace'}</em><b>{item.platform} · {item.content_type}</b><strong>{item.topic || item.name}</strong><small>{item.schedule_type === 'recurring' ? `${item.frequency === 'weekly' ? `Every ${item.days.map(day => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'][day - 1]).join(', ')}` : `Every ${item.frequency}`} · ${item.time}` : prettyDate(item.scheduled_at)}</small><span>{item.schedule_type === 'recurring' ? 'Recurring' : 'One-time'}</span></article>})}{error && <p className="planner-form-error">{error}</p>}<footer><button type="button" className="ai-back" onClick={() => setResult(null)}>Back / Edit</button><button disabled={saving || result.plan.items.length === 0} onClick={confirm}>{saving ? 'Saving…' : 'Confirm schedule'}</button></footer></div>}</section></div>;
}

export function ContentPlanner({ planner, brands, loading, loadPlanner, createSchedule, analyzePlan, contentItems, generateContent, reviseContent, updateContent, approveContent }) {
    const [cursor, setCursor] = useState(() => new Date(new Date().getFullYear(), new Date().getMonth(), 1));
    const [formType, setFormType] = useState(null);
    const [brandFilter, setBrandFilter] = useState('all');
    const [aiPlanOpen, setAiPlanOpen] = useState(false);
    const [reviewItem, setReviewItem] = useState(null);
    const [selectedSchedules, setSelectedSchedules] = useState(new Set());
    const start = new Date(cursor.getFullYear(), cursor.getMonth(), 1);
    const end = new Date(cursor.getFullYear(), cursor.getMonth() + 1, 0);
    useEffect(() => { loadPlanner(start.toISOString().slice(0, 10), end.toISOString().slice(0, 10)).catch(() => {}); }, [cursor]);
    const days = useMemo(() => Array.from({ length: end.getDate() }, (_, index) => new Date(cursor.getFullYear(), cursor.getMonth(), index + 1)), [cursor]);
    const visibleOccurrences = useMemo(() => (planner?.occurrences || []).filter(item => brandFilter === 'all' || item.brand?.id === brandFilter), [planner, brandFilter]);
    const visibleUpcoming = useMemo(() => (planner?.upcoming || []).filter(item => brandFilter === 'all' || item.brand?.id === brandFilter), [planner, brandFilter]);
    const manageableSchedules = useMemo(() => (planner?.schedules || []).filter(item => brandFilter === 'all' || item.brand?.id === brandFilter), [planner, brandFilter]);
    const byDay = useMemo(() => visibleOccurrences.reduce((result, item) => { const key = dateKey(item.scheduledAt); result[key] = [...(result[key] || []), item]; return result; }, {}), [visibleOccurrences]);
    const monthTitle = new Intl.DateTimeFormat('id-ID', { month: 'long', year: 'numeric' }).format(cursor);

    const [editSchedule, setEditSchedule] = useState(null);

    const handleOccurrenceClick = (occurrence) => {
        const schedule = planner?.schedules?.find(s => s.id === occurrence.scheduleId);
        const draft = contentItems.find(c => c.schedule_id === occurrence.scheduleId && (c.metadata?.scheduled_at === occurrence.scheduledAt || occurrence.scheduleType === 'one_time'));
        if (!schedule) {
            console.warn('Schedule record missing for occurrence item', occurrence);
            return;
        }
        setReviewItem({ draft: draft || null, schedule: schedule, occurrence });
    };

    const ScheduleCard = ({ item }) => {
        const schedule = item.scheduleId ? (planner?.schedules?.find(s => s.id === item.scheduleId) || item) : item;
        return (
            <article key={item.id} className={!schedule.isActive ? 'paused-schedule' : ''}>
                <div><em>{item.brand?.name || 'Unassigned'}</em><b>{item.name}</b><small>{item.platform} · {item.contentType}</small>{item.topic && <small>{item.topic}</small>}</div>
                <time>{prettyDate(item.scheduledAt)}</time><span className={item.scheduleType}>{item.scheduleType === 'recurring' ? item.recurrenceLabel : 'One-time'}</span>
                {!schedule.isActive && <div style={{color: 'var(--amber-11)', fontSize: 11, marginTop: 4, fontWeight: 500}}>PAUSED</div>}
                <div style={{marginTop: 8, display: 'flex', gap: '4px', flexWrap: 'wrap'}}>
                    {(() => {
                        const draft = contentItems.find(c => c.schedule_id === item.scheduleId && (c.metadata?.scheduled_at === item.scheduledAt || item.scheduleType === 'one_time'));
                        if (draft) {
                            return <button onClick={() => setReviewItem({draft, schedule})} className="primary-button" style={{fontSize: 11, padding: '4px 8px'}}>Draft: {draft.status}</button>;
                        }
                        return null;
                    })()}
                    <button onClick={() => setEditSchedule(schedule)} style={{fontSize: 11, padding: '4px 8px'}}>Edit</button>
                    <button onClick={() => togglePause(schedule)} style={{fontSize: 11, padding: '4px 8px'}}>{schedule.isActive ? 'Pause' : 'Resume'}</button>
                    <button onClick={() => deleteSchedule(schedule)} style={{fontSize: 11, padding: '4px 8px', color: 'red'}}>Hapus</button>
                </div>
            </article>
        );
    };

    const deleteSchedule = async (schedule) => {
        if (!window.confirm(`Yakin ingin menghapus planner "${schedule.name}"?\n(Konten yang sudah dibuat tidak akan dihapus, tetapi jadwal ini akan berhenti dan disembunyikan)`)) return;
        try {
            const res = await fetch(`/office/api/content-schedules/${schedule.id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } });
            if (!res.ok) throw new Error('Failed to delete schedule');
            await loadPlanner(start.toISOString().slice(0, 10), end.toISOString().slice(0, 10));
            setEditSchedule(null);
            setReviewItem(null);
        } catch (error) {
            console.error('Failed to delete schedule', error);
            alert('Gagal menghapus schedule: ' + error.message);
        }
    };

    const togglePause = async (schedule) => {
        try {
            const res = await fetch(`/office/api/content-schedules/${schedule.id}`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify({ is_active: !schedule.isActive })
            });
            if (!res.ok) throw new Error('Failed to update status');
            await loadPlanner(start.toISOString().slice(0, 10), end.toISOString().slice(0, 10));
        } catch (error) {
            console.error(error);
            alert('Gagal mengubah status: ' + error.message);
        }
    };

    const bulkAction = async (action, ids = [...selectedSchedules]) => {
        if (!ids.length) return;
        if (action === 'delete' && !window.confirm(`Hapus ${ids.length} planner?\nPlanner tidak akan dijalankan lagi. Konten yang sudah dibuat tetap tersimpan.`)) return;
        const res = await fetch('/office/api/content-schedules/bulk-action', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]').content }, body: JSON.stringify({ action, schedule_ids: ids }) });
        if (!res.ok) throw new Error('Bulk action failed');
        setSelectedSchedules(new Set());
        await loadPlanner(start.toISOString().slice(0, 10), end.toISOString().slice(0, 10));
    };

    const updateSchedule = async (schedule, data) => {
        try {
            const res = await fetch(`/office/api/content-schedules/${schedule.id}`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: JSON.stringify(data)
            });
            if (!res.ok) throw new Error('Failed to update schedule');
            await loadPlanner(start.toISOString().slice(0, 10), end.toISOString().slice(0, 10));
        } catch (error) {
            console.error(error);
            alert('Gagal mengedit jadwal: ' + error.message);
            throw error;
        }
    };

    const postJson = async (url, body = {}) => {
        const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }, body: JSON.stringify(body) });
        const json = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(json.message || 'Request failed');
        return json;
    };
    const generateScheduleNow = async (schedule) => { await postJson(`/office/api/content-schedules/${schedule.id}/generate`); await loadPlanner(start.toISOString().slice(0, 10), end.toISOString().slice(0, 10)); };
    const generateMonth = async () => { if (!window.confirm(`Generate konten untuk ${monthTitle}?`)) return; await postJson('/office/api/content-schedules/generate-month', { start: start.toISOString().slice(0, 10), end: end.toISOString().slice(0, 10), brand_id: brandFilter === 'all' ? null : brandFilter }); await loadPlanner(start.toISOString().slice(0, 10), end.toISOString().slice(0, 10)); };
    const generateAllActive = async () => { if (!window.confirm('Generate semua schedule aktif yang eligible? Maksimal 100 item.')) return; await postJson('/office/api/content-schedules/generate-all-active'); await loadPlanner(start.toISOString().slice(0, 10), end.toISOString().slice(0, 10)); };
    const contentForSchedule = (schedule) => contentItems.find(c => c.schedule_id === schedule.id);
    const generationLabel = (schedule) => schedule.generationTiming === 'lead_time' ? (schedule.generationLeadMinutes === 2880 ? 'H-2' : 'H-1') : schedule.generationTiming === 'immediate' ? 'Sekarang' : 'Manual';
    const contentLabel = (schedule) => ({ draft: 'Belum dibuat', queued_for_generation: 'Antre', generating: 'Sedang dibuat', ready_for_review: 'Siap direview', approved: 'Approved', published: 'Published', failed: 'Failed' }[contentForSchedule(schedule)?.status] || 'Belum dibuat');

    return <div className="content-view planner-view"><div className="view-heading"><div><span>CONTENT PLANNER</span><h1>Content Planner</h1><p>Calendar-based schedules in {TZ}. <b>Manual</b> saves the schedule without starting generation. <b>Automatic preference</b> sends content to worker automatically.</p></div><div className="planner-actions"><button onClick={() => setFormType('one_time')}><Plus size={15} /> Sekali</button><button onClick={() => setFormType('recurring')}><Repeat2 size={15} /> Berulang</button><button onClick={() => setAiPlanOpen(true)}><Sparkles size={15} /> AI Plan</button><a href="#kelola-planner">Kelola Planner</a></div></div>
        <div className="planner-filter"><span>Workspace</span><select value={brandFilter} onChange={event => setBrandFilter(event.target.value)}><option value="all">Semua Brand</option>{brands.map(brand => <option key={brand.id} value={brand.id}>{brand.name}</option>)}</select></div>
        <section className="planner-layout"><div className="planner-calendar"><header><div><button onClick={() => setCursor(new Date(cursor.getFullYear(), cursor.getMonth() - 1, 1))} aria-label="Bulan sebelumnya"><ChevronLeft size={18} /></button><strong>{monthTitle}</strong><button onClick={() => setCursor(new Date(cursor.getFullYear(), cursor.getMonth() + 1, 1))} aria-label="Bulan berikutnya"><ChevronRight size={18} /></button></div><small>{visibleOccurrences.length} visible occurrences</small></header><div className="calendar-weekdays">{['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map(day => <span key={day}>{day}</span>)}</div><div className="calendar-grid">{Array.from({ length: (start.getDay() + 6) % 7 }, (_, index) => <i key={`gap-${index}`} />)}{days.map(day => { const entries = byDay[dateKey(day)] || []; return <article key={day.toISOString()} className={dateKey(day) === dateKey(new Date()) ? 'today' : ''}><time>{day.getDate()}</time>{entries.slice(0, 3).map(item => <div onClick={() => handleOccurrenceClick(item)} style={{cursor: 'pointer'}} className={`calendar-item ${item.scheduleType}`} title={`${item.brand?.name || 'No workspace'} · ${item.name} · ${prettyDate(item.scheduledAt)}`} key={item.id}><em>{item.brand?.name || 'Unassigned'}</em><b>{item.name}</b><small>{item.platform} · {item.contentType}</small><small>{item.scheduleType === 'recurring' ? `↻ ${item.recurrenceLabel}` : 'One-time'}</small></div>)}{entries.length > 3 && <small className="calendar-more">+{entries.length - 3} more</small>}</article>; })}</div></div><aside className="planner-upcoming"><header><CalendarDays size={16} /><div><span>AKAN DATANG BULAN INI</span><strong>Konten terjadwal berikutnya</strong></div></header>{loading ? <p>Loading schedules…</p> : visibleUpcoming.length ? visibleUpcoming.map(item => <article key={item.id}><div><em>{item.brand?.name || 'Unassigned'}</em><b>{item.name}</b><small>{item.platform} · {item.contentType}</small>{item.topic && <small>{item.topic}</small>}</div><time>{prettyDate(item.scheduledAt)}</time><span className={item.scheduleType}>{item.scheduleType === 'recurring' ? item.recurrenceLabel : 'One-time'}</span>
<div style={{marginTop: 8}}>
    {(() => {
        const draft = contentItems.find(c => c.schedule_id === item.scheduleId && (c.metadata?.scheduled_at === item.scheduledAt || item.scheduleType === 'one_time'));
        if (!draft) return null;
        return <button onClick={() => setReviewItem({ draft, schedule: planner?.schedules?.find(s => s.id === item.scheduleId), occurrence: item })} className="primary-button" style={{fontSize: 11, padding: '4px 8px'}}>Draft: {draft.status}</button>;
    })()}
</div>
</article>) : <p>No content scheduled in this visible month.</p>}</aside></section>

        <section id="kelola-planner" className="planner-manage" style={{marginTop: 20}}><header><div><span>KELOLA PLANNER</span><h2>Semua Planner</h2></div><div className="planner-actions"><button onClick={() => setSelectedSchedules(new Set(manageableSchedules.map(item => item.id)))}>Pilih Semua</button><button onClick={() => bulkAction('generate')}>Generate Terpilih</button><button onClick={() => bulkAction('set_h1')}>Set H-1 Terpilih</button><button onClick={generateMonth}>Generate Semua Bulan Ini</button><button onClick={generateAllActive}>Generate Semua Aktif</button><button onClick={() => bulkAction('pause')}>Jeda Terpilih</button><button onClick={() => bulkAction('resume')}>Lanjutkan Terpilih</button><button onClick={() => bulkAction('delete')}>Hapus Terpilih</button><button onClick={() => bulkAction('delete', manageableSchedules.map(item => item.id))}>Hapus Semua</button></div></header>{manageableSchedules.map(schedule => { const content = contentForSchedule(schedule); const busy = ['queued_for_generation', 'generating'].includes(content?.status); const done = ['ready_for_review', 'approved', 'published'].includes(content?.status); return <article key={schedule.id} className={!schedule.isActive ? 'paused-schedule' : ''} style={{display: 'grid', gridTemplateColumns: 'auto 1fr auto', gap: 12, alignItems: 'center'}}><input type="checkbox" checked={selectedSchedules.has(schedule.id)} onChange={() => setSelectedSchedules(current => { const next = new Set(current); next.has(schedule.id) ? next.delete(schedule.id) : next.add(schedule.id); return next; })} /><div><strong>{schedule.brand?.name || 'Unassigned'} · {schedule.name}</strong><p>{schedule.scheduleType === 'recurring' ? 'Berulang' : 'Sekali'} · {schedule.recurrenceLabel || (schedule.scheduledAt ? prettyDate(schedule.scheduledAt) : '-')} · Source: {schedule.metadata?.source || 'manual'}</p><small>Generation: {generationLabel(schedule)} · Content: {contentLabel(schedule)} · Publishing: {schedule.publishingMode === 'automatic' ? 'Otomatis' : 'Review'} · {schedule.isActive ? 'Aktif' : 'DIJEDA'}</small></div><div className="planner-actions"><button disabled={busy || done || !schedule.isActive} onClick={() => generateScheduleNow(schedule)}>{busy ? 'Sedang dibuat' : done ? 'Siap direview' : 'Generate Sekarang'}</button><button onClick={() => updateSchedule(schedule, { ...mapScheduleToForm(schedule), generation_mode: 'automatic', generation_timing: 'lead_time', generation_lead_minutes: 1440 })}>Set H-1</button><button onClick={() => setEditSchedule(schedule)}>Edit</button><button onClick={() => togglePause(schedule)}>{schedule.isActive ? 'Jeda' : 'Lanjutkan'}</button><button onClick={() => deleteSchedule(schedule)}>Hapus</button></div></article>; })}</section>
        {formType && <PlannerForm type={formType} brands={brands} onClose={() => setFormType(null)} onSave={createSchedule} />}
        {editSchedule && <PlannerForm type={editSchedule.scheduleType} brands={brands} initialData={editSchedule} onClose={() => setEditSchedule(null)} onSave={(data) => updateSchedule(editSchedule, data)} />}
        {aiPlanOpen && <AiPlanModal brands={brands} onClose={() => setAiPlanOpen(false)} onAnalyze={analyzePlan} onSave={createSchedule} />}
        <ContentReview contentItem={reviewItem?.draft} schedule={reviewItem?.schedule} occurrence={reviewItem?.occurrence} onClose={() => setReviewItem(null)} generateContent={generateContent} reviseContent={reviseContent} updateContent={updateContent} approveContent={approveContent} />
    </div>;
}
