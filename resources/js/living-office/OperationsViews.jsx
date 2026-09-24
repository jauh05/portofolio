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
    const heading = { tasks: ['TASK HISTORY', 'Tasks', 'Real task runs reported by connected workers.'], content: ['CONTENT LIBRARY', 'Content', 'Generated and published content from connected workers.'], activity: ['MEANINGFUL EVENTS', 'Activity', 'Task and content history without heartbeat or polling noise.'] }[view];
    return <div className="content-view"><div className="view-heading"><div><span>{heading[0]}</span><h1>{heading[1]}</h1><p>{heading[2]}</p></div></div>
        {loading ? <State title="Loading operations…" /> : view === 'tasks' ? (
            tasks.length ? <div className="task-table"><div className="task-row task-head task-row-wide"><span>Task</span><span>Worker / Type</span><span>Status</span><span>Progress</span><span>Started / Finished</span></div>{tasks.map(task => <div className="task-row task-row-wide" key={task.id}><span><i className="task-icon"><FileText size={16} /></i><b>{task.title}</b>{task.target && <small>{task.target}</small>}{task.error && <small className="failure">{task.error}</small>}</span><span>{names[task.agentId] || task.agentId}<small>{task.type}</small></span><span><b className={`status-pill ${task.status === 'failed' ? 'red' : task.status === 'completed' ? 'green' : 'blue'}`}>{task.status}</b></span><span><div className="task-progress"><i style={{ width: `${task.progress}%` }} /></div>{task.progress}%</span><span><small>{when(task.startedAt)}</small><small>{when(task.finishedAt)}</small></span></div>)}</div> : <State title="No tasks yet" detail="Tasks will appear when a connected worker reports task.started." />
        ) : view === 'content' ? <><div className="filter-tabs">{[['all','All'],['article','Articles'],['instagram','Instagram'],['threads','Threads']].map(([id,label]) => <button className={filter === id ? 'active' : ''} key={id} onClick={() => setFilter(id)}>{label}</button>)}</div>{content.filter(item => filter === 'all' || item.platform === filter).length ? <div className="content-grid">{content.filter(item => filter === 'all' || item.platform === filter).map(item => <article className="content-card" key={item.id}>{item.imageUrl ? <img src={item.imageUrl} alt="" /> : <div className="content-placeholder"><FileText size={24} /></div>}<div><span>{item.platform} · {item.contentType}</span><h3>{item.title || 'Untitled content'}</h3>{item.text && <p>{item.text}</p>}<dl><div><dt>Generated</dt><dd>{when(item.generatedAt)}</dd></div><div><dt>Published</dt><dd>{when(item.publishedAt)}</dd></div>{item.externalId && <div><dt>Media ID</dt><dd>{item.externalId}</dd></div>}</dl><footer><b className="status-pill blue">{item.status}</b>{item.publicUrl && <a href={item.publicUrl} target="_blank" rel="noreferrer">Open <ExternalLink size={13} /></a>}</footer></div></article>)}</div> : <State title="No content generated yet." />}</> : (
            activity.length ? <div className="activity-timeline">{activity.map(item => <article key={item.id}><i /><div><span>{item.type}</span><strong>{names[item.agentId] || 'Office'}</strong><p>{item.activity}</p></div><time>{when(item.createdAt)}</time></article>)}</div> : <State icon={Activity} title="No activity yet" />
        )}
    </div>;
}
