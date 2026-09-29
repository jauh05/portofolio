import React, { useState } from 'react';
import { Activity, AlertTriangle, ExternalLink, FileText, Inbox, RefreshCw } from 'lucide-react';

const when = (value) => value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '—';
const kanbanColumns = [
    ['queued', 'Queued'],
    ['claimed', 'Claimed'],
    ['running', 'Running'],
    ['completed', 'Completed'],
    ['failed', 'Failed'],
];

function TaskCard({ task, name }) {
    const isRunning = task.status === 'running';
    const isFinished = ['completed', 'failed'].includes(task.status);
    return <article className={`kanban-card is-${task.status}`}>
        <header><span className={`status-pill ${task.status === 'failed' ? 'red' : task.status === 'completed' ? 'green' : task.status === 'running' ? 'blue' : 'amber'}`}>{task.status}</span><small>{task.type}</small></header>
        <h3>{task.title}</h3>
        <dl>
            <div><dt>Worker</dt><dd>{name || task.agentId}</dd></div>
            {task.target && <div><dt>Target</dt><dd>{task.target}</dd></div>}
            <div><dt>Started</dt><dd>{when(task.startedAt)}</dd></div>
            {isFinished && <div><dt>{task.status === 'completed' ? 'Completed' : 'Failed'}</dt><dd>{when(task.finishedAt)}</dd></div>}
        </dl>
        {isRunning && <div className="kanban-progress"><span><b>Progress</b><b>{task.progress ?? 0}%</b></span><i><b style={{ width: `${task.progress ?? 0}%` }} /></i></div>}
        {task.error && <p className="kanban-error">{task.error}</p>}
    </article>;
}

function State({ icon: Icon = Inbox, title, detail, retry }) {
    return <div className="empty-state"><Icon size={30} /><strong>{title}</strong>{detail && <p>{detail}</p>}{retry && <button onClick={retry}><RefreshCw size={14} /> Retry</button>}</div>;
}

export function OperationsView({ view, agents, tasks, activity, content, error, loading, retry }) {
    const [filter, setFilter] = useState('all');
    if (error) return <div className="content-view"><State icon={AlertTriangle} title="Unable to load data" detail={error} retry={retry} /></div>;
    const names = Object.fromEntries(agents.map((agent) => [agent.id, agent.name]));
    const heading = {
        kanban: ['LIVE KANBAN', 'Kanban', 'Real task runs grouped by status from connected workers.'],
        agents: ['WORKER ROSTER', 'Agents', 'Connected Living AI Office workers and their current state.'],
        activity: ['MEANINGFUL EVENTS', 'Activity', 'Task and content history without heartbeat or polling noise.'],
    }[view];
    return <div className="content-view"><div className="view-heading"><div><span>{heading[0]}</span><h1>{heading[1]}</h1><p>{heading[2]}</p></div></div>
        {loading ? <State title="Loading operations…" /> : view === 'kanban' ? (
            <div className="kanban-scroll" aria-label="Task lifecycle Kanban board"><div className="kanban-board">{kanbanColumns.map(([status, label]) => {
                const columnTasks = tasks.filter(task => task.status === status);
                return <section className={`kanban-column is-${status}`} key={status}><header><div><span>{label}</span><small>{status === 'running' ? 'In progress' : 'Task lifecycle'}</small></div><b>{columnTasks.length}</b></header><div className="kanban-cards">{columnTasks.length ? columnTasks.map(task => <TaskCard key={task.id} task={task} name={names[task.agentId]} />) : <p className="kanban-empty">No {label.toLowerCase()} tasks</p>}</div></section>;
            })}</div></div>
        ) : view === 'agents' ? (
            agents.length ? <div className="task-table"><div className="task-row task-head task-row-wide"><span>Agent</span><span>Role / System</span><span>Status</span><span>Progress</span><span>Last activity</span></div>{agents.map(agent => <div className="task-row task-row-wide" key={agent.id}><span><i className="task-icon"><Activity size={16} /></i><b>{agent.name}</b><small>{agent.id}</small></span><span>{agent.role}<small>{agent.parentSystem}</small></span><span><b className={`status-pill ${agent.status === 'failed' || agent.status === 'offline' ? 'red' : agent.status === 'working' ? 'green' : 'blue'}`}>{agent.status}</b></span><span><div className="task-progress"><i style={{ width: `${agent.progress || 0}%` }} /></div>{agent.progress || 0}%</span><span><small>{agent.currentTask}</small><small>{agent.lastActivity}</small></span></div>)}</div> : <State title="No agents registered" detail="Connected workers will appear here." />
        ) : (
            activity.length ? <div className="activity-timeline">{activity.map(item => <article key={item.id}><i /><div><span>{item.type}</span><strong>{names[item.agentId] || 'Office'}</strong><p>{item.activity}</p></div><time>{when(item.createdAt)}</time></article>)}</div> : <State icon={Activity} title="No activity yet" />
        )}
    </div>;
}
