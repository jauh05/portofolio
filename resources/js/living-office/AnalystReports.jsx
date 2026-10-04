import React, { useEffect, useMemo, useState } from 'react';
import { BarChart3, Check, ChevronRight, ClipboardCheck, Clock3, FileClock, Lightbulb, Play, Sparkles, X } from 'lucide-react';
import './analyst-reports.css';
import './analyst-report-mode.css';

const scopeLabels = {
    today: 'Today', last_7_days: 'Last 7 days', content_planner: 'Content planner', worker_performance: 'Worker activity', brand: 'Brand workspace',
};

function formatTime(value) {
    return value ? new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : '—';
}

function statusLabel(value) {
    return { ready: 'Perlu ditinjau', reviewed: 'Reviewed', draft: 'Draft' }[value] || value;
}

export function AnalystReports({ reports, brands, loading, generateReport, approveReport, dismissReport }) {
    const [selectedId, setSelectedId] = useState(null);
    const [scope, setScope] = useState('today');
    const [brandId, setBrandId] = useState('');
    const [planningHorizon, setPlanningHorizon] = useState('7_days');
    const [selectedActions, setSelectedActions] = useState(new Set());
    const [pending, setPending] = useState(false);
    const [error, setError] = useState(null);
    const selected = useMemo(() => reports.find(report => report.id === selectedId) || reports[0] || null, [reports, selectedId]);

    useEffect(() => { if (!selectedId && reports[0]) setSelectedId(reports[0].id); }, [reports, selectedId]);
    useEffect(() => { setSelectedActions(new Set()); }, [selected?.id]);

    const generate = async () => {
        setPending(true); setError(null);
        try {
            const report = await generateReport({ report_type: 'manual', scope, planning_horizon: planningHorizon, ...(scope === 'brand' && brandId ? { brand_id: brandId } : {}) });
            setSelectedId(report.id);
        } catch (caught) { setError(caught.message || 'Unable to generate the report.'); }
        finally { setPending(false); }
    };
    const decide = async (method) => {
        if (!selectedActions.size || !selected) return;
        setPending(true); setError(null);
        try { await method(selected.id, [...selectedActions]); setSelectedActions(new Set()); }
        catch (caught) { setError(caught.message || 'Unable to update the proposed action.'); }
        finally { setPending(false); }
    };
    const toggleAction = (id) => setSelectedActions(current => {
        const next = new Set(current); next.has(id) ? next.delete(id) : next.add(id); return next;
    });
    const contentPlans = selected?.contentPlans || [];
    const planProposed = contentPlans.filter(plan => plan.status === 'proposed');
    const legacyProposed = selected?.nextActions?.filter(action => action.status === 'proposed') || [];
    const proposed = planProposed.length ? planProposed : legacyProposed;

    return <div className="analyst-view">
        <section className="analyst-hero">
            <div className="analyst-intro"><span className="eyebrow"><Sparkles size={14} /> INTERNAL OFFICE ANALYST</span><div className="analyst-title-line"><div><h1>Daily report &amp; planning</h1><p>Fakta Office dan rencana yang menunggu persetujuan.</p></div><div className="analyst-intro-meta"><span><FileClock size={14} /> {reports.length} Reports</span><span><ClipboardCheck size={14} /> Approval required</span></div></div></div>
            <div className="analyst-runner"><div className="analyst-runner-fields"><label>Scope<select value={scope} onChange={event => setScope(event.target.value)}>{Object.entries(scopeLabels).map(([value, label]) => <option value={value} key={value}>{label}</option>)}</select></label><label>Plan period<select value={planningHorizon} onChange={event => setPlanningHorizon(event.target.value)}><option value="only_analysis">Hanya Analisis</option><option value="7_days">7 Hari</option><option value="14_days">14 Hari</option><option value="30_days">30 Hari / Bulanan</option></select></label>{scope === 'brand' && <label>Workspace<select value={brandId} onChange={event => setBrandId(event.target.value)}><option value="">All brands</option>{brands.map(brand => <option key={brand.id} value={brand.id}>{brand.name}</option>)}</select></label>}</div><button className="primary-button" onClick={generate} disabled={pending || (scope === 'brand' && !brandId)}><Play size={15} />{pending ? 'Generating…' : 'Run analysis'}</button></div>
        </section>
        {error && <p className="analyst-error">{error}</p>}
        <div className="analyst-layout">
            <aside className="analyst-history"><div className="panel-label">REPORT HISTORY <b>{reports.length}</b></div>{reports.length ? reports.map(report => <button className={selected?.id === report.id ? 'active' : ''} key={report.id} onClick={() => setSelectedId(report.id)}><span><strong>{report.reportType} brief</strong><small>{report.reportDate}</small></span><em className={`report-status ${report.status}`}>{statusLabel(report.status)}</em><ChevronRight size={14} /></button>) : <div className="analyst-history-empty"><FileClock size={21} /><strong>Belum ada laporan</strong><p>Analisis pertama akan muncul di sini.</p></div>}</aside>
            <section className="analyst-report">{loading && !selected ? <p>Loading analyst reports…</p> : selected ? <><header><div><span className="eyebrow"><ClipboardCheck size={13} /> {selected.reportType} ANALYSIS</span><h2>{selected.reportDate} · {statusLabel(selected.status)}</h2><time><Clock3 size={13} /> Dibuat {formatTime(selected.generatedAt)}</time></div><span className={`report-source ${selected.metadata?.analysis_mode === 'fallback_internal' ? 'fallback' : ''}`}>{selected.metadata?.analysis_mode === 'ai' && selected.metadata?.source === 'agent_reach' ? `Agent-Reach Research + AI Analysis` : 'Analisis internal — riset eksternal tidak tersedia'} · {selected.metadata?.performance_available ? 'Performance available' : 'Data performa belum tersedia'}</span></header>
                <article className="analyst-summary"><h3>Ringkasan Eksekutif</h3><p>{selected.summary}</p></article>
                <div className="analyst-sections"><article><h3>Temuan</h3><div className="finding-list">{selected.findings?.map((finding, index) => <div className={`finding finding-${finding.type}`} key={`${finding.title}-${index}`}><strong>{finding.title}</strong><p>{finding.description}</p><small>Evidence: {finding.evidence}</small></div>)}</div></article><article><h3>Kesimpulan</h3><p className="analyst-conclusion">{selected.conclusion}</p><h3>Rekomendasi</h3><ol className="recommendation-list">{selected.recommendations?.map((recommendation, index) => <li key={`${recommendation.action}-${index}`}><span className={recommendation.priority}>{{high: 'Tinggi', medium: 'Sedang', low: 'Rendah'}[recommendation.priority] || recommendation.priority}</span><strong>{recommendation.action}</strong><p>{recommendation.reason}</p></li>)}</ol></article></div>
                <article className="forward-plan"><header><div><span className="eyebrow"><Lightbulb size={13} /> MEMERLUKAN PERSETUJUAN OWNER</span><h3>Rencana Konten · {selected.metadata?.planning_horizon === '30_days' ? 'Bulanan' : (selected.metadata?.planning_horizon || '7 Hari').replace('_days', ' Hari')}</h3><p>{selected.metadata?.source === 'agent_reach' ? `Berdasarkan Agent-Reach · ${(selected.metadata?.source_count || selected.metadata?.sources_count || 0)} sumber` : 'Analisis AI · Data Internal'}</p></div>{proposed.length > 0 && <span>{selectedActions.size}/{proposed.length} dipilih</span>}</header>{contentPlans.length ? <div className="action-list">{contentPlans.map(plan => <label className={`plan-action ${plan.status}`} key={plan.id}><input type="checkbox" disabled={plan.status !== 'proposed'} checked={selectedActions.has(plan.id)} onChange={() => toggleAction(plan.id)} /><span><strong>{formatTime(plan.scheduledAt)} · {plan.brand?.name} · {plan.platform} {plan.contentType}</strong><b>{plan.topic}</b><small>Objective: {plan.metadata?.objective || '-'} · Angle: {plan.metadata?.angle || '-'} · Evidence: {(plan.metadata?.source_evidence || []).length} sumber</small><small>Alasan: {plan.reason}</small><em className={`report-status ${plan.status}`}>{plan.status.toUpperCase()}</em></span></label>)}</div> : selected.nextActions?.length ? <div className="action-list">{selected.nextActions.map(action => <label className={`plan-action ${action.status}`} key={action.id}><input type="checkbox" disabled={action.status !== 'proposed'} checked={selectedActions.has(action.id)} onChange={() => toggleAction(action.id)} /><span><strong>{action.description}</strong><small>{action.action_type.replace('_', ' ')} · {{proposed: 'Diusulkan', approved: 'Disetujui', dismissed: 'Ditolak'}[action.status] || action.status}</small></span></label>)}</div> : <p className="empty-plan">Tidak ada proposal eksekusi yang valid dari data tersedia.</p>}{proposed.length > 0 && <footer><button onClick={() => setSelectedActions(new Set(proposed.map(item => item.id)))} disabled={pending}>Pilih Semua</button><button onClick={() => decide(dismissReport)} disabled={pending || !selectedActions.size}><X size={14} /> Dismiss Semua/Terpilih</button><button className="primary-button" onClick={() => decide(approveReport)} disabled={pending || !selectedActions.size}><Check size={14} /> Approve Semua/Terpilih</button></footer>}</article>
            </> : <div className="analyst-report-empty"><span><BarChart3 size={31} /></span><small>SIAP DIANALISIS</small><h2>Laporan pertama dimulai dari data Office.</h2><p>Pilih cakupan di atas, lalu jalankan analisis untuk melihat temuan dan rencana yang bisa kamu tinjau.</p><button type="button" onClick={generate} disabled={pending || (scope === 'brand' && !brandId)}><Play size={15} />{pending ? 'Generating…' : 'Run analysis'}</button></div>}</section>
        </div>
    </div>;
}
