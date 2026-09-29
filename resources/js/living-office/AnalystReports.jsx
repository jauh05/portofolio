import React, { useEffect, useMemo, useState } from 'react';
import { Check, ChevronRight, ClipboardCheck, Clock3, Lightbulb, Play, Sparkles, X } from 'lucide-react';
import './analyst-reports.css';

const scopeLabels = {
    today: 'Today', last_7_days: 'Last 7 days', content_planner: 'Content planner', worker_performance: 'Worker activity', brand: 'Brand workspace',
};

function formatTime(value) {
    return value ? new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : '—';
}

function statusLabel(value) {
    return { ready: 'Needs review', reviewed: 'Reviewed', draft: 'Draft' }[value] || value;
}

export function AnalystReports({ reports, brands, loading, generateReport, approveReport, dismissReport }) {
    const [selectedId, setSelectedId] = useState(null);
    const [scope, setScope] = useState('today');
    const [brandId, setBrandId] = useState('');
    const [selectedActions, setSelectedActions] = useState(new Set());
    const [pending, setPending] = useState(false);
    const [error, setError] = useState(null);
    const selected = useMemo(() => reports.find(report => report.id === selectedId) || reports[0] || null, [reports, selectedId]);

    useEffect(() => { if (!selectedId && reports[0]) setSelectedId(reports[0].id); }, [reports, selectedId]);
    useEffect(() => { setSelectedActions(new Set()); }, [selected?.id]);

    const generate = async () => {
        setPending(true); setError(null);
        try {
            const report = await generateReport({ report_type: 'manual', scope, ...(scope === 'brand' && brandId ? { brand_id: brandId } : {}) });
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
    const proposed = selected?.nextActions?.filter(action => action.status === 'proposed') || [];

    return <div className="analyst-view">
        <section className="analyst-hero">
            <div><span className="eyebrow"><Sparkles size={13} /> INTERNAL OFFICE ANALYST</span><h1>Daily report &amp; forward planning</h1><p>Facts are derived from Office records. Proposed work stays pending until owner approval.</p></div>
            <div className="analyst-runner"><label>Manual report scope<select value={scope} onChange={event => setScope(event.target.value)}>{Object.entries(scopeLabels).map(([value, label]) => <option value={value} key={value}>{label}</option>)}</select></label>{scope === 'brand' && <label>Workspace<select value={brandId} onChange={event => setBrandId(event.target.value)}><option value="">All brands</option>{brands.map(brand => <option key={brand.id} value={brand.id}>{brand.name}</option>)}</select></label>}<button className="primary-button" onClick={generate} disabled={pending || (scope === 'brand' && !brandId)}><Play size={14} />{pending ? 'Generating…' : 'Run analysis'}</button></div>
        </section>
        {error && <p className="analyst-error">{error}</p>}
        <div className="analyst-layout">
            <aside className="analyst-history"><div className="panel-label">REPORT HISTORY</div>{reports.length ? reports.map(report => <button className={selected?.id === report.id ? 'active' : ''} key={report.id} onClick={() => setSelectedId(report.id)}><span><strong>{report.reportType} brief</strong><small>{report.reportDate}</small></span><em className={`report-status ${report.status}`}>{statusLabel(report.status)}</em><ChevronRight size={14} /></button>) : <p>No reports yet. Run an internal analysis to create the first brief.</p>}</aside>
            <section className="analyst-report">{loading && !selected ? <p>Loading analyst reports…</p> : selected ? <><header><div><span className="eyebrow"><ClipboardCheck size={13} /> {selected.reportType} ANALYSIS</span><h2>{selected.reportDate} · {statusLabel(selected.status)}</h2><time><Clock3 size={13} /> Generated {formatTime(selected.generatedAt)}</time></div><span className="report-source">{selected.metadata?.performance_available ? 'Performance data available' : 'Performance data unavailable'}</span></header>
                <article className="analyst-summary"><h3>Executive summary</h3><p>{selected.summary}</p></article>
                <div className="analyst-sections"><article><h3>Findings</h3><div className="finding-list">{selected.findings?.map((finding, index) => <div className={`finding finding-${finding.type}`} key={`${finding.title}-${index}`}><strong>{finding.title}</strong><p>{finding.description}</p><small>Evidence: {finding.evidence}</small></div>)}</div></article><article><h3>Conclusion</h3><p className="analyst-conclusion">{selected.conclusion}</p><h3>Recommendations</h3><ol className="recommendation-list">{selected.recommendations?.map((recommendation, index) => <li key={`${recommendation.action}-${index}`}><span className={recommendation.priority}>{recommendation.priority}</span><strong>{recommendation.action}</strong><p>{recommendation.reason}</p></li>)}</ol></article></div>
                <article className="forward-plan"><header><div><span className="eyebrow"><Lightbulb size={13} /> OWNER APPROVAL REQUIRED</span><h3>Next action plan</h3></div>{proposed.length > 0 && <span>{selectedActions.size}/{proposed.length} selected</span>}</header>{selected.nextActions?.length ? <div className="action-list">{selected.nextActions.map(action => <label className={`plan-action ${action.status}`} key={action.id}><input type="checkbox" disabled={action.status !== 'proposed'} checked={selectedActions.has(action.id)} onChange={() => toggleAction(action.id)} /><span><strong>{action.description}</strong><small>{action.action_type.replace('_', ' ')} · {action.status}</small></span></label>)}</div> : <p className="empty-plan">No execution proposal was derived from the available records. Continue monitoring or run a scoped report.</p>}{proposed.length > 0 && <footer><button onClick={() => decide(dismissReport)} disabled={pending || !selectedActions.size}><X size={14} /> Dismiss selected</button><button className="primary-button" onClick={() => decide(approveReport)} disabled={pending || !selectedActions.size}><Check size={14} /> Approve selected</button></footer>}</article>
            </> : <p>No analyst report is available.</p>}</section>
        </div>
    </div>;
}
