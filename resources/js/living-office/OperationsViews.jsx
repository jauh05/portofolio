import React from 'react';
import { Activity, AlertTriangle, ArrowUpRight, Bot, CheckCircle2, Clock3, Inbox, PlayCircle, RefreshCw, Sparkles } from 'lucide-react';

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

function ActivityCard({ item, name, index }) {
    const type = String(item.type || 'Office event');
    const failed = /fail|error|warning/i.test(type);
    const completed = /complet|publish|approv|generated/i.test(type);
    const Icon = failed ? AlertTriangle : completed ? CheckCircle2 : PlayCircle;
    return <article className={`activity-card ${failed ? 'is-warning' : completed ? 'is-complete' : ''}`}>
        <div className="activity-card-icon"><Icon size={19} /></div>
        <div className="activity-card-body"><div className="activity-card-meta"><span>{type.replaceAll('.', ' · ').replaceAll('_', ' ')}</span><time><Clock3 size={13} />{when(item.createdAt)}</time></div><h3>{name || 'Office'}</h3><p>{item.activity}</p></div>
        <small>{String(index + 1).padStart(2, '0')}</small>
    </article>;
}

export function OperationsView({ view, agents, tasks, activity, error, loading, retry, onSelectWorker }) {
    if (error) return <div className="content-view operations-view"><State icon={AlertTriangle} title="Data Office belum dapat dimuat" detail={error} retry={retry} /></div>;
    const names = Object.fromEntries(agents.map(agent => [agent.id, agent.name]));
    const connected = agents.filter(agent => !unavailable.has(agent.status)).length;
    const heading = {
        kanban: ['LIVE KANBAN', 'Kanban', 'Perjalanan setiap tugas dari antrean sampai selesai.'],
        agents: ['WORKER ROSTER', 'Agents', 'Kenali worker Office, tugas berjalan, dan status terkini.'],
        activity: ['OFFICE JOURNAL', 'Activity', 'Aktivitas penting dari worker dan konten dalam satu alur.'],
    }[view];
    return <div className="content-view operations-view">
        <div className="view-heading operations-heading"><div><span>{heading[0]}</span><h1>{heading[1]}</h1><p>{heading[2]}</p></div><div className="operations-heading-badge">{view === 'agents' ? <><Bot size={20} /><strong>{connected}/{agents.length}</strong><small>worker tersedia</small></> : view === 'activity' ? <><Activity size={20} /><strong>{activity.length}</strong><small>peristiwa tercatat</small></> : <><Sparkles size={20} /><strong>{tasks.length}</strong><small>total tugas</small></>}</div></div>
        {loading ? <State title="Memuat Office…" /> : view === 'kanban' ? <div className="kanban-scroll" aria-label="Task lifecycle Kanban board"><div className="kanban-board">{kanbanColumns.map(([status, label]) => { const columnTasks = tasks.filter(task => task.status === status); return <section className={`kanban-column is-${status}`} key={status}><header><div><span>{label}</span><small>{status === 'running' ? 'In progress' : 'Task lifecycle'}</small></div><b>{columnTasks.length}</b></header><div className="kanban-cards">{columnTasks.length ? columnTasks.map(task => <TaskCard key={task.id} task={task} name={names[task.agentId]} />) : <p className="kanban-empty">No {label.toLowerCase()} tasks</p>}</div></section>; })}</div></div> : view === 'agents' ? agents.length ? <div className="roster-grid">{agents.map(agent => <AgentCard key={agent.id} agent={agent} onSelect={onSelectWorker} />)}</div> : <State icon={Bot} title="Belum ada worker" detail="Worker yang terhubung akan muncul di sini." /> : activity.length ? <div className="activity-card-list">{activity.map((item, index) => <ActivityCard key={item.id} item={item} name={names[item.agentId]} index={index} />)}</div> : <State icon={Activity} title="Belum ada aktivitas" detail="Peristiwa penting dari worker akan tersusun di sini setelah tugas dijalankan." />}
    </div>;
}
