import React, { useEffect, useState } from 'react';

const POLL_MS = 5000;
const CHART = { width: 560, height: 236, left: 34, right: 8, top: 26, bottom: 34 };

function dayLabel(value) {
    return new Date(`${value}T12:00:00`).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
}

function timeLabel(value) {
    return value ? new Date(value).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) : '—';
}

function shorten(text, length = 12) {
    return text.length > length ? `${text.slice(0, length - 1)}…` : text;
}

/* One chart style for both cards: vertical stacked columns, dashed guides, value labels, highlighted maximum. */
function ColumnChart({ columns, ariaLabel }) {
    const { width, height, left, right, top, bottom } = CHART;
    const plotWidth = width - left - right;
    const plotHeight = height - top - bottom;
    const max = Math.max(1, ...columns.map(column => column.total));
    const gap = 12;
    const barWidth = Math.max(12, (plotWidth - gap * (columns.length - 1)) / columns.length);
    const scale = value => (value / max) * plotHeight;
    const baseline = top + plotHeight;
    const ticks = [...new Set([0, Math.ceil(max / 2), max])];
    const highlightIndex = columns.reduce((best, column, index) => (column.total > columns[best].total ? index : best), 0);

    return <svg className="rc-chart" viewBox={`0 0 ${width} ${height}`} role="img" aria-label={ariaLabel}>
        {ticks.map(tick => {
            const y = baseline - scale(tick);
            return <g key={`tick-${tick}`}>
                <line x1={left} x2={width - right} y1={y} y2={y} className="rc-grid-line" />
                <text x={left - 8} y={y + 4} textAnchor="end" className="rc-tick">{tick}</text>
            </g>;
        })}
        {columns.map((column, index) => {
            const x = left + index * (barWidth + gap);
            let cursor = baseline;
            return <g key={column.key} className={index === highlightIndex && column.total > 0 ? 'is-highlight' : ''}>
                {column.total === 0 && <rect x={x} y={baseline - 3} width={barWidth} height={3} rx="2" className="rc-bar-empty" />}
                {column.total > 0 && column.segments.map(segment => {
                    const segmentHeight = scale(segment.value);
                    cursor -= segmentHeight;
                    return segment.value > 0 && <rect key={segment.className} x={x} y={cursor} width={barWidth} height={segmentHeight} rx={segmentHeight > 6 ? 6 : 0} className={segment.className} />;
                })}
                {column.total > 0 && <text x={x + barWidth / 2} y={baseline - scale(column.total) - 7} textAnchor="middle" className="rc-value">{column.total}</text>}
                <text x={x + barWidth / 2} y={height - 10} textAnchor="middle" className="rc-axis"><title>{column.title}</title>{column.label}</text>
            </g>;
        })}
    </svg>;
}

export function ResearchCharts({ refreshKey }) {
    const [stats, setStats] = useState(null);
    const [error, setError] = useState('');
    const [updatedAt, setUpdatedAt] = useState(null);

    useEffect(() => {
        let active = true;
        const load = async () => {
            try {
                const response = await fetch('/office/api/research-stats', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                const body = await response.json();
                if (active) { setStats(body.data); setUpdatedAt(new Date()); setError(''); }
            } catch (failure) {
                if (active) setError('Grafik riset belum bisa dimuat. Akan dicoba lagi otomatis.');
            }
        };
        load();
        const timer = setInterval(load, POLL_MS);
        return () => { active = false; clearInterval(timer); };
    }, [refreshKey]);

    if (!stats) return <section className="rc-panel rc-loading" aria-live="polite">{error || 'Memuat grafik riset…'}</section>;

    const { totals, series, topics } = stats;
    const dailyColumns = series.map((day, index) => ({
        key: day.date,
        total: day.total,
        segments: [
            { value: day.completed, className: 'rc-bar-completed' },
            { value: day.running, className: 'rc-bar-running' },
            { value: day.failed, className: 'rc-bar-failed' },
        ],
        label: index % 2 === 0 || index === series.length - 1 ? dayLabel(day.date) : '',
        title: `${dayLabel(day.date)}: ${day.total} riset (${day.completed} selesai, ${day.running} berjalan, ${day.failed} gagal)`,
    }));
    const topicColumns = topics.map(topic => ({
        key: topic.topic,
        total: topic.sources,
        segments: [{ value: topic.sources, className: 'rc-bar-topic' }],
        label: shorten(topic.topic),
        title: `${topic.topic}: ${topic.sources} sumber dari ${topic.runs} riset`,
    }));
    const topicSources = topics.reduce((sum, topic) => sum + topic.sources, 0);

    return <section className="rc-panel" aria-label="Grafik riset analyst">
        <header className="rc-header">
            <div>
                <span className="eyebrow">RESEARCH ANALYTICS</span>
                <h2>Bahan riset Analyst</h2>
            </div>
            <div className="rc-live"><i /> Live · {updatedAt ? timeLabel(updatedAt) : '—'}</div>
        </header>
        {error && <p className="rc-error" role="status">{error}</p>}

        <div className="rc-grid">
            <article className="rc-card">
                <header>
                    <div><strong>Riset per hari</strong><small>14 hari terakhir</small></div>
                    <div className="rc-stat"><b>{totals.runs}</b><small>total riset</small></div>
                </header>
                {totals.runs === 0 ? <p className="rc-muted">Belum ada riset. Jalankan Run analysis untuk mengisi grafik.</p> : <ColumnChart columns={dailyColumns} ariaLabel="Riset per hari, 14 hari terakhir" />}
                <ul className="rc-legend-row">
                    <li><i className="rc-dot-completed" />Selesai <b>{totals.completed}</b></li>
                    <li><i className="rc-dot-running" />Berjalan <b>{totals.running}</b></li>
                    <li><i className="rc-dot-failed" />Gagal <b>{totals.failed}</b></li>
                </ul>
            </article>
            <article className="rc-card">
                <header>
                    <div><strong>Topik teratas</strong><small>jumlah sumber per topik</small></div>
                    <div className="rc-stat"><b>{topicSources}</b><small>sumber, 5 topik teratas</small></div>
                </header>
                {topics.length ? <ColumnChart columns={topicColumns} ariaLabel="Jumlah sumber per topik teratas" /> : <p className="rc-muted">Belum ada topik.</p>}
                <p className="rc-caption">Batang tertinggi disorot. Angka di atas batang adalah jumlah sumber.</p>
            </article>
        </div>
    </section>;
}
