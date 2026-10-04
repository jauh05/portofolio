import React, { useEffect, useRef, useState } from 'react';
import { CalendarDays, Plus, Repeat2, Sparkles, X } from 'lucide-react';
import { ContentReview } from './ContentReview';
import { PlannerCalendar } from './PlannerCalendar';
import { calendarRange, calendarTitle, occurrenceDateKey, readCalendarView, shiftCalendarDate, todayKey, writeCalendarView } from './calendarViewModel';
import { PANEL_MODES, readPanelMode, writePanelMode } from './panelPreferences';
import { PlannerGenerationCard } from './PlannerGenerationCard';
import { articleReady, generationStatus, resolvePlannerOutput, runGenerationRequest, safeGenerationError, selectGenerationCommand, selectScheduleContent, shouldPollGeneration, STATUS_LABELS } from './plannerGeneration';

const TZ = 'Asia/Jakarta';
const prettyDate = (value) => new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short', timeZone: TZ }).format(new Date(value));
const initialForm = (type, brandId = '') => ({
    name: '', schedule_type: type, platform: 'instagram', content_type: 'feed', topic: '', brief: '', assigned_agent: '', timezone: TZ,
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
        <section className="planner-form-section"><span>BASIC</span><label>Workspace / Brand<select required value={form.brand_id} onChange={e => change('brand_id', e.target.value)}><option value="" disabled>Select a workspace</option>{brands.map(item => <option value={item.id} key={item.id}>{item.name}</option>)}</select></label><label>Output yang akan dibuat<select value={`${form.platform}:${form.content_type}`} onChange={e => { const [platform, contentType] = e.target.value.split(':'); setForm(current => ({ ...current, platform, content_type: contentType, assigned_agent: '' })); }}><option value="instagram:feed">Instagram Feed</option><option value="instagram:story">Instagram Story</option><option value="threads:thread">Threads</option><option value="article:article">Web Article</option>{!['instagram:feed','instagram:story','threads:thread','article:article'].includes(`${form.platform}:${form.content_type}`) && <option value={`${form.platform}:${form.content_type}`}>Output lama: {form.platform} · {form.content_type}</option>}</select></label><label>Topic / title<input required value={form.name} onChange={e => { change('name', e.target.value); change('topic', e.target.value); }} placeholder="Active Recall Tips" /></label></section>
        {type === 'one_time' ? <section className="planner-form-section"><span>WHEN</span><div className="planner-form-grid"><label>Date<input required type="date" value={datePart} onChange={e => setWhen('date', e.target.value)} /></label><label>Time<input required type="time" value={timePart} onChange={e => setWhen('time', e.target.value)} /></label></div></section> : <section className="planner-form-section"><span>SCHEDULE</span><div className="planner-form-grid"><label>Frequency<select value={form.frequency} onChange={e => change('frequency', e.target.value)}><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option></select></label><label>Time<input required type="time" value={form.time} onChange={e => change('time', e.target.value)} /></label></div>{form.frequency === 'weekly' && <fieldset><legend>Weekdays</legend><div className="weekday-picker">{['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map((label, index) => <button type="button" className={form.days.includes(index + 1) ? 'selected' : ''} onClick={() => toggleDay(index + 1)} key={label}>{label}</button>)}</div></fieldset>}<label>{form.frequency === 'monthly' ? 'Starts on (day of month repeats)' : 'Starts'}<input required type="date" value={form.starts_at} onChange={e => change('starts_at', e.target.value)} /></label></section>}
        <details className="planner-more-options"><summary>More options</summary><div><label>Brief<textarea value={form.brief} onChange={e => change('brief', e.target.value)} placeholder="Optional context" /></label><label>Assigned agent<input value={form.assigned_agent} onChange={e => change('assigned_agent', e.target.value)} placeholder="Optional worker id" /></label>{type === 'recurring' && <label>End date<input type="date" value={form.ends_at} onChange={e => change('ends_at', e.target.value)} /></label>}<div className="planner-form-grid"><label>Generation timing<select value={form.generation_timing} onChange={e => { const v = e.target.value; change('generation_timing', v); change('generation_mode', v === 'manual' ? 'manual' : 'automatic'); change('generation_lead_minutes', v === 'h1' ? 1440 : v === 'h2' ? 2880 : null); }}><option value="manual">Manual</option><option value="immediate">Generate sekarang</option><option value="h1">H-1</option><option value="h2">H-2</option></select></label><label>Publishing<select value={form.publishing_mode} onChange={e => change('publishing_mode', e.target.value)}><option value="review">Review sebelum terbit</option><option value="automatic">Terbit otomatis sesuai jadwal</option></select></label></div></div></details>
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

export function ContentPlanner({ planner, brands, loading, loadPlanner, loadPlannerGeneration, createSchedule, analyzePlan, contentItems = [], commands = [], agents = [], generateContent, reviseContent, updateContent, approveContent }) {
    const manageRef = useRef(null);
    const [panelMode, setPanelMode] = useState(() => readPanelMode('plannerPanelMode'));
    const [calendarView, setCalendarView] = useState(readCalendarView);
    const [selectedDate, setSelectedDate] = useState(todayKey);
    const [formType, setFormType] = useState(null);
    const [brandFilter, setBrandFilter] = useState('all');
    const [aiPlanOpen, setAiPlanOpen] = useState(false);
    const [reviewId, setReviewId] = useState(null);
    const [editSchedule, setEditSchedule] = useState(null);
    const [selectedSchedules, setSelectedSchedules] = useState(new Set());
    const [requests, setRequests] = useState({});
    const [notice, setNotice] = useState('');
    const { start: startKey, end: endKey } = calendarRange(calendarView, selectedDate);
    const monthRange = calendarRange('month', selectedDate);
    const monthTitle = calendarTitle('month', selectedDate);
    const schedules = planner?.schedules || [];
    const manageableSchedules = schedules.filter(item => brandFilter === 'all' || item.brand?.id === brandFilter);
    const visibleOccurrences = (planner?.occurrences || []).filter(item => brandFilter === 'all' || item.brand?.id === brandFilter);
    const visibleUpcoming = (planner?.upcoming || []).filter(item => brandFilter === 'all' || item.brand?.id === brandFilter);
    const contentFor = (id) => selectScheduleContent(contentItems, id);
    const commandFor = (content) => selectGenerationCommand(commands, content);
    const reviewContent = contentItems.find(item => item.id === reviewId) || null;
    const loadedStart = occurrenceDateKey(planner?.range?.start);
    const loadedEnd = occurrenceDateKey(planner?.range?.end);
    useEffect(() => {
        if (loadedStart && loadedEnd && loadedStart <= startKey && loadedEnd >= endKey) return;
        loadPlanner(startKey, endKey).catch(() => setNotice('Jadwal belum dapat dimuat. Coba buka kembali Planner.'));
    }, [startKey, endKey, loadedStart, loadedEnd]);
    const scheduleIds = schedules.map(item => item.id).join(',');
    useEffect(() => {
        if (!scheduleIds) return;
        loadPlannerGeneration(scheduleIds.split(',')).catch(() => setNotice('Hasil generation belum dapat dimuat. Planner akan mencoba lagi.'));
    }, [scheduleIds, loadPlannerGeneration]);
    useEffect(() => {
        if (window.location.hash !== '#kelola-planner') return;
        const frame = window.requestAnimationFrame(() => manageRef.current?.scrollIntoView({ block: 'start' }));
        return () => window.cancelAnimationFrame(frame);
    }, []);
    const activeIds = manageableSchedules.filter(schedule => {
        const content = contentFor(schedule.id);
        return shouldPollGeneration(content, commandFor(content), requests[schedule.id]?.phase === 'queued');
    }).map(item => item.id).join(',');
    useEffect(() => {
        if (!activeIds) return undefined;
        let pending = false;
        const poll = async () => {
            if (pending || document.hidden) return;
            pending = true;
            try { await loadPlannerGeneration(activeIds.split(',')); }
            catch { setNotice('Status generation belum dapat diperbarui. Planner akan mencoba lagi.'); }
            finally { pending = false; }
        };
        const timer = window.setInterval(poll, 4000);
        return () => window.clearInterval(timer);
    }, [activeIds, loadPlannerGeneration]);

    const openManage = () => {
        window.history.replaceState(null, '', '#kelola-planner');
        manageRef.current?.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'start' });
    };
    const refreshPlanner = () => loadPlanner(startKey, endKey);
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const postJson = async (url, body = {}) => {
        const response = await fetch(url, { method: 'POST', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify(body) });
        const json = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(safeGenerationError(json.message || 'Permintaan gagal dikirim.'));
        return json;
    };
    const generateScheduleNow = async (schedule) => {
        const output = resolvePlannerOutput(schedule);
        if (output.key === 'unknown' || !articleReady(output, agents) || schedule.publishingMode === 'automatic' || !schedule.isActive) return;
        await runGenerationRequest({ scheduleId: schedule.id, post: postJson, refresh: loadPlannerGeneration,
            onState: state => setRequests(current => ({ ...current, [schedule.id]: state })), onNotice: setNotice });
    };
    const isEligible = (schedule) => {
        const output = resolvePlannerOutput(schedule);
        return schedule.isActive && schedule.publishingMode !== 'automatic' && output.key !== 'unknown' && articleReady(output, agents);
    };
    const bulkGenerate = async (scheduleList, label) => {
        const eligible = scheduleList.filter(isEligible).slice(0, 100);
        const excluded = scheduleList.length - eligible.length;
        if (!eligible.length) { setNotice(`${label}: tidak ada jadwal yang siap digenerate. Periksa output, worker, dan mode publishing.`); return; }
        try {
            const response = await postJson('/office/api/content-schedules/bulk-action', { action: 'generate', schedule_ids: eligible.map(item => item.id) });
            const summary = response.summary || {};
            setNotice(`${label}: ${summary.generated || 0} masuk antrean, ${summary.skipped || 0} dilewati, ${summary.failed || 0} gagal, ${excluded} tidak eligible.`);
            setSelectedSchedules(new Set());
            await loadPlannerGeneration(eligible.map(item => item.id));
        } catch (error) { setNotice(`${label} gagal: ${safeGenerationError(error.message)}`); }
    };
    const bulkAction = async (action, ids = [...selectedSchedules]) => {
        if (!ids.length) return;
        if (action === 'generate') return bulkGenerate(manageableSchedules.filter(item => ids.includes(item.id)), 'Generate Terpilih');
        if (action === 'delete' && !window.confirm(`Hapus ${ids.length} planner? Konten yang sudah dibuat tetap tersimpan.`)) return;
        try {
            await postJson('/office/api/content-schedules/bulk-action', { action, schedule_ids: ids });
            setSelectedSchedules(new Set());
            await refreshPlanner();
        } catch (error) { setNotice(safeGenerationError(error.message)); }
    };
    const generateMonth = () => {
        if (!window.confirm(`Generate konten untuk ${monthTitle}?`)) return;
        const candidates = manageableSchedules.filter(item => item.isActive && (item.scheduleType === 'recurring' || (item.scheduledAt && item.scheduledAt.slice(0, 10) >= monthRange.start && item.scheduledAt.slice(0, 10) <= monthRange.end)));
        bulkGenerate(candidates, `Generate ${monthTitle}`);
    };
    const generateAllActive = () => {
        if (!window.confirm('Generate semua schedule aktif yang eligible? Maksimal 100 item.')) return;
        bulkGenerate(manageableSchedules.filter(item => item.isActive), 'Generate Semua Aktif');
    };
    const updateSchedule = async (schedule, data) => {
        const response = await fetch(`/office/api/content-schedules/${schedule.id}`, { method: 'PATCH', headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() }, body: JSON.stringify(data) });
        const json = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(safeGenerationError(json.message || 'Jadwal gagal diperbarui.'));
        await refreshPlanner();
    };
    const togglePause = async (schedule) => {
        try { await updateSchedule(schedule, { is_active: !schedule.isActive }); }
        catch (error) { setNotice(safeGenerationError(error.message)); }
    };
    const deleteSchedule = async (schedule) => {
        if (!window.confirm(`Hapus planner "${schedule.name}"? Konten yang sudah dibuat tetap tersimpan.`)) return;
        try {
            const response = await fetch(`/office/api/content-schedules/${schedule.id}`, { method: 'DELETE', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() } });
            if (!response.ok) throw new Error('Jadwal gagal dihapus.');
            await refreshPlanner();
            setEditSchedule(null);
            setReviewId(null);
        } catch (error) { setNotice(safeGenerationError(error.message)); }
    };
    const openOccurrence = (occurrence) => {
        const schedule = schedules.find(item => item.id === occurrence.scheduleId);
        if (!schedule) { setNotice('Jadwal tidak ditemukan.'); return; }
        const content = contentFor(schedule.id);
        if (content && ['preview_ready', 'ready_for_review', 'ready', 'approved', 'published'].includes(content.status)) setReviewId(content.id);
        else { openManage(); }
    };
    return <div className="content-view planner-view">
        <div className="view-heading"><div><span>CONTENT PLANNER</span><h1>Content Planner</h1><p>Rencanakan output, pantau generation, dan lihat hasilnya langsung di sini. Jadwal mode Review menunggu persetujuan sebelum terbit.</p></div><div className="planner-actions"><button onClick={() => setFormType('one_time')}><Plus size={15} /> Sekali</button><button onClick={() => setFormType('recurring')}><Repeat2 size={15} /> Berulang</button><button onClick={() => setAiPlanOpen(true)}><Sparkles size={15} /> AI Plan</button><button type="button" onClick={openManage}>Kelola Planner</button></div></div>
        {notice && <div className="planner-notice" role="status"><span>{notice}</span><button type="button" onClick={() => setNotice('')} aria-label="Tutup pesan">×</button></div>}
        <div className="planner-filter"><span>Workspace</span><select value={brandFilter} onChange={event => setBrandFilter(event.target.value)}><option value="all">Semua Brand</option>{brands.map(brand => <option key={brand.id} value={brand.id}>{brand.name}</option>)}</select></div>
        <section className="planner-layout">
            <PlannerCalendar view={calendarView} selectedDate={selectedDate} occurrences={visibleOccurrences} loading={loading}
                statusFor={item => { const content = contentFor(item.scheduleId); return generationStatus(content, commandFor(content)); }}
                onViewChange={view => setCalendarView(writeCalendarView(view))}
                onPrevious={() => setSelectedDate(date => shiftCalendarDate(calendarView, date, -1))}
                onNext={() => setSelectedDate(date => shiftCalendarDate(calendarView, date, 1))}
                onToday={() => setSelectedDate(todayKey())}
                onDateSelect={date => { setSelectedDate(date); setCalendarView(writeCalendarView('day')); }}
                onOpenOccurrence={openOccurrence} />
            <aside className="planner-upcoming"><header><CalendarDays size={16} /><div><span>AKAN DATANG</span><strong>Konten terjadwal berikutnya</strong></div></header>{loading ? <p>Memuat jadwal…</p> : visibleUpcoming.length ? visibleUpcoming.map(item => { const content = contentFor(item.scheduleId); const status = generationStatus(content, commandFor(content)); return <article key={item.id}><div><em>{item.brand?.name || 'Workspace'}</em><b>{item.name}</b><small>{resolvePlannerOutput(item).label} · {STATUS_LABELS[status] || 'Belum dibuat'}</small></div><time>{prettyDate(item.scheduledAt)}</time><button type="button" onClick={() => openOccurrence(item)}>{content ? 'Lihat konten' : 'Buka planner'}</button></article>; }) : <p>Belum ada konten terjadwal pada rentang ini.</p>}</aside>
        </section>
        <section id="kelola-planner" ref={manageRef} className={`planner-manage planner-mode-${panelMode}`}><header><div><span>KELOLA PLANNER</span><h2>Semua Planner</h2><p>{manageableSchedules.length} jadwal pada workspace terpilih.</p><div className="panel-mode-control" role="group" aria-label="Ukuran Planner">{PANEL_MODES.map(mode => <button key={mode} type="button" aria-pressed={panelMode === mode} onClick={() => setPanelMode(writePanelMode('plannerPanelMode', mode))}>{mode === 'compact' ? 'Ringkas' : mode === 'expanded' ? 'Luas' : 'Normal'}</button>)}</div></div><div className="planner-actions"><button disabled={!manageableSchedules.length} onClick={() => setSelectedSchedules(new Set(manageableSchedules.map(item => item.id)))}>Pilih Semua</button><button disabled={!selectedSchedules.size} onClick={() => bulkAction('generate')}>Generate Terpilih</button><button disabled={!selectedSchedules.size} onClick={() => bulkAction('set_h1')}>Set H-1 Terpilih</button><button disabled={!manageableSchedules.length} onClick={generateMonth}>Generate Bulan Ini</button><button disabled={!manageableSchedules.length} onClick={generateAllActive}>Generate Semua Aktif</button><button disabled={!selectedSchedules.size} onClick={() => bulkAction('pause')}>Jeda Terpilih</button><button disabled={!selectedSchedules.size} onClick={() => bulkAction('resume')}>Lanjutkan Terpilih</button><button disabled={!selectedSchedules.size} onClick={() => bulkAction('delete')}>Hapus Terpilih</button><button disabled={!manageableSchedules.length} onClick={() => bulkAction('delete', manageableSchedules.map(item => item.id))}>Hapus Semua</button></div></header>
            {!manageableSchedules.length && <div className="planner-manage-empty"><CalendarDays size={22} /><strong>Belum ada jadwal untuk dikelola</strong><p>Buat jadwal sekali atau berulang terlebih dahulu.</p><button type="button" onClick={() => setFormType('one_time')}>Buat jadwal pertama</button></div>}
            {manageableSchedules.map(schedule => {
                const content = contentFor(schedule.id);
                return <PlannerGenerationCard key={schedule.id} schedule={schedule} content={content} command={commandFor(content)} mode={panelMode} agents={agents} requestState={requests[schedule.id]} optimisticQueued={requests[schedule.id]?.phase === 'queued'} selected={selectedSchedules.has(schedule.id)} onSelect={() => setSelectedSchedules(current => { const next = new Set(current); next.has(schedule.id) ? next.delete(schedule.id) : next.add(schedule.id); return next; })} onGenerate={() => generateScheduleNow(schedule)} onReview={() => content && setReviewId(content.id)} onEdit={() => setEditSchedule(schedule)} onPause={() => togglePause(schedule)} onDelete={() => deleteSchedule(schedule)} onSetH1={() => updateSchedule(schedule, { ...mapScheduleToForm(schedule), generation_mode: 'automatic', generation_timing: 'lead_time', generation_lead_minutes: 1440 }).catch(error => setNotice(safeGenerationError(error.message)))} />;
            })}
        </section>
        {formType && <PlannerForm type={formType} brands={brands} onClose={() => setFormType(null)} onSave={createSchedule} />}
        {editSchedule && <PlannerForm type={editSchedule.scheduleType} brands={brands} initialData={editSchedule} onClose={() => setEditSchedule(null)} onSave={data => updateSchedule(editSchedule, data)} />}
        {aiPlanOpen && <AiPlanModal brands={brands} onClose={() => setAiPlanOpen(false)} onAnalyze={analyzePlan} onSave={createSchedule} />}
        <ContentReview contentItem={reviewContent} onClose={() => setReviewId(null)} generateContent={generateContent} reviseContent={reviseContent} updateContent={updateContent} approveContent={approveContent} />
    </div>;
}
