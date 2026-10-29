import React, { useEffect, useMemo, useState } from 'react';
import { BarChart3, Check, ClipboardCheck, Clock3, FileClock, Lightbulb, Play, Sparkles, X } from 'lucide-react';
import { ResearchCharts } from './ResearchCharts';
import { AnalystCharts } from './AnalystCharts';
import './analyst-daily-report.css';

const HISTORY_PAGE = 11;
const scopeLabels = {
    keduanya: 'Keduanya: luar + dalam',
    luar: 'Hasil luar: riset web',
    dalam: 'Hasil dalam: performa kita',
};
const FORMATS = [['feed', 'Feed'], ['story', 'Story'], ['reels', 'Reels'], ['article', 'Artikel'], ['tiktok', 'TikTok']];
const BRANDS = [['jauki', 'Jauki'], ['kauiz', 'Kauiz']];
const SOURCE_LABELS = { luar: 'Luar', dalam: 'Dalam' };

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';

function formatTime(value) {
    return value ? new Date(value).toLocaleString('id-ID', { dateStyle: 'medium', timeStyle: 'short' }) : '—';
}

function statusLabel(value) {
    return { ready: 'Perlu ditinjau', reviewed: 'Reviewed', draft: 'Draft', generating: 'Sedang dibuat' }[value] || value;
}

function formatMessage(result) {
    if (result.status === 'generated') return 'Masuk antrean generate.';
    if (result.status === 'proposed') return 'Masuk rencana. Tinjau dan setujui di Rencana Konten.';
    if (result.status === 'skipped') return `Dilewati: ${result.reason || 'alasan tidak diketahui'}.`;
    return `Gagal: ${result.reason || result.status}.`;
}

function topicHint(scope) {
    if (scope === 'luar') return 'Riset web belum menghasilkan topik. Jalankan analisis lagi setelah riset selesai.';
    if (scope === 'dalam') return 'Belum ada data performa. Hubungkan Instagram Insights untuk melihat topik dari views dan likes.';
    return 'Belum ada topik dari riset web maupun performa kita.';
}

function matchesQuery(report, query) {
    const text = [report.reportDate, report.reportType, statusLabel(report.status), report.summary, report.conclusion,
        ...(report.metadata?.topic_recommendations || []).map(item => item.topic)].filter(Boolean).join(' ').toLowerCase();
    return text.includes(query);
}

export function AnalystReports({ reports, brands, loading, generateReport, approveReport, dismissReport, hideReports }) {
    const [mode, setMode] = useState('laporan');
    const [selectedId, setSelectedId] = useState(null);
    const [scope, setScope] = useState('keduanya');
    const [planningHorizon, setPlanningHorizon] = useState('7_days');
    const [selectedActions, setSelectedActions] = useState(new Set());
    const [pending, setPending] = useState(false);
    const [error, setError] = useState(null);
    const [historyOpen, setHistoryOpen] = useState(true);
    const [historyQuery, setHistoryQuery] = useState('');
    const [historyLimit, setHistoryLimit] = useState(HISTORY_PAGE);
    const [topic, setTopic] = useState('');
    const [formatBusy, setFormatBusy] = useState('');
    const [formatNotice, setFormatNotice] = useState('');
    const selected = useMemo(() => reports.find(report => report.id === selectedId) || reports[0] || null, [reports, selectedId]);
    const recommendations = selected?.metadata?.topic_recommendations || [];
    const matchingReports = useMemo(() => {
        const query = historyQuery.trim().toLowerCase();
        return query ? reports.filter(report => matchesQuery(report, query)) : reports;
    }, [reports, historyQuery]);
    const visibleReports = matchingReports.slice(0, historyLimit);

    useEffect(() => { if (!selectedId && reports[0]) setSelectedId(reports[0].id); }, [reports, selectedId]);
    useEffect(() => { setSelectedActions(new Set()); }, [selected?.id]);
    useEffect(() => { setTopic(recommendations[0]?.topic || ''); setFormatNotice(''); }, [selected?.id]);

    const generate = async () => {
        setPending(true); setError(null);
        try {
            const report = await generateReport({ report_type: 'manual', scope, planning_horizon: planningHorizon });
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

    // Sembunyikan satu laporan atau semuanya. Datanya tetap ada di database.
    const hideOne = async (id) => {
        setError(null);
        try {
            await hideReports({ ids: [id] });
            if (selectedId === id) setSelectedId(null);
        } catch (caught) { setError(caught.message || 'Gagal menyembunyikan laporan.'); }
    };
    const hideAll = async () => {
        if (!reports.length || !window.confirm(`Sembunyikan ${reports.length} laporan dari daftar? Datanya tetap tersimpan.`)) return;
        setError(null);
        try {
            await hideReports({ all: true });
            setSelectedId(null);
            setHistoryLimit(HISTORY_PAGE);
        } catch (caught) { setError(caught.message || 'Gagal menyembunyikan laporan.'); }
    };

    // Tombol generate per format. Feed, Story, dan Artikel lewat generator; Reels dan TikTok jadi rencana.
    const generateFormat = async (brandSlug, format) => {
        if (!topic.trim()) { setFormatNotice('Isi topik dulu.'); return; }
        const key = `${brandSlug}:${format}`;
        setFormatBusy(key); setFormatNotice('');
        try {
            const response = await fetch('/office/api/analyst-reports/generate-format', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify({ brand_slug: brandSlug, format, topic: topic.trim() }),
            });
            const json = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(json.message || 'Gagal membuat konten.');
            setFormatNotice(`${BRANDS.find(([slug]) => slug === brandSlug)[1]} · ${FORMATS.find(([value]) => value === format)[1]}: ${formatMessage(json)}`);
        } catch (caught) {
            setFormatNotice(caught.message || 'Gagal membuat konten.');
        } finally {
            setFormatBusy('');
        }
    };

    const contentPlans = selected?.contentPlans || [];
    const planProposed = contentPlans.filter(plan => plan.status === 'proposed');
    const legacyProposed = selected?.nextActions?.filter(action => action.status === 'proposed') || [];
    const proposed = planProposed.length ? planProposed : legacyProposed;

    return <div className="analyst-view">
        <section className="analyst-hero">
            <div className="analyst-intro"><span className="eyebrow"><Sparkles size={14} /> INTERNAL OFFICE ANALYST</span><div className="analyst-title-line"><div><h1>Daily report &amp; planning</h1><p>Fakta Office dan rencana yang menunggu persetujuan.</p></div><div className="analyst-intro-meta"><span><FileClock size={14} /> {reports.length} Reports</span><span><ClipboardCheck size={14} /> Approval required</span></div></div></div>
            <div className="analyst-runner">
                <div className="analyst-mode" role="group" aria-label="Tampilan Daily Report">
                    <button type="button" className={mode === 'laporan' ? 'active' : ''} aria-pressed={mode === 'laporan'} onClick={() => setMode('laporan')}>Laporan</button>
                    <button type="button" className={mode === 'chart' ? 'active' : ''} aria-pressed={mode === 'chart'} onClick={() => setMode('chart')}>Chart</button>
                </div>
                <div className="analyst-runner-fields">
                    <label>Scope<select value={scope} onChange={event => setScope(event.target.value)}>{Object.entries(scopeLabels).map(([value, label]) => <option value={value} key={value}>{label}</option>)}</select></label>
                    <label>Plan period<select value={planningHorizon} onChange={event => setPlanningHorizon(event.target.value)}><option value="only_analysis">Hanya Analisis</option><option value="7_days">7 Hari</option><option value="14_days">14 Hari</option><option value="30_days">30 Hari / Bulanan</option></select></label>
                </div>
                <button className="primary-button" onClick={generate} disabled={pending}><Play size={15} />{pending ? 'Generating…' : 'Generate'}</button>
            </div>
        </section>
        {error && <p className="analyst-error">{error}</p>}

        {mode === 'chart' ? <>
            <AnalystCharts />
            <ResearchCharts refreshKey={reports.length} />
        </> : <div className="analyst-stack">
            <section className="analyst-history-section" aria-label="Riwayat laporan">
                <header className="history-head">
                    <div><span className="eyebrow"><FileClock size={13} /> RIWAYAT LAPORAN</span><h3>{reports.length} laporan</h3></div>
                    <div className="history-actions">
                        <button type="button" onClick={() => setHistoryOpen(value => !value)}>{historyOpen ? 'Lipat' : 'Buka'}</button>
                        <button type="button" className="is-danger" onClick={hideAll} disabled={!reports.length}>Sembunyikan semua</button>
                    </div>
                </header>
                {historyOpen && <>
                    <input className="history-search" type="search" placeholder="Cari tanggal, topik, atau isi laporan" value={historyQuery} onChange={event => { setHistoryQuery(event.target.value); setHistoryLimit(HISTORY_PAGE); }} />
                    {visibleReports.length ? <div className="history-card-grid">{visibleReports.map(report => <article key={report.id} className={`history-card ${selected?.id === report.id ? 'active' : ''}`}>
                        <button type="button" className="history-card-main" onClick={() => setSelectedId(report.id)}>
                            <span className="history-card-top"><b>{report.reportDate}</b><em className={`report-status ${report.status}`}>{statusLabel(report.status)}</em></span>
                            <strong>{report.reportType} brief</strong>
                            <small>{report.summary}</small>
                        </button>
                        <button type="button" className="history-card-hide" onClick={() => hideOne(report.id)}>Sembunyikan</button>
                    </article>)}</div> : <p className="history-empty">{reports.length ? 'Tidak ada laporan yang cocok.' : 'Belum ada laporan. Analisis pertama akan muncul di sini.'}</p>}
                    {matchingReports.length > visibleReports.length && <button type="button" className="history-more" onClick={() => setHistoryLimit(value => value + HISTORY_PAGE)}>Selengkapnya ({matchingReports.length - visibleReports.length} lagi)</button>}
                </>}
            </section>

            <section className="analyst-report">{loading && !selected ? <p>Loading analyst reports…</p> : selected ? <><header><div><span className="eyebrow"><ClipboardCheck size={13} /> {selected.reportType} ANALYSIS</span><h2>{selected.reportDate} · {statusLabel(selected.status)}</h2><time><Clock3 size={13} /> Dibuat {formatTime(selected.generatedAt)}</time></div><span className={`report-source ${selected.metadata?.analysis_mode === 'fallback_internal' ? 'fallback' : ''}`}>{selected.metadata?.analysis_mode === 'ai' && selected.metadata?.source === 'agent_reach' ? `Agent-Reach Research + AI Analysis` : 'Analisis internal — riset eksternal tidak tersedia'} · {selected.metadata?.performance_available ? 'Performance available' : 'Data performa belum tersedia'} · Scope: {scopeLabels[selected.metadata?.scope] || 'Keduanya: luar + dalam'}</span>{selected.metadata?.analysis_error && <p className="analysis-error-note">AI gagal: {selected.metadata.analysis_error}</p>}</header>
                <article className="analyst-summary"><h3>Ringkasan Eksekutif</h3><p>{selected.summary}</p></article>
                <div className="analyst-sections"><article><h3>Temuan</h3><div className="topic-reco"><h4>Topik yang direkomendasikan</h4>{recommendations.length ? <ol>{recommendations.map(item => <li key={`${item.source}-${item.topic}`}><span className={`source-tag ${item.source}`}>{SOURCE_LABELS[item.source] || item.source}</span><strong>{item.topic}</strong><small>{item.reason}</small></li>)}</ol> : <p className="topic-reco-empty">{topicHint(selected.metadata?.scope)}</p>}</div><div className="finding-list">{selected.findings?.map((finding, index) => <div className={`finding finding-${finding.type}`} key={`${finding.title}-${index}`}><strong>{finding.title}</strong><p>{finding.description}</p><small>Evidence: {finding.evidence}</small></div>)}</div></article><article><h3>Kesimpulan</h3><p className="analyst-conclusion">{selected.conclusion}</p><h3>Rekomendasi</h3><ol className="recommendation-list">{selected.recommendations?.map((recommendation, index) => <li key={`${recommendation.action}-${index}`}><span className={recommendation.priority}>{{high: 'Tinggi', medium: 'Sedang', low: 'Rendah'}[recommendation.priority] || recommendation.priority}</span><strong>{recommendation.action}</strong><p>{recommendation.reason}</p></li>)}</ol></article></div>
                <article className="forward-plan"><header><div><span className="eyebrow"><Lightbulb size={13} /> MEMERLUKAN PERSETUJUAN OWNER</span><h3>Rencana Konten · {selected.metadata?.planning_horizon === '30_days' ? 'Bulanan' : (selected.metadata?.planning_horizon || '7 Hari').replace('_days', ' Hari')}</h3><p>{selected.metadata?.source === 'agent_reach' ? `Berdasarkan Agent-Reach · ${(selected.metadata?.source_count || selected.metadata?.sources_count || 0)} sumber` : 'Analisis AI · Data Internal'}</p></div>{proposed.length > 0 && <span>{selectedActions.size}/{proposed.length} dipilih</span>}</header>{contentPlans.length ? <div className="action-list">{contentPlans.map(plan => <label className={`plan-action ${plan.status}`} key={plan.id}><input type="checkbox" disabled={plan.status !== 'proposed'} checked={selectedActions.has(plan.id)} onChange={() => toggleAction(plan.id)} /><span><strong>{formatTime(plan.scheduledAt)} · {plan.brand?.name} · {plan.platform} {plan.contentType}</strong><b>{plan.topic}</b><small>Objective: {plan.metadata?.objective || '-'} · Angle: {plan.metadata?.angle || '-'} · Evidence: {(plan.metadata?.source_evidence || []).length} sumber</small><small>Alasan: {plan.reason}</small><em className={`report-status ${plan.status}`}>{plan.status.toUpperCase()}</em></span></label>)}</div> : selected.nextActions?.length ? <div className="action-list">{selected.nextActions.map(action => <label className={`plan-action ${action.status}`} key={action.id}><input type="checkbox" disabled={action.status !== 'proposed'} checked={selectedActions.has(action.id)} onChange={() => toggleAction(action.id)} /><span><strong>{action.description}</strong><small>{action.action_type.replace('_', ' ')} · {{proposed: 'Diusulkan', approved: 'Disetujui', dismissed: 'Ditolak'}[action.status] || action.status}</small></span></label>)}</div> : <p className="empty-plan">Tidak ada proposal eksekusi yang valid dari data tersedia.</p>}{proposed.length > 0 && <footer><button onClick={() => setSelectedActions(new Set(proposed.map(item => item.id)))} disabled={pending}>Pilih Semua</button><button onClick={() => decide(dismissReport)} disabled={pending || !selectedActions.size}><X size={14} /> Dismiss Semua/Terpilih</button><button className="primary-button" onClick={() => decide(approveReport)} disabled={pending || !selectedActions.size}><Check size={14} /> Setujui Semua/Terpilih</button></footer>}</article>
                <article className="format-generate"><header><span className="eyebrow"><Sparkles size={13} /> GENERATE</span><h3>Buat konten dari topik</h3><p>Pilih topik, lalu klik format untuk brand yang dituju.</p></header><label className="format-topic">Topik<input list="analyst-topic-options" value={topic} onChange={event => setTopic(event.target.value)} placeholder="Tulis topik atau pilih rekomendasi" /></label><datalist id="analyst-topic-options">{recommendations.map(item => <option value={item.topic} key={`${item.source}-${item.topic}`} />)}</datalist><div className="format-grid">{BRANDS.map(([slug, name]) => <div className="format-row" key={slug}><b>{name}</b>{FORMATS.map(([format, label]) => { const key = `${slug}:${format}`; return <button type="button" key={key} disabled={Boolean(formatBusy) || !topic.trim()} onClick={() => generateFormat(slug, format)}>{formatBusy === key ? '…' : label}</button>; })}</div>)}</div>{formatNotice && <p className="format-notice" role="status">{formatNotice}</p>}</article>
                </> : <div className="analyst-report-empty"><span><BarChart3 size={31} /></span><small>SIAP DIANALISIS</small><h2>Laporan pertama dimulai dari data Office.</h2><p>Pilih scope di atas, lalu jalankan analisis untuk melihat temuan dan rencana yang bisa kamu tinjau.</p><button type="button" onClick={generate} disabled={pending}><Play size={15} />{pending ? 'Generating…' : 'Generate'}</button></div>}</section>
        </div>}
    </div>;
}
