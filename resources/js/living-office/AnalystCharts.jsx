import React, { useEffect, useState } from 'react';
import { ColumnChart, shorten } from './ResearchCharts';

const POLL_MS = 30000;

function dayLabel(value) {
    return new Date(`${value}T12:00:00`).toLocaleDateString('id-ID', { day: 'numeric', month: 'short' });
}

// Sumbu X tidak perlu semua tanggal: setiap dua hari, dan hari terakhir selalu tampil.
function axisLabel(index, total, date) {
    return index % 2 === 0 || index === total - 1 ? dayLabel(date) : '';
}

function dailyColumns(days) {
    return days.map((day, index) => ({
        key: day.date,
        total: day.total,
        segments: [{ value: day.total, className: 'rc-bar-topic' }],
        label: axisLabel(index, days.length, day.date),
        title: `${dayLabel(day.date)}: ${day.total} konten`,
    }));
}

function socialColumns(days) {
    return days.map((day, index) => ({
        key: day.date,
        total: day.views,
        segments: [{ value: day.views, className: 'rc-bar-completed' }],
        label: axisLabel(index, days.length, day.date),
        title: `${dayLabel(day.date)}: ${day.views} views, ${day.likes} likes`,
    }));
}

export function AnalystCharts() {
    const [data, setData] = useState(null);
    const [error, setError] = useState('');

    useEffect(() => {
        let active = true;
        const load = async () => {
            try {
                const response = await fetch('/office/api/analyst-reports/charts', { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                const body = await response.json();
                if (active) { setData(body.data); setError(''); }
            } catch {
                if (active) setError('Chart belum bisa dimuat. Akan dicoba lagi otomatis.');
            }
        };
        load();
        const timer = setInterval(load, POLL_MS);
        return () => { active = false; clearInterval(timer); };
    }, []);

    if (!data) return <section className="rc-panel rc-loading" aria-live="polite">{error || 'Memuat chart…'}</section>;

    const { topics, topTopic, contentPerDay, topicMetrics, social, metricsConfigured, lastFetchedAt } = data;
    const topicColumnsData = topicMetrics.map((topic) => ({
        key: topic.topic,
        total: topic.views,
        segments: [{ value: topic.views, className: 'rc-bar-topic' }],
        label: shorten(topic.topic),
        title: `${topic.topic}: ${topic.views} views, ${topic.likes} likes dari ${topic.posts} konten`,
    }));

    return <section className="rc-panel" aria-label="Chart Daily Report">
        <header className="rc-header">
            <div>
                <span className="eyebrow">CHART DAILY REPORT</span>
                <h2>Konten, topik, dan performa</h2>
            </div>
            <div className="rc-live"><i /> {lastFetchedAt ? `Views terakhir: ${new Date(lastFetchedAt).toLocaleString('id-ID')}` : 'Views belum diambil'}</div>
        </header>
        {!metricsConfigured && <p className="rc-error" role="status">Views dan likes belum tersedia. Hubungkan Instagram Insights (INSTAGRAM_ACCESS_TOKEN) untuk mengisi angka di bawah.</p>}
        {error && <p className="rc-error" role="status">{error}</p>}

        <div className="rc-grid">
            <article className="rc-card">
                <header>
                    <div><strong>Semua topik yang pernah di-generate</strong><small>{topics.length} topik</small></div>
                </header>
                {topics.length ? <ul className="analyst-topic-list">{topics.map((item) => <li key={item.topic}><span>{item.topic}</span><b>{item.count}×</b></li>)}</ul> : <p className="rc-muted">Belum ada topik yang pernah di-generate.</p>}
            </article>
            <article className="rc-card">
                <header>
                    <div><strong>Topik teratas</strong><small>berdasarkan views</small></div>
                </header>
                {topTopic ? <div className="analyst-top-topic"><b>{topTopic.topic}</b><span>{topTopic.views !== undefined ? `${topTopic.views} views · ${topTopic.likes} likes` : `${topTopic.count}× dibuat`}</span></div> : <p className="rc-muted">Belum ada topik.</p>}
            </article>
            <article className="rc-card">
                <header>
                    <div><strong>Konten per hari</strong><small>14 hari terakhir</small></div>
                    <div className="rc-stat"><b>{contentPerDay.reduce((sum, day) => sum + day.total, 0)}</b><small>konten</small></div>
                </header>
                <ColumnChart columns={dailyColumns(contentPerDay)} ariaLabel="Konten per hari, 14 hari terakhir" />
            </article>
            <article className="rc-card">
                <header>
                    <div><strong>Total views per topik</strong><small>semua konten yang sudah diambil</small></div>
                </header>
                {topicMetrics.length ? <>
                    <ColumnChart columns={topicColumnsData} ariaLabel="Total views per topik" />
                    <ul className="analyst-topic-list">{topicMetrics.map((item) => <li key={item.topic}><span>{item.topic}</span><b>{item.views} views · {item.likes} likes</b></li>)}</ul>
                </> : <p className="rc-muted">Belum ada data performa per topik.</p>}
            </article>
            <article className="rc-card">
                <header>
                    <div><strong>Sosial media Kauiz</strong><small>views per hari, 14 hari terakhir</small></div>
                    <div className="rc-stat"><b>{social.kauiz.reduce((sum, day) => sum + day.likes, 0)}</b><small>likes</small></div>
                </header>
                <ColumnChart columns={socialColumns(social.kauiz)} ariaLabel="Views Kauiz per hari" />
            </article>
            <article className="rc-card">
                <header>
                    <div><strong>Sosial media Jauki</strong><small>views per hari, 14 hari terakhir</small></div>
                    <div className="rc-stat"><b>{social.jauki.reduce((sum, day) => sum + day.likes, 0)}</b><small>likes</small></div>
                </header>
                <ColumnChart columns={socialColumns(social.jauki)} ariaLabel="Views Jauki per hari" />
            </article>
        </div>
    </section>;
}
