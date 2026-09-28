import React, { useState } from 'react';
import { Activity, AlertTriangle, ExternalLink, FileText, Inbox, RefreshCw } from 'lucide-react';

const when = (value) => value ? new Intl.DateTimeFormat('id-ID', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(value)) : '—';

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
            tasks.length ? <div className="task-table"><div className="task-row task-head task-row-wide"><span>Task</span><span>Worker / Type</span><span>Status</span><span>Progress</span><span>Started / Finished</span></div>{tasks.map(task => <div className="task-row task-row-wide" key={task.id}><span><i className="task-icon"><FileText size={16} /></i><b>{task.title}</b>{task.target && <small>{task.target}</small>}{task.error && <small className="failure">{task.error}</small>}</span><span>{names[task.agentId] || task.agentId}<small>{task.type}</small></span><span><b className={`status-pill ${task.status === 'failed' ? 'red' : task.status === 'completed' ? 'green' : 'blue'}`}>{task.status}</b></span><span><div className="task-progress"><i style={{ width: `${task.progress}%` }} /></div>{task.progress}%</span><span><small>{when(task.startedAt)}</small><small>{when(task.finishedAt)}</small></span></div>)}</div> : <State title="No tasks yet" detail="Tasks will appear when a connected worker reports task.started." />
        ) : view === 'agents' ? (
            agents.length ? <div className="task-table"><div className="task-row task-head task-row-wide"><span>Agent</span><span>Role / System</span><span>Status</span><span>Progress</span><span>Last activity</span></div>{agents.map(agent => <div className="task-row task-row-wide" key={agent.id}><span><i className="task-icon"><Activity size={16} /></i><b>{agent.name}</b><small>{agent.id}</small></span><span>{agent.role}<small>{agent.parentSystem}</small></span><span><b className={`status-pill ${agent.status === 'failed' || agent.status === 'offline' ? 'red' : agent.status === 'working' ? 'green' : 'blue'}`}>{agent.status}</b></span><span><div className="task-progress"><i style={{ width: `${agent.progress || 0}%` }} /></div>{agent.progress || 0}%</span><span><small>{agent.currentTask}</small><small>{agent.lastActivity}</small></span></div>)}</div> : <State title="No agents registered" detail="Connected workers will appear here." />
        ) : (
            activity.length ? <div className="activity-timeline">{activity.map(item => <article key={item.id}><i /><div><span>{item.type}</span><strong>{names[item.agentId] || 'Office'}</strong><p>{item.activity}</p></div><time>{when(item.createdAt)}</time></article>)}</div> : <State icon={Activity} title="No activity yet" />
        )}
    </div>;
}
