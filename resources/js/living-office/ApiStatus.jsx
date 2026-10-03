import React from 'react';
import { ArrowUpRight, AtSign, CalendarDays, FileText, Instagram, PlugZap, Search, ShieldAlert, Sparkles } from 'lucide-react';

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
    const ready = channels.filter(channel => { const agent = byId[channel.id]; return agent && !unavailable.has(agent.status) && channel.outputs.some(item => agent.availableActions?.includes(item.action)); }).length;
    return <div className="content-view api-status-view">
        <div className="view-heading api-status-heading"><div><span>OFFICE CONNECTIONS</span><h1>API Aktif</h1><p>Lihat worker yang tersedia dan jenis konten yang dapat dibuat dari Office.</p></div><div className="api-status-count"><PlugZap size={20} /><strong>{ready}/{channels.length}</strong><small>worker konten tersedia</small></div></div>
        <div className="api-status-note"><ShieldAlert size={19} /><p><strong>Status worker berasal dari Office.</strong> Koneksi akun dan izin publikasi platform belum diverifikasi oleh data ini. Hasil generasi dan publikasi memiliki alur terpisah.</p></div>
        <div className="api-channel-grid">{channels.map(channel => {
            const agent = byId[channel.id];
            const Icon = channel.icon;
            const connected = agent && !unavailable.has(agent.status);
            const outputs = channel.outputs.filter(output => agent?.availableActions?.includes(output.action));
            return <article className={`api-channel-card tone-${channel.tone}`} key={channel.id}>
                <div className="api-channel-top"><span className="api-channel-icon"><Icon size={25} /></span><span className={`roster-state ${connected ? 'is-online' : 'is-offline'}`}><i />{connected ? 'Worker tersedia' : 'Worker tidak tersedia'}</span></div>
                <small>{channel.label}</small><h2>{channel.name}</h2><p>{agent?.name || 'Worker belum terdaftar'} · {agent?.role || 'Integrasi belum tersedia'}</p>
                <div className="api-channel-capabilities"><span>YANG BISA DIBUAT</span>{outputs.length ? outputs.map(output => <div key={output.action}><Sparkles size={15} />{output.label}</div>) : <div>Belum ada aksi generasi yang tersedia.</div>}</div>
                <button type="button" disabled={!connected} onClick={() => onSelectWorker(channel.id)}>Buka worker <ArrowUpRight size={16} /></button>
            </article>;
        })}</div>
        <section className="api-support-section"><div><span>DI BALIK KONTEN</span><h2>Worker pendukung</h2><p>Riset dan perencanaan membantu alur pembuatan konten.</p></div><div className="api-support-grid">{supportWorkers.map(item => {
            const agent = byId[item.id]; const Icon = item.icon; const connected = agent && !unavailable.has(agent.status);
            return <button type="button" key={item.id} className="api-support-card" disabled={!connected} onClick={() => onSelectWorker(item.id)}><span><Icon size={20} /></span><div><strong>{item.name}</strong><p>{item.description}</p><small>{connected ? `Status: ${agent.status.replaceAll('_', ' ')}` : 'Belum tersedia'}</small></div><ArrowUpRight size={18} /></button>;
        })}</div></section>
    </div>;
}
