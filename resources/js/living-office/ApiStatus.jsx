import React from 'react';
import { ArrowUpRight, AtSign, CalendarDays, FileText, Instagram, PlugZap, Search, ShieldAlert, Sparkles } from 'lucide-react';
import { channelReadiness } from './connectionReadiness';

const unavailable = new Set(['offline', 'not_connected', 'not_installed']);
const channels = [
    { id: 'jauki-social', name: 'Instagram', label: 'Social content', icon: Instagram, tone: 'violet', outputs: [{ action: 'generate_feed', label: 'Feed & caption' }, { action: 'generate_story', label: 'Story' }] },
    { id: 'jauki-article', name: 'Artikel Web', label: 'Editorial', icon: FileText, tone: 'cyan', outputs: [{ action: 'generate_article', label: 'Draft artikel & SEO' }] },
    { id: 'jauki-threads', name: 'Threads', label: 'Community', icon: AtSign, tone: 'pink', outputs: [{ action: 'generate_threads', label: 'Post Threads' }] },
];
const supportWorkers = [
    { id: 'jauki-analyst', name: 'Analyst', icon: Search, description: 'Riset tren, topik, sumber, dan ide konten.' },
    { id: 'jauki-planner', name: 'Planner', icon: CalendarDays, description: 'Tema mingguan dan rencana konten.' },
];

export function ApiStatus({ agents, onSelectWorker }) {
    const byId = Object.fromEntries(agents.map(agent => [agent.id, agent]));
    const ready = channels.filter(channel => channelReadiness(byId[channel.id]).worker === 'READY').length;
    return <div className="content-view api-status-view">
        <div className="view-heading api-status-heading"><div><span>OFFICE CONNECTIONS</span><h1>API Aktif</h1><p>Status worker dan kemampuan yang tersedia. Kesehatan koneksi platform ditampilkan terpisah.</p></div><div className="api-status-count"><PlugZap size={20} /><strong>{ready}/{channels.length}</strong><small>worker ready</small></div></div>
        <div className="api-status-note"><ShieldAlert size={19} /><p><strong>Accounts &amp; publishing: NOT VERIFIED.</strong> Office belum menerima health status koneksi platform. Daftar aksi worker tidak membuktikan akun atau izin publikasi aktif.</p></div>
        <div className="api-readiness-summary"><span>Workers <b>{ready}/{channels.length} READY</b></span><span>Accounts <b>NOT VERIFIED</b></span><span>Publishing <b>NOT VERIFIED</b></span></div>
        <div className="api-channel-grid">{channels.map(channel => {
            const agent = byId[channel.id];
            const Icon = channel.icon;
            const readiness = channelReadiness(agent,channel.outputs.map(output=>output.action));
            const connected = agent && !unavailable.has(agent.status);
            const outputs = channel.outputs.filter(output => agent?.availableActions?.includes(output.action));
            return <article className={`api-channel-card tone-${channel.tone}`} key={channel.id}>
                <div className="api-channel-top"><span className="api-channel-icon"><Icon size={22} /></span><span className={`readiness-tag ${readiness.worker.toLowerCase().replaceAll(' ','-')}`}>Worker {readiness.worker}</span></div>
                <small>{channel.label}</small><h2>{channel.name}</h2><p>{agent?.name || 'Worker belum terdaftar'} · {agent?.role || 'Integrasi belum tersedia'}</p>
                <dl className="api-readiness-rows"><div><dt>Worker</dt><dd>{readiness.worker}</dd></div><div><dt>Generator</dt><dd>{readiness.generator}</dd></div><div><dt>Account connection</dt><dd>{readiness.account}</dd></div><div><dt>Publishing</dt><dd>{readiness.publishing}</dd></div></dl>
                <div className="api-channel-capabilities"><span>AKSI YANG TERSEDIA</span>{outputs.length ? outputs.map(output => <div key={output.action}><Sparkles size={15} />{output.label}</div>) : <div>Belum ada aksi generasi yang tersedia.</div>}</div>
                <button type="button" disabled={!connected} onClick={() => onSelectWorker(channel.id)}>Buka worker <ArrowUpRight size={16} /></button>
            </article>;
        })}</div>
        <section className="api-support-section"><div><span>DI BALIK KONTEN</span><h2>Worker pendukung</h2><p>Riset dan perencanaan membantu alur pembuatan konten.</p></div><div className="api-support-grid">{supportWorkers.map(item => {
            const agent = byId[item.id]; const Icon = item.icon; const connected = agent && !unavailable.has(agent.status);
            return <button type="button" key={item.id} className="api-support-card" disabled={!connected} onClick={() => onSelectWorker(item.id)}><span><Icon size={20} /></span><div><strong>{item.name}</strong><p>{item.description}</p><small>{connected ? `Status: ${agent.status.replaceAll('_', ' ')}` : 'Belum tersedia'}</small></div><ArrowUpRight size={18} /></button>;
        })}</div></section>
    </div>;
}
