import React, { useState } from 'react';
import { Activity, AlertTriangle, ArrowUpRight, Bot, CheckCircle2, Clock3, Inbox, PlayCircle, RefreshCw, Sparkles } from 'lucide-react';
import { PANEL_MODES, readPanelMode, writePanelMode } from './panelPreferences';

const when = (value) => value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '—';
const unavailable = new Set(['offline', 'not_connected', 'not_installed']);
const kanbanColumns = [['queued', 'Queued'], ['claimed', 'Claimed'], ['running', 'Running'], ['completed', 'Completed'], ['failed', 'Failed']];

function TaskCard({ task, name }) {
    const isRunning = task.status === 'running';
    const isFinished = ['completed', 'failed'].includes(task.status);
    return <article className={`kanban-card is-${task.status}`}>
        <header><span className={`status-pill ${task.status === 'failed' ? 'red' : task.status === 'completed' ? 'green' : task.status === 'running' ? 'blue' : 'amber'}`}>{task.status}</span><small>{task.type}</small></header>
        <h3>{task.title}</h3>
        <dl><div><dt>Worker</dt><dd>{name || task.agentId}</dd></div>{task.target && <div><dt>Target</dt><dd>{task.target}</dd></div>}<div><dt>Started</dt><dd>{when(task.startedAt)}</dd></div>{isFinished && <div><dt>{task.status === 'completed' ? 'Completed' : 'Failed'}</dt><dd>{when(task.finishedAt)}</dd></div>}</dl>
        {isRunning && <div className="kanban-progress"><span><b>Progress</b><b>{task.progress ?? 0}%</b></span><i><b style={{ width: `${task.progress ?? 0}%` }} /></i></div>}
        {task.error && <p className="kanban-error">{task.error}</p>}
    </article>;
}

function State({ icon: Icon = Inbox, title, detail, retry }) {
    return <div className="office-empty-state"><span><Icon size={25} /></span><strong>{title}</strong>{detail && <p>{detail}</p>}{retry && <button onClick={retry}><RefreshCw size={15} /> Muat ulang</button>}</div>;
}

function AgentCard({ agent, onSelect }) {
    const connected = !unavailable.has(agent.status);
    const progress = Math.max(0, Math.min(100, Number(agent.progress) || 0));
    return <button type="button" className="roster-card" onClick={() => onSelect?.(agent.id)} style={{ '--worker-color': agent.color }} aria-label={`Buka detail ${agent.name}`}>
        <div className="roster-card-top"><span className="roster-avatar">{agent.shortName}</span><span className={`roster-state ${connected ? 'is-online' : 'is-offline'}`}><i />{agent.status.replaceAll('_', ' ')}</span></div>
        <div className="roster-card-title"><strong>{agent.name}</strong><small>{agent.role}</small></div>
        <div className="roster-card-task"><span>SEDANG DIKERJAKAN</span><p>{agent.currentTask || (connected ? 'Menunggu tugas berikutnya' : 'Integrasi belum terhubung')}</p></div>
        <div className="roster-card-progress"><span>Progress <b>{progress}%</b></span><i><em style={{ width: `${progress}%` }} /></i></div>
        <div className="roster-card-bottom"><small>{agent.parentSystem}</small><span>Detail worker <ArrowUpRight size={15} /></span></div>
    </button>;
}

function ActivityCard({ item, name }) {
    const type = String(item.type || 'Office event');
    const status = typeof item.status === 'string' && item.status ? item.status : null;
    const failed = /fail|error|warning/i.test(status || type);
    const completed = /complet|publish|approv|generated/i.test(status || type);
    const Icon = failed ? AlertTriangle : completed ? CheckCircle2 : PlayCircle;
    return <article className={`activity-card activity-row ${failed ? 'is-warning' : completed ? 'is-complete' : ''}`}>
        <div className="activity-card-icon"><Icon size={19} /></div>
        <div className="activity-card-body"><div className="activity-card-meta"><time title={when(item.createdAt)}><Clock3 size={13} />{item.createdAt ? new Intl.DateTimeFormat('id-ID',{hour:'2-digit',minute:'2-digit'}).format(new Date(item.createdAt)) : '—'}</time><strong>{name || 'Office'}</strong><span>{type.replaceAll('.', ' · ').replaceAll('_', ' ')}</span></div><p>{item.activity}</p></div>
        {status&&<span className={`activity-status ${failed ? 'is-failed' : ''}`}>{status.replaceAll('_',' ')}</span>}
    </article>;
}

export function OperationsView({ view, agents, tasks, activity, error, loading, retry, onSelectWorker }) {
    const [kanbanMode, setKanbanMode] = useState(() => readPanelMode('kanbanPanelMode'));
    const [activityFilter, setActivityFilter] = useState('all');
    const [showAllActivity, setShowAllActivity] = useState(false);
    if (error) return <div className="content-view operations-view"><State icon={AlertTriangle} title="Data Office belum dapat dimuat" detail={error} retry={retry} /></div>;
    const names = Object.fromEntries(agents.map(agent => [agent.id, agent.name]));
    const connected = agents.filter(agent => !unavailable.has(agent.status)).length;
    const statuses = [...new Set(activity.map(item => item.status).filter(Boolean))];
    const validActivityFilter = statuses.includes(activityFilter) ? activityFilter : 'all';
    const filteredActivity = validActivityFilter === 'all' ? activity : activity.filter(item => item.status === validActivityFilter);
    const heading = {
        kanban: ['LIVE KANBAN', 'Kanban', 'Perjalanan setiap tugas dari antrean sampai selesai.'],
        agents: ['WORKER ROSTER', 'Agents', 'Kenali worker Office, tugas berjalan, dan status terkini.'],
        activity: ['OFFICE JOURNAL', 'Activity', 'Aktivitas penting dari worker dan konten dalam satu alur.'],
    }[view];
    return <div className={`content-view operations-view operations-${view} ${view === 'kanban' ? `kanban-mode-${kanbanMode}` : ''}`}>
        <div className="view-heading operations-heading"><div><span>{heading[0]}</span><h1>{heading[1]}</h1><p>{heading[2]}</p></div><div className="operations-heading-badge">{view === 'agents' ? <><Bot size={20} /><strong>{connected}/{agents.length}</strong><small>worker tersedia</small></> : view === 'activity' ? <><Activity size={20} /><strong>{activity.length}</strong><small>peristiwa tercatat</small></> : <><Sparkles size={20} /><strong>{tasks.length}</strong><small>total tugas</small></>}</div></div>
        {view === 'kanban'&&<div className="panel-mode-control" role="group" aria-label="Ukuran Kanban">{PANEL_MODES.map(mode=><button key={mode} type="button" aria-pressed={kanbanMode===mode} onClick={()=>setKanbanMode(writePanelMode('kanbanPanelMode',mode))}>{mode==='compact'?'Ringkas':mode==='expanded'?'Luas':'Normal'}</button>)}</div>}
        {view === 'activity'&&<div className="activity-controls"><label>Filter status<select value={validActivityFilter} onChange={event=>{setActivityFilter(event.target.value);setShowAllActivity(false);}}><option value="all">Semua</option>{statuses.map(status=><option key={status} value={status}>{status.replaceAll('_',' ')}</option>)}</select></label><small>{filteredActivity.length} aktivitas</small></div>}
        {loading ? <State title="Memuat Office…" /> : view === 'kanban' ? <div className="kanban-scroll" aria-label="Task lifecycle Kanban board"><div className="kanban-board">{kanbanColumns.map(([status, label]) => { const columnTasks = tasks.filter(task => task.status === status); return <section className={`kanban-column is-${status}`} key={status}><header><div><span>{label}</span><small>{status === 'running' ? 'In progress' : 'Task lifecycle'}</small></div><b>{columnTasks.length}</b></header><div className="kanban-cards">{columnTasks.length ? columnTasks.map(task => <TaskCard key={task.id} task={task} name={names[task.agentId]} />) : <p className="kanban-empty">No {label.toLowerCase()} tasks</p>}</div></section>; })}</div></div> : view === 'agents' ? agents.length ? <div className="roster-grid">{agents.map(agent => <AgentCard key={agent.id} agent={agent} onSelect={onSelectWorker} />)}</div> : <State icon={Bot} title="Belum ada worker" detail="Worker yang terhubung akan muncul di sini." /> : activity.length ? <><div className="activity-card-list">{filteredActivity.slice(0,showAllActivity?filteredActivity.length:6).map(item => <ActivityCard key={item.id} item={item} name={names[item.agentId]} />)}</div>{filteredActivity.length>6&&<button className="activity-more" type="button" onClick={()=>setShowAllActivity(value=>!value)}>{showAllActivity?'Tampilkan lebih sedikit':`Lihat ${filteredActivity.length-6} lainnya`}</button>}</> : <State icon={Activity} title="Belum ada aktivitas" detail="Peristiwa penting dari worker akan tersusun di sini setelah tugas dijalankan." />}
    </div>;
}
