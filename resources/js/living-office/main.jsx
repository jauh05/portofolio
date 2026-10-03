import React, { useEffect, useMemo, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import {
    Activity, AlertTriangle, ArrowLeft, BarChart3, Bell, Bot, Box, BriefcaseBusiness, CalendarDays,
    Check, ChevronRight, CirclePause, Clock3, Coffee, Cpu, Database, ExternalLink, FileText,
    DollarSign, Gauge, HardDrive, LayoutDashboard, ListTodo, MemoryStick, MessageCircle, MonitorCog, MoreHorizontal,
    PanelLeftClose, Play, PlugZap, Radio, Refrigerator, RotateCcw, Search, Server, Settings, Sparkles, Square,
    Thermometer, Users, X, ZoomIn, ZoomOut, Moon, Sun,
} from 'lucide-react';
import { agentRegistry, systems } from './agentRegistry';
import { workerStates } from './stateMachine';
import { OFFICE_WORLD, deskAssignments, officeEvents, officeStations } from './officeModel';
import { useLivingOffice } from './useLivingOffice';
import { useOfficeData } from './useOfficeData';
import { OperationsView } from './OperationsViews';
import { ContentPlanner } from './ContentPlanner';
import { AnalystReports } from './AnalystReports';
import { ApiStatus } from './ApiStatus';
import { OfficeFloor, OfficeRoomRail } from './OfficeFloor';
import OfficeRenderer from './OfficeRenderer';
import { normalizeRoster } from './preview3d/full/roster';
import './living-office.css';
import './office-redesign.css';
import './office-pages.css';
import './office-interactions.css';

const statusTone = (status) => ({
    monitoring: 'cyan', generating: 'violet', working: 'blue', planning: 'green', completed: 'green',
    idle: 'slate', break: 'amber', error: 'red', warning: 'amber', reporting: 'amber', meeting: 'violet',
    analyzing: 'amber', offline: 'slate', not_connected: 'muted', not_installed: 'muted',
}[status] || 'slate');

function BrandMark() {
    return <div className="brand-mark" aria-hidden="true"><span /><Bot size={24} /></div>;
}

function Header({ summary, notifications, unread, onRead, onReadAll, onNotificationOpen, theme, onThemeToggle }) {
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
            <button className="icon-button theme-toggle" onClick={onThemeToggle} aria-label={`Switch to ${theme === 'light' ? 'dark' : 'light'} mode`} title={`Switch to ${theme === 'light' ? 'dark' : 'light'} mode`}>{theme === 'light' ? <Moon size={17} /> : <Sun size={17} />}</button>
            <button className="icon-button notification-button" onClick={() => setOpen(value => !value)} aria-label={`${unread} unread notifications`}><Bell size={18} />{unread > 0 && <b>{unread > 99 ? '99+' : unread}</b>}</button>
            {open && <aside className="notification-panel"><header><div><strong>Notifications</strong><small>{unread} unread</small></div><button onClick={onReadAll} disabled={!unread}>Mark all read</button></header><div>{notifications.length ? notifications.map(item => <button className={item.readAt ? 'read' : ''} key={item.id} onClick={() => { onRead(item.id); if (item.type === 'analyst.report_ready') { onNotificationOpen?.(item.data?.report_id); setOpen(false); } }}><i className={item.severity}>{item.severity === 'error' ? '×' : '✓'}</i><span><strong>{item.title}</strong><p>{item.message}</p><time>{new Date(item.createdAt).toLocaleString('id-ID')}</time></span></button>) : <p className="notification-empty">No notifications yet</p>}</div></aside>}
            <form action="/office/logout" method="POST" style={{ margin: 0 }}>
                <input type="hidden" name="_token" value={document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')} />
                <button type="submit" className="icon-button" aria-label="Logout" title="Logout" style={{ color: '#ef4444' }}><X size={18} /></button>
            </form>
            <div className="avatar">JF</div><div className="user-copy"><strong>Jauhar</strong><small>Owner</small></div>
        </div>
    </header>;
}

const navItems = [
    ['overview', LayoutDashboard, 'Overview'], ['kanban', ListTodo, 'Kanban'], ['planner', CalendarDays, 'Content Planner'],
    ['reports', BarChart3, 'Analyst Report'], ['agents', Bot, 'Agents'], ['activity', Activity, 'Activity'], ['api', PlugZap, 'API Aktif'],
];

const actionFields = {
    research_trends: ['topic', 'audience', 'language', 'limit'],
    research_topic: ['topic', 'audience', 'language'],
    find_content_ideas: ['topic', 'audience', 'language'],
    analyze_sources: ['topic', 'sources', 'language'],
};

function Sidebar({ activeView, setActiveView }) {
    return <aside className="sidebar"><nav>{navItems.map(([id, Icon, label]) => <button key={id} className={activeView === id ? 'active' : ''} onClick={() => { if (window.location.hash === '#kelola-planner') window.history.replaceState(null, '', window.location.pathname + window.location.search); setActiveView(id); }}><Icon size={20} /><span>{label}</span></button>)}</nav><a className="back-site" href="/"><ArrowLeft size={18} /><span>Portfolio</span></a></aside>;
}

function Workstation({ variant = 'desk', worker = null }) {
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
    return <div className={`workstation ${worker?.status || 'idle'}`}><span className="workstation-shadow" /><span className="workstation-chair" /><span className="workstation-desk"><i className="workstation-monitor"><b>{worker?.shortName}</b><em /></i><i className="workstation-keyboard" /><i className="workstation-mouse" /></span></div>;
}

function DeskPod({ agent }) {
    const assignment = deskAssignments[agent.id];
    const seat = assignment.deskSeat;
    const unavailable = ['not_installed', 'not_connected'].includes(agent.status);
    return <div className={`desk-pod floor-desk-pod ${unavailable ? 'inactive' : ''}`} style={{ left: `${seat.x}%`, top: `${seat.y}%` }}>
        <span className="desk-label"><i style={{ background: agent.color }} />{agent.name.replace(' Worker', '')}</span>
        <Workstation worker={agent} />
        {unavailable && <span className="desk-offline">NOT CONNECTED</span>}
    </div>;
}

const officeStateLabel = {
    seated_work: 'Seated · Working', standing_idle: 'Standing', walking: 'Walking', meeting: 'In meeting',
    resting: 'Resting', server_check: 'Server check', returning_to_desk: 'Returning', offline: 'Offline',
    not_installed: 'Belum diinstal', error: 'Error',
};

function Worker({ agent, selected, onClick, reportReady, worldHeight }) {
    const state = workerStates[agent.status] || workerStates.idle;
    const worldX = (agent.position.x / 100) * OFFICE_WORLD.width;
    const worldY = (agent.position.y / 100) * worldHeight;
    return <button
        className={`worker worker-${agent.animation || state.motion} status-${agent.status} is-${agent.movementState || 'idle'} facing-${agent.facing || 'right'} ${selected ? 'selected' : ''}`}
        style={{ '--agent-color': agent.color, '--walk-duration': `${agent.walkDuration || 3.5}s`, transform: `translate3d(${worldX}px, ${worldY}px, 0) translate3d(-50%, -50%, 0)`, zIndex: 100 + Math.floor(agent.position.y) }}
        onClick={() => onClick(agent.id)} aria-label={`Buka detail ${agent.name}`}
    >
        {agent.bubble && <span className="speech-bubble">{agent.bubble}</span>}
        <span className={`worker-status ${reportReady ? 'violet' : statusTone(agent.status)}`}>{agent.status === 'not_installed' ? <Square size={10} /> : <i />}{reportReady ? 'Report ready' : officeStateLabel[agent.officeState] || state.label}</span>
        <span className="worker-shadow" /><span className="worker-body"><i className="hair" /><i className="head"><b /><b /></i><i className="torso"><small>{agent.shortName}</small></i><i className="arm left" /><i className="arm right" /><i className="leg left" /><i className="leg right" /></span>
        <span className="worker-name">{agent.name.replace(' Worker', '')}</span>
    </button>;
}

function MeetingStatus({ event }) {
    const [remaining, setRemaining] = useState(() => Math.max(0, Math.ceil((event.endsAt - Date.now()) / 1000)));
    useEffect(() => { const timer = window.setInterval(() => setRemaining(Math.max(0, Math.ceil((event.endsAt - Date.now()) / 1000))), 1000); return () => window.clearInterval(timer); }, [event.endsAt]);
    return <div className="meeting-bubble"><Users size={13} /><strong>{event.label}</strong><span>{event.message}</span><time>{String(Math.floor(remaining / 60)).padStart(2, '0')}:{String(remaining % 60).padStart(2, '0')}</time></div>;
}

function OfficeMap({ agents, selectedId, onSelect, summary, analystReport, activeEvent, onSimulateEvent, onServerCheck, onRest }) {
    const [zoom, setZoom] = useState(.85);
    const [selectedRoomId, setSelectedRoomId] = useState('open');
    const [eventIndex, setEventIndex] = useState(0);
    const viewportRef = useRef(null);
    const dragRef = useRef(null);
    const suppressClickRef = useRef(false);
    const zoomTouchedRef = useRef(false);
    const fitZoom = (showAll = false) => {
        const viewport = viewportRef.current;
        if (!viewport) return;
        const fit = Math.min((viewport.clientWidth - 12) / OFFICE_WORLD.width, (viewport.clientHeight - 12) / OFFICE_WORLD.height);
        setZoom(showAll ? Math.max(.25, fit) : Math.min(1.35, Math.max(.68, fit * 1.4)));
    };
    const adjustZoom = (amount) => { zoomTouchedRef.current = true; setZoom((current) => Math.max(.25, Math.min(1.5, Number((current + amount).toFixed(2))))); };
    const resetView = () => { zoomTouchedRef.current = true; fitZoom(true); viewportRef.current?.scrollTo({ left: 0, top: 0, behavior: 'smooth' }); };
    useEffect(() => { const frame = window.requestAnimationFrame(() => viewportRef.current?.scrollTo({ left: 0, top: 0 })); return () => window.cancelAnimationFrame(frame); }, []);
    useEffect(() => {
        const viewport = viewportRef.current;
        if (!viewport) return undefined;
        const observer = new ResizeObserver(() => { if (!zoomTouchedRef.current) fitZoom(); });
        observer.observe(viewport);
        fitZoom();
        return () => observer.disconnect();
    }, []);
    const onPointerDown = (event) => {
        if (event.button !== 0 || event.target.closest('.worker')) return;
        dragRef.current = { pointerId: event.pointerId, x: event.clientX, y: event.clientY, left: viewportRef.current.scrollLeft, top: viewportRef.current.scrollTop, moved: false };
    };
    const onPointerMove = (event) => {
        const drag = dragRef.current;
        if (!drag || drag.pointerId !== event.pointerId) return;
        const deltaX = event.clientX - drag.x;
        const deltaY = event.clientY - drag.y;
        if (!drag.moved && Math.hypot(deltaX, deltaY) < 6) return;
        if (!drag.moved) {
            drag.moved = true;
            viewportRef.current.classList.add('is-dragging');
            viewportRef.current.setPointerCapture(event.pointerId);
        }
        viewportRef.current.scrollLeft = drag.left - deltaX;
        viewportRef.current.scrollTop = drag.top - deltaY;
    };
    const endDrag = (event) => {
        const drag = dragRef.current;
        if (!drag || drag.pointerId !== event.pointerId) return;
        if (drag.moved) {
            suppressClickRef.current = true;
            window.setTimeout(() => { suppressClickRef.current = false; }, 0);
        }
        viewportRef.current?.classList.remove('is-dragging');
        if (viewportRef.current?.hasPointerCapture(event.pointerId)) viewportRef.current.releasePointerCapture(event.pointerId);
        dragRef.current = null;
    };
    const onMapClickCapture = (event) => {
        if (!suppressClickRef.current) return;
        event.preventDefault();
        event.stopPropagation();
        suppressClickRef.current = false;
    };
    const nextEvent = () => { const next = (eventIndex + 1) % officeEvents.length; setEventIndex(next); };
    const activeWorkers = agents.filter(agent => !['offline', 'not_connected', 'not_installed'].includes(agent.status)).length;
    const trentAvailable = agents.some(agent => agent.id === 'trent' && !['offline', 'not_connected', 'not_installed'].includes(agent.status));
    const selectedAvailable = agents.some(agent => agent.id === selectedId && !['offline', 'not_connected', 'not_installed'].includes(agent.status));
    return <div className="office-shell">
        <div className="office-toolbar"><div><span className={`live-dot ${activeWorkers ? '' : 'inactive'}`} />OFFICE FLOOR · {agents.length} WORKERS <small>{summary.workingNow ?? 0} working now</small></div><div className="event-control"><span>{activeEvent ? `${activeEvent.label} · ${activeEvent.phase.replace('_', ' ')}` : `Visual command: ${officeEvents[eventIndex].label}`}</span><button onClick={nextEvent} aria-label="Event berikutnya"><ChevronRight size={14} /></button><button className="simulate-button" disabled={Boolean(activeEvent)} onClick={() => onSimulateEvent(officeEvents[eventIndex].id)}>Start meeting</button><button disabled={!trentAvailable} title={trentAvailable ? 'Trent checks the server room' : 'Trent belum terhubung'} onClick={onServerCheck}>Trent check</button><button disabled={!selectedAvailable} title={selectedAvailable ? 'Send selected worker to the lounge' : 'Worker belum terhubung'} onClick={onRest}>Rest selected</button></div><div className="map-controls"><span className="map-pan-hint">Geser peta</span><button onClick={() => adjustZoom(-.1)} aria-label="Zoom out"><ZoomOut size={14} /></button><b>{Math.round(zoom * 100)}%</b><button onClick={() => adjustZoom(.1)} aria-label="Zoom in"><ZoomIn size={14} /></button><button onClick={resetView} aria-label="Lihat seluruh peta"><RotateCcw size={14} /></button></div></div>
        <div className="office-command-layout"><div className="office-viewport" ref={viewportRef} onPointerDown={onPointerDown} onPointerMove={onPointerMove} onPointerUp={endDrag} onPointerCancel={endDrag} onClickCapture={onMapClickCapture} aria-label="Peta Living AI Office, geser untuk menjelajah ruangan" role="region" tabIndex={0}>
            <div className="office-canvas" style={{ width: OFFICE_WORLD.width * zoom, height: OFFICE_WORLD.height * zoom }}>
                <div className="office-map" style={{ width: OFFICE_WORLD.width, height: OFFICE_WORLD.height, transform: `scale(${zoom})` }}>
                    <OfficeFloor agents={agents} selectedRoomId={selectedRoomId} onRoomSelect={setSelectedRoomId} />
                    {agents.map(agent => <DeskPod agent={agent} key={`desk-${agent.id}`} />)}
                    {activeEvent && <MeetingStatus event={activeEvent} />}
                    {agents.map(agent => <Worker key={agent.id} agent={agent} selected={selectedId === agent.id} onClick={onSelect} reportReady={agent.id === 'jauki-analyst' && analystReport?.status === 'ready'} worldHeight={OFFICE_WORLD.height} />)}
                </div>
            </div>
        </div><OfficeRoomRail agents={agents} selectedRoomId={selectedRoomId} onRoomSelect={setSelectedRoomId} onSelectWorker={onSelect} /></div>
    </div>;
}

function BottomDock({ selectedAgent, onSelect, agents, activity, analystReport, onOpenReports }) {
    if (!selectedAgent) return <div className="bottom-dock"><p>Belum ada worker pada roster Office.</p></div>;
    const connected = agents.filter(agent => !['offline', 'not_connected', 'not_installed'].includes(agent.status)).length;
    return <div className="bottom-dock">
        <section className="dock-agent"><div className="panel-label">SELECTED WORKER</div><div className="current-agent"><span className="mini-avatar" style={{ '--agent-color': selectedAgent.color }}>{selectedAgent.shortName}</span><div><strong>{selectedAgent.name}</strong><small>{selectedAgent.role}</small><span className={`status-pill ${statusTone(selectedAgent.status)}`}>{workerStates[selectedAgent.status]?.label || selectedAgent.status}</span></div></div></section>
        <section className="dock-task"><div className="panel-label">CURRENT TASK <small>LIVE</small></div><div className="active-task"><span><FileText size={19} /></span><div><strong>{selectedAgent.currentTask}</strong><small>{selectedAgent.parentSystem}</small><div className="progress-track"><i style={{ width: `${selectedAgent.progress}%` }} /></div></div><b>{Number.isFinite(selectedAgent.progress) ? `${selectedAgent.progress}%` : '—'}</b></div></section>
        <section className="dock-feed"><div className="panel-label">RECENT ACTIVITY</div><div className="feed-list">{activity.length ? activity.slice(0, 2).map(item => <div key={item.id}><time>{new Date(item.createdAt).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })}</time><i /><p><strong>{item.type}</strong> {item.activity}</p></div>) : <p className="dock-empty">No activity yet</p>}</div></section>
        <section className="dock-insight"><div className="panel-label">ANALYST INSIGHT</div><div className="dock-insight-content"><BarChart3 size={21} /><p>{analystReport?.summary || "Laporan analyst akan tampil di sini setelah tersedia."}</p></div><button type="button" onClick={onOpenReports}>{analystReport ? "Buka laporan" : "Lihat Analyst"} <ChevronRight size={13} /></button></section>
        <section className="dock-status"><div className="panel-label">OFFICE STATUS</div><div className="dock-status-row"><strong>{connected}/{agents.length}</strong><small>Workers active</small></div><div className="dock-status-bar" aria-label={`${connected} dari ${agents.length} worker aktif`}>{agents.map(agent => <i key={agent.id} className={!['offline', 'not_connected', 'not_installed'].includes(agent.status) ? 'active' : ''} />)}</div><div className="dock-status-actions"><div className="dock-status-workers">{agents.map(agent => <button key={agent.id} type="button" onClick={() => onSelect(agent.id)} title={agent.name} aria-label={`Buka ${agent.name}`} style={{ '--agent-color': agent.color }}>{agent.shortName}</button>)}</div></div></section>
    </div>;
}

function AgentDrawer({ agent, tasks, commands, enqueue, onClose }) {
    if (!agent) return null;
    const parent = systems.find(s => s.id === agent.parentSystem);
    const unavailable = ['not_installed', 'not_connected'].includes(agent.status);
    const room = officeStations[agent.currentStation]?.label || agent.zoneLabel;
    const recentTasks = tasks.filter(task => task.agentId === agent.id).slice(0, 5);
    const recentCommands = commands.filter(command => command.agentId === agent.id).slice(0, 3);
    const labels = { generate_feed: 'Generate Feed', generate_story: 'Generate Story', publish_last: 'Publish Last', generate_threads: 'Generate Threads', generate_article: 'Generate Article', publish_article: 'Publish Article', schedule_article: 'Schedule Article', run_weekly_analysis: 'Run Weekly Analysis', research_trends: 'Research Trends', research_topic: 'Research Topic', find_content_ideas: 'Find Content Ideas', analyze_sources: 'Analyze Sources' };
    const [queueState, setQueueState] = useState(null);
    const [payloads, setPayloads] = useState({});
    const activeCommand = commands.find(command => command.agentId === agent.id && ['queued', 'claimed', 'running'].includes(command.status));
    const payloadFor = (action) => Object.fromEntries((actionFields[action] || []).map(field => [field, payloads[action]?.[field] || '']).filter(([, value]) => value !== ''));
    const updatePayload = (action, field, value) => setPayloads(current => ({ ...current, [action]: { ...(current[action] || {}), [field]: value } }));
    const run = async action => { setQueueState('queuing'); try { const result = await enqueue(agent.id, action, payloadFor(action)); setQueueState(`${labels[action]} · ${result.status}`); } catch (error) { setQueueState(error.message); } };
    return <>
        <button className="drawer-scrim" onClick={onClose} aria-label="Tutup panel" />
        <aside className="agent-drawer">
            <div className="drawer-header"><div className="drawer-avatar" style={{ '--agent-color': agent.color }}>{agent.shortName}<i /></div><div><span>{parent?.name}</span><h2>{agent.name}</h2><p>{agent.fullRole}</p></div><button className="drawer-close" onClick={onClose}><X size={19} /></button></div>
            <div className="drawer-status"><span className={`status-pill ${statusTone(agent.status)}`}>{workerStates[agent.status]?.label || agent.status}</span><small><Clock3 size={13} /> {agent.lastActivity}</small></div>
            <div className="activity-state"><div><small>CURRENT ROOM</small><strong>{room}</strong></div><div><small>ACTIVITY</small><strong>{officeStateLabel[agent.officeState] || agent.officeState}</strong></div><div><small>ENERGY</small><strong>{Number.isFinite(agent.energy) ? `${agent.energy}%` : '—'}</strong><i><b style={{ width: `${agent.energy}%` }} /></i></div></div>
            {unavailable ? <div className="unavailable-card"><Database size={24} /><div><strong>NOT CONNECTED</strong><p>Integration pending. No command controls are available for this worker.</p></div><button disabled>Integration pending</button></div> : <>
                <section className="drawer-section"><label>CURRENT TASK</label><div className="drawer-task"><span><Activity size={18} /></span><div><strong>{agent.currentTask}</strong><small>{room}</small><div className="progress-track"><i style={{ width: `${agent.progress}%` }} /></div></div><b>{agent.progress}%</b></div></section>
                <section className="drawer-section"><label>SAFE COMMANDS</label><div className="command-grid">{agent.availableActions.map(action => <div className="command-card" key={action}>{(actionFields[action] || []).map(field => <input key={field} value={payloads[action]?.[field] || ''} onChange={event => updatePayload(action, field, event.target.value)} placeholder={field === 'sources' ? 'sources (comma separated or URLs)' : field} />)}<button onClick={() => run(action)} disabled={queueState === 'queuing' || !!activeCommand}><Play size={13} />{labels[action]}</button></div>)}</div>{queueState && <p className="queue-state">{queueState}</p>}{activeCommand && <p className="queue-state">A command is currently active: {labels[activeCommand.action] || activeCommand.action}</p>}</section>
                <section className="drawer-section"><label>CAPABILITIES</label><div className="capability-list">{agent.capabilities.map(item => <span key={item}><Check size={12} />{item}</span>)}</div></section>
                <section className="drawer-section"><label>RECENT TASKS / RESULTS</label>{recentTasks.length ? <div className="result-list">{recentTasks.map(item => <div className="result-row" key={item.id}><span><Box size={15} /></span><strong>{item.title}</strong><b className={`status-pill ${item.status === 'failed' ? 'red' : item.status === 'completed' ? 'green' : 'blue'}`}>{item.status}</b></div>)}</div> : <p className="drawer-empty">No tasks yet</p>}</section>
                <section className="drawer-section"><label>COMMAND HISTORY</label>{recentCommands.length ? <div className="command-history">{recentCommands.map(item => <p key={item.id}><span>{labels[item.action] || item.action}</span><b>{item.status}</b></p>)}</div> : <p className="drawer-empty">No commands queued yet</p>}</section>
            </>}
            <p className="drawer-note"><PanelLeftClose size={13} /> Commands are queued only. No shell or operating-system command is executed.</p>
        </aside>
    </>;
}

function App() {
    const { domainData, visualAgents: agents, activeEvent, simulateEvent, sendToRest, sendToServer } = useLivingOffice(agentRegistry);
    const office = useOfficeData();
    const ambience = useMemo(() => { const hour = new Date().getHours(); return hour >= 18 || hour < 6 ? 'night' : hour >= 16 ? 'evening' : 'day'; }, []);
    const [selectedId, setSelectedId] = useState('jauki-article');
    const [drawerOpen, setDrawerOpen] = useState(false);
    const mainRef = useRef(null);
    const [activeView, setActiveView] = useState(() => window.location.hash === '#kelola-planner' ? 'planner' : 'overview');
    const [theme, setTheme] = useState(() => {
        try { return localStorage.getItem('living-office-theme') === 'dark' ? 'dark' : 'light'; } catch { return 'light'; }
    });
    const domainRoster = useMemo(() => normalizeRoster(domainData.rows, domainData).workers, [domainData]);
    const displayAgents = domainData.loading ? agents : domainRoster;
    const selectedAgent = displayAgents.find(a => a.id === selectedId) || displayAgents[0];
    const selectAgent = (id) => { setSelectedId(id); setDrawerOpen(true); };
    useEffect(() => { try { localStorage.setItem('living-office-theme', theme); } catch {} }, [theme]);
    useEffect(() => {
        if (activeView === 'planner' && window.location.hash === '#kelola-planner') return;
        mainRef.current?.scrollTo({ top: 0, left: 0 });
        if (window.innerWidth <= 760) window.scrollTo({ top: 0, left: 0 });
    }, [activeView]);
    return <div className="app-shell office-redesign" data-theme={theme} data-ambience={ambience}>
        <Header summary={office.summary} notifications={office.notifications} unread={office.unread} onRead={office.markRead} onReadAll={office.markAllRead} onNotificationOpen={() => setActiveView('reports')} theme={theme} onThemeToggle={() => setTheme(current => current === 'light' ? 'dark' : 'light')} />
        <Sidebar activeView={activeView} setActiveView={setActiveView} />
        <main ref={mainRef} className={`main-stage ${activeView !== 'overview' ? 'view-mode' : ''}`}>{activeView === 'overview' ? <><OfficeRenderer domainData={domainData} selectedId={selectedId} onSelect={selectAgent}><OfficeMap agents={agents} selectedId={selectedId} onSelect={selectAgent} summary={office.summary} analystReport={office.reports[0]} activeEvent={activeEvent} onSimulateEvent={simulateEvent} onServerCheck={() => sendToServer('trent')} onRest={() => !['not_installed', 'not_connected'].includes(selectedAgent?.status) && selectedAgent && sendToRest(selectedAgent.id)} /></OfficeRenderer><BottomDock selectedAgent={selectedAgent} onSelect={selectAgent} agents={displayAgents} activity={office.activity} analystReport={office.reports[0]} onOpenReports={() => setActiveView('reports')} /></> : activeView === 'planner' ? <ContentPlanner planner={office.planner} brands={office.brands} loading={office.loading} loadPlanner={office.loadPlanner} createSchedule={office.createSchedule} analyzePlan={office.analyzePlan} contentItems={office.content} generateContent={office.generateContent} reviseContent={office.reviseContent} updateContent={office.updateContent} approveContent={office.approveContent} /> : activeView === 'reports' ? <AnalystReports reports={office.reports} brands={office.brands} loading={office.loading} generateReport={office.generateReport} approveReport={office.approveReport} dismissReport={office.dismissReport} /> : activeView === 'api' ? <ApiStatus agents={displayAgents} onSelectWorker={selectAgent} /> : <OperationsView view={activeView} agents={displayAgents} tasks={office.tasks} activity={office.activity} content={office.content} error={office.error} loading={office.loading} retry={office.refresh} onSelectWorker={selectAgent} />}</main>
        {drawerOpen && <AgentDrawer agent={selectedAgent} tasks={office.tasks} commands={office.commands} enqueue={office.enqueue} onClose={() => setDrawerOpen(false)} />}
        {office.toast && <div className={`office-toast ${office.toast.severity}`}><i>{office.toast.severity === 'error' ? '×' : '✓'}</i><div><strong>{office.toast.title}</strong><span>{office.toast.message}</span></div><button onClick={office.dismissToast}><X size={14} /></button></div>}
    </div>;
}

createRoot(document.getElementById('living-office-root')).render(<App />);
