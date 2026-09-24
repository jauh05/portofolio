import React, { useEffect, useMemo, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import {
    Activity, AlertTriangle, ArrowLeft, BarChart3, Bell, Bot, Box, BriefcaseBusiness, CalendarDays,
    Check, ChevronRight, CirclePause, Clock3, Coffee, Cpu, Database, ExternalLink, FileText,
    DollarSign, Gauge, HardDrive, LayoutDashboard, ListTodo, MemoryStick, MessageCircle, MonitorCog, MoreHorizontal,
    PanelLeftClose, Play, Radio, Refrigerator, RotateCcw, Search, Server, Settings, Sparkles, Square,
    Thermometer, Users, X, ZoomIn, ZoomOut,
} from 'lucide-react';
import { agentRegistry, systems } from './agentRegistry';
import { workerStates } from './stateMachine';
import { OFFICE_WORLD, deskAssignments, officeEvents, officeStations } from './officeModel';
import { useLivingOffice } from './useLivingOffice';
import { useOfficeData } from './useOfficeData';
import { OperationsView } from './OperationsViews';
import './living-office.css';

const statusTone = (status) => ({
    monitoring: 'cyan', generating: 'violet', working: 'blue', planning: 'green', completed: 'green',
    idle: 'slate', break: 'amber', error: 'red', warning: 'amber', reporting: 'amber', meeting: 'violet',
    analyzing: 'amber', offline: 'slate', not_connected: 'muted', not_installed: 'muted',
}[status] || 'slate');

function BrandMark() {
    return <div className="brand-mark" aria-hidden="true"><span /><Bot size={24} /></div>;
}

function Header({ summary, notifications, unread, onRead, onReadAll }) {
    const [now, setNow] = useState(new Date());
    const [open, setOpen] = useState(false);
    useEffect(() => { const timer = setInterval(() => setNow(new Date()), 1000); return () => clearInterval(timer); }, []);
    const metrics = [
        { icon: Users, value: summary.workers ?? '—', label: 'AI Workers', tone: 'cyan' },
        { icon: Radio, value: summary.workingNow ?? '—', label: 'Working Now', tone: 'green' },
        { icon: ListTodo, value: summary.tasksToday ?? '—', label: 'Tasks Today', tone: 'blue' },
        { icon: FileText, value: summary.contentThisWeek ?? '—', label: 'Content This Week', tone: 'violet' },
    ];
    return <header className="topbar">
        <a href="/" className="brand"><BrandMark /><div><strong>Jauhar Bot HQ</strong><span>Living AI operations</span></div></a>
        <div className="top-metrics">{metrics.map(({ icon: Icon, value, label, tone }) => <div className="top-metric" key={label}>
            <span className={`metric-icon ${tone}`}><Icon size={16} /></span><strong>{value}</strong><small>{label}</small>
        </div>)}</div>
        <div className="header-user">
            <div className="current-time"><small>{now.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })}</small><strong>{now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':')}</strong></div>
            <button className="icon-button notification-button" onClick={() => setOpen(value => !value)} aria-label={`${unread} unread notifications`}><Bell size={18} />{unread > 0 && <b>{unread > 99 ? '99+' : unread}</b>}</button>
            {open && <aside className="notification-panel"><header><div><strong>Notifications</strong><small>{unread} unread</small></div><button onClick={onReadAll} disabled={!unread}>Mark all read</button></header><div>{notifications.length ? notifications.map(item => <button className={item.readAt ? 'read' : ''} key={item.id} onClick={() => onRead(item.id)}><i className={item.severity}>{item.severity === 'error' ? '×' : '✓'}</i><span><strong>{item.title}</strong><p>{item.message}</p><time>{new Date(item.createdAt).toLocaleString('id-ID')}</time></span></button>) : <p className="notification-empty">No notifications yet</p>}</div></aside>}
            <form action="/office/logout" method="POST" style={{ margin: 0 }}>
                <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')} />
                <button type="submit" className="icon-button" aria-label="Logout" title="Logout" style={{ color: '#ef4444' }}><X size={18} /></button>
            </form>
            <div className="avatar">JF</div><div className="user-copy"><strong>Jauhar</strong><small>Owner</small></div>
        </div>
    </header>;
}

const navItems = [
    ['office', LayoutDashboard, 'Office'], ['tasks', ListTodo, 'Tasks'], ['content', FileText, 'Content'],
    ['activity', Activity, 'Activity'],
];

function Sidebar({ activeView, setActiveView }) {
    return <aside className="sidebar"><nav>{navItems.map(([id, Icon, label]) => <button key={id} className={activeView === id ? 'active' : ''} onClick={() => setActiveView(id)}><Icon size={20} /><span>{label}</span></button>)}</nav><a className="back-site" href="/"><ArrowLeft size={18} /><span>Portfolio</span></a></aside>;
}

function Workstation({ variant = 'desk' }) {
    if (variant === 'trent') return <div className="identity-desk trent-station"><div className="trent-monitors"><i /><i /><i /></div><span className="server-strip"><i /><i /><i /><i /></span><em>SYS</em></div>;
    if (variant === 'servers') return <div className="server-racks"><span /><span /><span /></div>;
    if (variant === 'board') return <div className="planning-board"><b>WEEK 39</b><span /><span /><span /></div>;
    if (variant === 'lounge') return <div className="lounge"><i /><i /><span /></div>;
    if (variant === 'article') return <div className="identity-desk article-station"><div className="angled-laptop"><i /></div><span className="book-stack"><i /><i /><i /></span><em>⌨</em></div>;
    if (variant === 'content') return <div className="identity-desk content-station"><div className="dual-design"><i /><i /></div><span className="phone-prop">▯</span><em>COLOR</em></div>;
    if (variant === 'community') return <div className="identity-desk community-station"><div className="wide-screen"><i /></div><span className="phone-prop">▯</span><em>@</em></div>;
    if (variant === 'analytics') return <div className="identity-desk analytics-station"><div className="analytics-screens"><i /><i /><i /></div><span className="chart-prop">▥</span></div>;
    if (variant === 'finance') return <div className="finance-station"><div className="finance-monitors"><i /><i /></div><span className="cost-graph"><b /><b /><b /><b /></span><em>$</em></div>;
    if (variant === 'pantry') return <div className="pantry-station"><Coffee /><Refrigerator /><span><i /></span></div>;
    if (variant === 'meeting') return <div className="meeting-table"><span /><i /><i /><i /><i /></div>;
    if (variant === 'reception') return <div className="reception-desk"><BrandMark /><span>CONTROL</span></div>;
    return <div className="workstation"><div className="monitor"><span /></div><div className="desk-top" /><div className="chair" /></div>;
}

function OfficeZone({ className, label, icon: Icon, variant, capacity, children, disabled, pending }) {
    return <section className={`office-zone ${className} ${disabled ? 'disabled' : ''}`}><div className="zone-title"><Icon size={13} /><span>{label}</span>{capacity && <small>{capacity} seats</small>}{disabled && <em>Belum diinstal</em>}{pending && <em className="pending">Mock data</em>}</div><Workstation variant={variant} />{children}<i className="plant plant-one" /><i className="plant plant-two" /></section>;
}

const deskVariants = {
    trent: 'trent', 'jauki-social': 'content', 'jauki-threads': 'community', 'jauki-article': 'article',
    'jauki-planner': 'board', 'data-analyst': 'analytics', 'finance-analyst': 'finance',
};

function DeskPod({ agent }) {
    const assignment = deskAssignments[agent.id];
    const seat = assignment.deskSeat;
    const unavailable = ['not_installed', 'not_connected'].includes(agent.status);
    const working = ['working', 'generating', 'planning', 'monitoring', 'analyzing'].includes(agent.status);
    return <div className={`desk-pod desk-${assignment.role} ${unavailable ? 'inactive' : ''}`} style={{ left: `${seat.x}%`, top: `${seat.y}%` }}>
        <span className="desk-label"><i style={{ background: agent.color }} />{agent.name.replace(' Worker', '')}</span>
        <Workstation variant={deskVariants[agent.id]} />
        <span className={`desk-screen ${agent.status === 'error' ? 'error' : ''}`}><b>{agent.shortName}</b><em>{unavailable ? 'NOT CONNECTED' : agent.status.toUpperCase()}</em><small>{working ? `${'█'.repeat(Math.round(agent.progress / 15))}${'░'.repeat(7 - Math.round(agent.progress / 15))} ${agent.progress}%` : agent.currentTask}</small></span>
        <span className="desk-keyboard" /><span className="desk-mouse" /><span className="desk-leg left" /><span className="desk-leg right" />
        <span className="desk-chair" /><span className="desk-foreground" />
        {unavailable && <span className="desk-offline">NOT CONNECTED</span>}
    </div>;
}

function OpenOffice({ agents }) {
    return <section className="open-office"><div className="open-office-title"><BriefcaseBusiness size={14} /><strong>OPEN OFFICE</strong><span>7 dedicated workstations · clear central walkway</span></div>{agents.map((agent) => <DeskPod agent={agent} key={agent.id} />)}<div className="open-office-path path-a" /><div className="open-office-path path-b" /></section>;
}

const officeStateLabel = {
    seated_work: 'Seated · Working', standing_idle: 'Standing', walking: 'Walking', meeting: 'In meeting',
    resting: 'Resting', server_check: 'Server check', returning_to_desk: 'Returning', offline: 'Offline',
    not_installed: 'Belum diinstal', error: 'Error',
};

function Worker({ agent, selected, onClick }) {
    const state = workerStates[agent.status] || workerStates.idle;
    const worldX = (agent.position.x / 100) * OFFICE_WORLD.width;
    const worldY = (agent.position.y / 100) * OFFICE_WORLD.height;
    return <button
        className={`worker worker-${agent.animation || state.motion} is-${agent.movementState || 'idle'} facing-${agent.facing || 'right'} ${selected ? 'selected' : ''}`}
        style={{ '--agent-color': agent.color, '--walk-duration': `${agent.walkDuration || 3.5}s`, transform: `translate3d(${worldX}px, ${worldY}px, 0) translate3d(-50%, -50%, 0)`, zIndex: 100 + Math.floor(agent.position.y) }}
        onClick={() => onClick(agent.id)} aria-label={`Buka detail ${agent.name}`}
    >
        {agent.bubble && <span className="speech-bubble">{agent.bubble}</span>}
        <span className={`worker-status ${statusTone(agent.status)}`}>{agent.status === 'not_installed' ? <Square size={10} /> : <i />}{officeStateLabel[agent.officeState] || state.label}</span>
        <span className="worker-shadow" /><span className="worker-body"><i className="hair" /><i className="head"><b /><b /></i><i className="torso"><small>{agent.shortName}</small></i><i className="arm left" /><i className="arm right" /><i className="leg left" /><i className="leg right" /></span>
        <span className="worker-name">{agent.name.replace(' Worker', '')}</span>
    </button>;
}

function CentralMonitor({ summary }) {
    return <div className="central-monitor"><div className="monitor-heading"><span><Activity size={14} /> CENTRAL OPERATIONS · LIVE</span></div><div className="monitor-grid"><div><small>AI workers</small><strong>{summary.workers ?? '—'}</strong></div><div><small>Working now</small><strong>{summary.workingNow ?? '—'}</strong></div><div><small>Tasks today</small><strong>{summary.tasksToday ?? '—'}</strong></div><div><small>Content / week</small><strong>{summary.contentThisWeek ?? '—'}</strong></div>{summary.successRate != null && <div><small>Success rate</small><strong>{summary.successRate}%</strong></div>}{summary.averageRuntimeSeconds != null && <div><small>Avg runtime</small><strong>{summary.averageRuntimeSeconds}s</strong></div>}</div></div>;
}

function OfficeDecor() {
    const details = [
        ['rug rug-content', ''], ['rug rug-lounge', ''], ['decor bookshelf', '▥'], ['decor printer', '▣'],
        ['decor wall-notes', '▦'], ['decor charging-station', '⌁'], ['decor floor-plant', '✦'], ['decor soft-light', ''],
    ];
    return <>{details.map(([className, label], index) => <span aria-hidden="true" className={className} key={`${className}-${index}`}>{label}</span>)}</>;
}

function MeetingStatus({ event }) {
    const [remaining, setRemaining] = useState(() => Math.max(0, Math.ceil((event.endsAt - Date.now()) / 1000)));
    useEffect(() => { const timer = window.setInterval(() => setRemaining(Math.max(0, Math.ceil((event.endsAt - Date.now()) / 1000))), 1000); return () => window.clearInterval(timer); }, [event.endsAt]);
    return <div className="meeting-bubble"><Users size={13} /><strong>{event.label}</strong><span>{event.message}</span><time>{String(Math.floor(remaining / 60)).padStart(2, '0')}:{String(remaining % 60).padStart(2, '0')}</time></div>;
}

function OfficeMap({ agents, selectedId, onSelect, summary, activeEvent, onSimulateEvent, onServerCheck, onRest }) {
    const [zoom, setZoom] = useState(.68);
    const [eventIndex, setEventIndex] = useState(0);
    const viewportRef = useRef(null);
    const dragRef = useRef(null);
    const adjustZoom = (amount) => setZoom((current) => Math.max(.38, Math.min(1.15, Number((current + amount).toFixed(2)))));
    const resetView = () => { setZoom(.68); viewportRef.current?.scrollTo({ left: 0, top: 0, behavior: 'smooth' }); };
    useEffect(() => { const frame = window.requestAnimationFrame(() => viewportRef.current?.scrollTo({ left: 0, top: 0 })); return () => window.cancelAnimationFrame(frame); }, []);
    const onPointerDown = (event) => {
        if (event.target.closest('button')) return;
        dragRef.current = { x: event.clientX, y: event.clientY, left: viewportRef.current.scrollLeft, top: viewportRef.current.scrollTop };
        viewportRef.current.setPointerCapture(event.pointerId);
    };
    const onPointerMove = (event) => {
        if (!dragRef.current) return;
        viewportRef.current.scrollLeft = dragRef.current.left - (event.clientX - dragRef.current.x);
        viewportRef.current.scrollTop = dragRef.current.top - (event.clientY - dragRef.current.y);
    };
    const nextEvent = () => { const next = (eventIndex + 1) % officeEvents.length; setEventIndex(next); };
    return <div className="office-shell">
        <div className="office-toolbar"><div><span className="live-dot" />OPEN OFFICE · {agents.length} EMPLOYEES <small>Command-driven movement</small></div><div className="event-control"><span>{activeEvent ? `${activeEvent.label} · ${activeEvent.phase.replace('_', ' ')}` : `Visual command: ${officeEvents[eventIndex].label}`}</span><button onClick={nextEvent} aria-label="Event berikutnya"><ChevronRight size={14} /></button><button className="simulate-button" onClick={() => onSimulateEvent(officeEvents[eventIndex].id)}>Start meeting</button><button onClick={onServerCheck}>Trent check</button><button onClick={onRest}>Rest selected</button></div><div className="map-controls"><button onClick={() => adjustZoom(-.1)} aria-label="Zoom out"><ZoomOut size={14} /></button><b>{Math.round(zoom * 100)}%</b><button onClick={() => adjustZoom(.1)} aria-label="Zoom in"><ZoomIn size={14} /></button><button onClick={resetView} aria-label="Reset view"><RotateCcw size={14} /></button></div></div>
        <div className="office-viewport" ref={viewportRef} onPointerDown={onPointerDown} onPointerMove={onPointerMove} onPointerUp={() => { dragRef.current = null; }} onPointerCancel={() => { dragRef.current = null; }}>
            <div className="office-canvas" style={{ width: OFFICE_WORLD.width * zoom, height: OFFICE_WORLD.height * zoom }}>
                <div className="office-map" style={{ width: OFFICE_WORLD.width, height: OFFICE_WORLD.height, transform: `scale(${zoom})` }}>
                    <OfficeDecor /><OpenOffice agents={agents} /><CentralMonitor summary={summary} />
                    <OfficeZone className="zone-server" label="SERVER ROOM" icon={Server} variant="servers" capacity={2}><span className="temp-monitor">MONITORING PENDING</span></OfficeZone>
                    <OfficeZone className="zone-meeting" label="MEETING ROOM" icon={Users} variant="meeting" capacity={8}><span className="presentation-screen">PRESENTATION</span><span className="meeting-whiteboard">PLAN · REVIEW · DECIDE</span></OfficeZone>
                    <OfficeZone className="zone-break" label="REST / LOUNGE" icon={Coffee} variant="lounge" capacity={4}><div className="integrated-pantry"><Coffee size={21} /><span>COFFEE</span></div><span className="water-dispenser">◒</span><span className="daybed">DAYBED</span></OfficeZone>
                    <div className="main-hallway"><span>MAIN WALKWAY</span></div>
                    {activeEvent && <MeetingStatus event={activeEvent} />}
                    {agents.map(agent => <Worker key={agent.id} agent={agent} selected={selectedId === agent.id} onClick={onSelect} />)}
                </div>
            </div>
        </div>
    </div>;
}

function BottomDock({ selectedAgent, onSelect, agents, activity }) {
    return <div className="bottom-dock"><section className="dock-agent"><div className="panel-label">SELECTED WORKER</div><div className="current-agent"><span className="mini-avatar" style={{ '--agent-color': selectedAgent.color }}>{selectedAgent.shortName}</span><div><strong>{selectedAgent.name}</strong><small>{selectedAgent.role}</small><span className={`status-pill ${statusTone(selectedAgent.status)}`}>{workerStates[selectedAgent.status]?.label || selectedAgent.status}</span></div></div></section><section className="dock-task"><div className="panel-label">CURRENT TASK <small>LIVE</small></div><div className="active-task"><span><FileText size={19} /></span><div><strong>{selectedAgent.currentTask}</strong><small>{selectedAgent.parentSystem}</small><div className="progress-track"><i style={{ width: `${selectedAgent.progress}%` }} /></div></div><b>{selectedAgent.progress}%</b></div></section><section className="dock-feed"><div className="panel-label">RECENT ACTIVITY</div><div className="feed-list">{activity.length ? activity.slice(0, 2).map(item => <div key={item.id}><time>{new Date(item.createdAt).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}</time><i /><p><strong>{item.type}</strong> {item.activity}</p></div>) : <p className="dock-empty">No activity yet</p>}</div></section><section className="dock-team"><div className="panel-label">CONNECTED <small>{agents.filter(a => !['offline', 'not_connected', 'not_installed'].includes(a.status)).length}/{agents.length}</small></div><div className="avatar-stack">{agents.map(a => <button key={a.id} style={{ '--agent-color': a.color }} onClick={() => onSelect(a.id)} className={['offline', 'not_connected', 'not_installed'].includes(a.status) ? 'disabled' : ''}>{a.shortName}</button>)}</div><small>Click a worker to inspect</small></section></div>;
}

function AgentDrawer({ agent, tasks, commands, enqueue, onClose }) {
    if (!agent) return null;
    const parent = systems.find(s => s.id === agent.parentSystem);
    const unavailable = ['not_installed', 'not_connected'].includes(agent.status);
    const room = officeStations[agent.currentStation]?.label || agent.zoneLabel;
    const recentTasks = tasks.filter(task => task.agentId === agent.id).slice(0, 5);
    const recentCommands = commands.filter(command => command.agentId === agent.id).slice(0, 3);
    const labels = { generate_feed: 'Generate Feed', generate_story: 'Generate Story', publish_last: 'Publish Last', generate_threads: 'Generate Threads', generate_article: 'Generate Article', publish_article: 'Publish Article', schedule_article: 'Schedule Article', run_weekly_analysis: 'Run Weekly Analysis' };
    const [queueState, setQueueState] = useState(null);
    const run = async action => { setQueueState('queuing'); try { const result = await enqueue(agent.id, action); setQueueState(`${labels[action]} · ${result.status}`); } catch (error) { setQueueState(error.message); } };
    return <>
        <button className="drawer-scrim" onClick={onClose} aria-label="Tutup panel" />
        <aside className="agent-drawer">
            <div className="drawer-header"><div className="drawer-avatar" style={{ '--agent-color': agent.color }}>{agent.shortName}<i /></div><div><span>{parent?.name}</span><h2>{agent.name}</h2><p>{agent.fullRole}</p></div><button className="drawer-close" onClick={onClose}><X size={19} /></button></div>
            <div className="drawer-status"><span className={`status-pill ${statusTone(agent.status)}`}>{workerStates[agent.status]?.label || agent.status}</span><small><Clock3 size={13} /> {agent.lastActivity}</small></div>
            <div className="activity-state"><div><small>CURRENT ROOM</small><strong>{room}</strong></div><div><small>ACTIVITY</small><strong>{officeStateLabel[agent.officeState] || agent.officeState}</strong></div><div><small>ENERGY</small><strong>{agent.energy}%</strong><i><b style={{ width: `${agent.energy}%` }} /></i></div></div>
            {unavailable ? <div className="unavailable-card"><Database size={24} /><div><strong>NOT CONNECTED</strong><p>Integration pending. No command controls are available for this worker.</p></div><button disabled>Integration pending</button></div> : <>
                <section className="drawer-section"><label>CURRENT TASK</label><div className="drawer-task"><span><Activity size={18} /></span><div><strong>{agent.currentTask}</strong><small>{room}</small><div className="progress-track"><i style={{ width: `${agent.progress}%` }} /></div></div><b>{agent.progress}%</b></div></section>
                <section className="drawer-section"><label>SAFE COMMANDS</label><div className="command-grid">{agent.availableActions.map(action => <button key={action} onClick={() => run(action)} disabled={queueState === 'queuing'}><Play size={13} />{labels[action]}</button>)}</div>{queueState && <p className="queue-state">{queueState}</p>}</section>
                <section className="drawer-section"><label>CAPABILITIES</label><div className="capability-list">{agent.capabilities.map(item => <span key={item}><Check size={12} />{item}</span>)}</div></section>
                <section className="drawer-section"><label>RECENT TASKS / RESULTS</label>{recentTasks.length ? <div className="result-list">{recentTasks.map(item => <div className="result-row" key={item.id}><span><Box size={15} /></span><strong>{item.title}</strong><b className={`status-pill ${item.status === 'failed' ? 'red' : item.status === 'completed' ? 'green' : 'blue'}`}>{item.status}</b></div>)}</div> : <p className="drawer-empty">No tasks yet</p>}</section>
                <section className="drawer-section"><label>COMMAND HISTORY</label>{recentCommands.length ? <div className="command-history">{recentCommands.map(item => <p key={item.id}><span>{labels[item.action] || item.action}</span><b>{item.status}</b></p>)}</div> : <p className="drawer-empty">No commands queued yet</p>}</section>
            </>}
            <p className="drawer-note"><PanelLeftClose size={13} /> Commands are queued only. No shell or operating-system command is executed.</p>
        </aside>
    </>;
}

function App() {
    const { visualAgents: agents, activeEvent, simulateEvent, sendToRest, sendToServer } = useLivingOffice(agentRegistry);
    const office = useOfficeData();
    const ambience = useMemo(() => { const hour = new Date().getHours(); return hour >= 18 || hour < 6 ? 'night' : hour >= 16 ? 'evening' : 'day'; }, []);
    const [selectedId, setSelectedId] = useState('jauki-article');
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [activeView, setActiveView] = useState('office');
    const selectedAgent = useMemo(() => agents.find(a => a.id === selectedId) || agents[0], [agents, selectedId]);
    const selectAgent = (id) => { setSelectedId(id); setDrawerOpen(true); };
    return <div className="app-shell" data-ambience={ambience}>
        <Header summary={office.summary} notifications={office.notifications} unread={office.unread} onRead={office.markRead} onReadAll={office.markAllRead} />
        <Sidebar activeView={activeView} setActiveView={setActiveView} />
        <main className={`main-stage ${activeView !== 'office' ? 'view-mode' : ''}`}>{activeView === 'office' ? <><OfficeMap agents={agents} selectedId={selectedId} onSelect={selectAgent} summary={office.summary} activeEvent={activeEvent} onSimulateEvent={simulateEvent} onServerCheck={() => sendToServer('trent')} onRest={() => !['not_installed', 'not_connected'].includes(selectedAgent.status) && sendToRest(selectedAgent.id)} /><BottomDock selectedAgent={selectedAgent} onSelect={selectAgent} agents={agents} activity={office.activity} /></> : <OperationsView view={activeView} agents={agents} tasks={office.tasks} activity={office.activity} content={office.content} error={office.error} loading={office.loading} retry={office.refresh} />}</main>
        {drawerOpen && <AgentDrawer agent={selectedAgent} tasks={office.tasks} commands={office.commands} enqueue={office.enqueue} onClose={() => setDrawerOpen(false)} />}
        {office.toast && <div className={`office-toast ${office.toast.severity}`}><i>{office.toast.severity === 'error' ? '×' : '✓'}</i><div><strong>{office.toast.title}</strong><span>{office.toast.message}</span></div><button onClick={office.dismissToast}><X size={14} /></button></div>}
    </div>;
}

createRoot(document.getElementById('living-office-root')).render(<App />);
