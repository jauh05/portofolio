import React, { useEffect, useMemo, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import {
    Activity, AlertTriangle, ArrowLeft, BarChart3, Bell, Bot, Box, BriefcaseBusiness, CalendarDays,
    Check, ChevronRight, CirclePause, Clock3, Coffee, Cpu, Database, ExternalLink, FileText,
    DollarSign, Gauge, HardDrive, LayoutDashboard, ListTodo, MemoryStick, MessageCircle, MonitorCog, MoreHorizontal,
    PanelLeftClose, Play, Radio, Refrigerator, RotateCcw, Search, Server, Settings, Sparkles, Square,
    Thermometer, Users, X, ZoomIn, ZoomOut,
} from 'lucide-react';
import { agentRegistry, systems, tasks } from './agentRegistry';
import { FinanceService, TrentService } from './services';
import { workerStates } from './stateMachine';
import { OFFICE_WORLD, deskAssignments, officeEvents, officeStations } from './officeModel';
import { useLivingOffice } from './useLivingOffice';
import './living-office.css';

const statusTone = (status) => ({
    monitoring: 'cyan', generating: 'violet', working: 'blue', planning: 'green', completed: 'green',
    idle: 'slate', break: 'amber', error: 'red', warning: 'amber', reporting: 'amber', meeting: 'violet',
    analyzing: 'amber', offline: 'slate', not_installed: 'muted',
}[status] || 'slate');

function BrandMark() {
    return <div className="brand-mark" aria-hidden="true"><span /><Bot size={24} /></div>;
}

function Header({ agents }) {
    const active = agents.filter((a) => !['offline', 'not_installed'].includes(a.status)).length;
    const running = agents.filter((a) => ['working', 'generating', 'planning', 'monitoring', 'analyzing'].includes(a.status)).length;
    const [now, setNow] = useState(new Date());
    useEffect(() => { const timer = setInterval(() => setNow(new Date()), 1000); return () => clearInterval(timer); }, []);
    const metrics = [
        { icon: Radio, value: '2', label: 'Systems online', tone: 'green' },
        { icon: Users, value: active, label: 'Agents active', tone: 'cyan' },
        { icon: ListTodo, value: running, label: 'Tasks running', tone: 'blue' },
        { icon: Check, value: '8', label: 'Completed today', tone: 'violet' },
        { icon: AlertTriangle, value: '0', label: 'Errors', tone: 'red' },
    ];
    return <header className="topbar">
        <a href="/" className="brand"><BrandMark /><div><strong>Jauhar Bot HQ</strong><span><i /> All systems operational</span></div></a>
        <div className="top-metrics">{metrics.map(({ icon: Icon, value, label, tone }) => <div className="top-metric" key={label}>
            <span className={`metric-icon ${tone}`}><Icon size={16} /></span><strong>{value}</strong><small>{label}</small>
        </div>)}</div>
        <div className="header-user"><div className="current-time"><small>{now.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })}</small><strong>{now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':')}</strong></div><button className="icon-button"><Bell size={18} /><i /></button><div className="avatar">JF</div><div className="user-copy"><strong>Jauhar</strong><small>Owner</small></div></div>
    </header>;
}

const navItems = [
    ['office', LayoutDashboard, 'Office'], ['agents', Users, 'Agents'], ['tasks', ListTodo, 'Tasks'],
    ['systems', Server, 'Systems'], ['analytics', BarChart3, 'Analytics'], ['settings', Settings, 'Settings'],
];

function Sidebar({ activeView, setActiveView, agents }) {
    return <aside className="sidebar"><nav>{navItems.map(([id, Icon, label]) => <button key={id} className={activeView === id ? 'active' : ''} onClick={() => setActiveView(id)}><Icon size={20} /><span>{label}</span>{id === 'agents' && <em>{agents.length}</em>}</button>)}</nav><a className="back-site" href="/"><ArrowLeft size={18} /><span>Portfolio</span></a></aside>;
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
    return <div className={`desk-pod desk-${assignment.role} ${agent.status === 'not_installed' ? 'inactive' : ''}`} style={{ left: `${seat.x}%`, top: `${seat.y}%` }}>
        <span className="desk-label"><i style={{ background: agent.color }} />{agent.name.replace(' Worker', '')}</span>
        <Workstation variant={deskVariants[agent.id]} />
        <span className="desk-chair" /><span className="desk-foreground" />
        {agent.status === 'not_installed' && <span className="desk-offline">NOT INSTALLED</span>}
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

function CentralMonitor({ agents, financeData }) {
    const running = agents.filter(a => ['working', 'generating', 'planning', 'monitoring', 'analyzing'].includes(a.status)).length;
    return <div className="central-monitor"><div className="monitor-heading"><span><Activity size={14} /> CENTRAL OPERATIONS · LIVE</span><i><span /> HEALTHY</i></div><div className="monitor-grid"><div><small>Systems online</small><strong>2</strong><em>+ 2 pending</em></div><div><small>Running tasks</small><strong>{running}</strong><span className="mini-chart"><i /><i /><i /><i /><i /><i /></span></div><div><small>Completed</small><strong>08</strong><em>Today</em></div><div><small>Errors</small><strong>0</strong><em className="ok">No incidents</em></div><div><small>Server</small><strong>98.7%</strong><span className="signal-bars"><i /><i /><i /><i /><i /></span></div><div><small>AI cost today</small><strong>${financeData?.today?.toFixed(2) || '—'}</strong><em>Mock</em></div><div><small>Content today</small><strong>12</strong><em>4 published</em></div></div></div>;
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

function OfficeMap({ agents, selectedId, onSelect, financeData, activeEvent, onSimulateEvent, onServerCheck, onRest }) {
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
                    <OfficeDecor /><OpenOffice agents={agents} /><CentralMonitor agents={agents} financeData={financeData} />
                    <OfficeZone className="zone-server" label="SERVER ROOM" icon={Server} variant="servers" capacity={2}><span className="temp-monitor">TEMP 48°C</span></OfficeZone>
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

function AgentListView({ agents, selectedId, onSelect, liveSystems = systems }) {
    return <div className="content-view"><div className="view-heading"><div><span>WORKFORCE DIRECTORY</span><h1>AI Agents</h1><p>Logical workers grouped by their real parent system.</p></div><div className="view-count">{agents.length}<small>total workers</small></div></div>{liveSystems.map(system => <section className="system-group" key={system.id}><div className="system-group-title"><i style={{ background: system.color }} /><div><strong>{system.name}</strong><small>{system.label}</small></div><span className={system.status}>{system.status.replace('_', ' ')}</span></div><div className="agent-card-grid">{agents.filter(a => a.parentSystem === system.id).map(agent => <button key={agent.id} className="agent-card" onClick={() => onSelect(agent.id)}><span className="agent-card-avatar" style={{ '--agent-color': agent.color }}>{agent.shortName}</span><div><strong>{agent.name}</strong><small>{agent.fullRole}</small><p>{agent.currentTask}</p></div><span className={`status-pill ${statusTone(agent.status)}`}>{workerStates[agent.status].label}</span><ChevronRight size={17} /></button>)}</div></section>)}</div>;
}

function GenericView({ view, agents, tasks: liveTasks = tasks, systems: liveSystems = systems }) {
    const title = { tasks: 'Task Operations', systems: 'Connected Systems', analytics: 'Operations Analytics', settings: 'Office Settings' }[view];
    if (view === 'tasks') return <div className="content-view"><div className="view-heading"><div><span>LIVE WORK QUEUE</span><h1>{title}</h1><p>Task records follow the shared extensible task model.</p></div></div><div className="task-table"><div className="task-row task-head"><span>Task</span><span>Worker</span><span>Status</span><span>Progress</span></div>{liveTasks.map(task => { const agent = agents.find(a => a.id === task.agentId); return <div className="task-row" key={task.id}><span><i className="task-icon"><FileText size={16} /></i>{task.title}</span><span>{agent?.name}</span><span><b className={`status-pill ${statusTone(agent?.status)}`}>{task.status}</b></span><span><div className="task-progress"><i style={{ width: `${task.progress}%` }} /></div>{task.progress}%</span></div>})}</div></div>;
    return <div className="content-view"><div className="view-heading"><div><span>LIVING OFFICE</span><h1>{title}</h1><p>{view === 'systems' ? 'Backend services and logical workers remain intentionally separate.' : 'This Phase 1 surface is ready for a private backend adapter.'}</p></div></div><div className="placeholder-grid">{(view === 'systems' ? liveSystems : agents.slice(0, 3)).map(item => <article key={item.id}><span><Server size={20} /></span><strong>{item.name}</strong><small>{item.label || item.role}</small><b className={item.status}>{(item.status || 'adapter ready').replace('_', ' ')}</b></article>)}</div></div>;
}

function BottomDock({ selectedAgent, onSelect, agents }) {
    const activities = [
        ['12:31', 'Trent', 'Server health check completed'], ['12:25', 'Weekly Planner', 'Weekly theme prepared'],
        ['12:19', 'Social Worker', 'Instagram cover generated'], ['12:15', 'Article Worker', 'Kauiz article started'],
    ];
    return <div className="bottom-dock"><section className="dock-agent"><div className="panel-label">AGENT SAAT INI <button><MoreHorizontal size={15} /></button></div><div className="current-agent"><span className="mini-avatar" style={{ '--agent-color': selectedAgent.color }}>{selectedAgent.shortName}</span><div><strong>{selectedAgent.name}</strong><small>{selectedAgent.role}</small><span className={`status-pill ${statusTone(selectedAgent.status)}`}>{workerStates[selectedAgent.status].label}</span></div></div></section><section className="dock-task"><div className="panel-label">TASK AKTIF <small>LIVE</small></div><div className="active-task"><span><FileText size={19} /></span><div><strong>{selectedAgent.currentTask}</strong><small>{selectedAgent.parentSystem}</small><div className="progress-track"><i style={{ width: `${selectedAgent.progress}%` }} /></div></div><b>{selectedAgent.progress}%</b></div></section><section className="dock-feed"><div className="panel-label">AKTIVITAS TERBARU <button><ChevronRight size={15} /></button></div><div className="feed-list">{activities.slice(0, 2).map(([time, name, action]) => <div key={time}><time>{time}</time><i /><p><strong>{name}</strong> {action}</p></div>)}</div></section><section className="dock-team"><div className="panel-label">WORKER ONLINE <small>{agents.filter(a => a.status !== 'not_installed').length}/{agents.length}</small></div><div className="avatar-stack">{agents.map(a => <button key={a.id} style={{ '--agent-color': a.color }} onClick={() => onSelect(a.id)} className={a.status === 'not_installed' ? 'disabled' : ''}>{a.shortName}</button>)}</div><small>Klik avatar untuk lihat detail</small></section></div>;
}

function Metric({ icon: Icon, label, value, suffix = '%' }) { return <div className="server-metric"><span><Icon size={16} /></span><div><small>{label}</small><strong>{value}{typeof value === 'number' ? suffix : ''}</strong><i><b style={{ width: typeof value === 'number' ? `${value}%` : '100%' }} /></i></div></div>; }

function AgentDrawer({ agent, serverData, financeData, onClose }) {
    if (!agent) return null;
    const parent = systems.find(s => s.id === agent.parentSystem);
    const unavailable = agent.status === 'not_installed';
    const room = officeStations[agent.currentStation]?.label || agent.zoneLabel;
    return <>
        <button className="drawer-scrim" onClick={onClose} aria-label="Tutup panel" />
        <aside className="agent-drawer">
            <div className="drawer-header"><div className="drawer-avatar" style={{ '--agent-color': agent.color }}>{agent.shortName}<i /></div><div><span>{parent?.name}</span><h2>{agent.name}</h2><p>{agent.fullRole}</p></div><button className="drawer-close" onClick={onClose}><X size={19} /></button></div>
            <div className="drawer-status"><span className={`status-pill ${statusTone(agent.status)}`}>{workerStates[agent.status].label}</span><small><Clock3 size={13} /> {agent.lastActivity}</small></div>
            <div className="activity-state"><div><small>CURRENT ROOM</small><strong>{room}</strong></div><div><small>ACTIVITY</small><strong>{officeStateLabel[agent.officeState] || agent.officeState}</strong></div><div><small>ENERGY</small><strong>{agent.energy}%</strong><i><b style={{ width: `${agent.energy}%` }} /></i></div></div>
            {unavailable ? <div className="unavailable-card"><Database size={24} /><div><strong>Worker belum tersedia</strong><p>Tidak ada backend palsu yang dijalankan. Registry dan workstation sudah siap untuk adapter di fase berikutnya.</p></div><button disabled>Setup Later</button></div> : <>
                <section className="drawer-section"><label>CURRENT TASK</label><div className="drawer-task"><span><Activity size={18} /></span><div><strong>{agent.currentTask}</strong><small>{room}</small><div className="progress-track"><i style={{ width: `${agent.progress}%` }} /></div></div><b>{agent.progress}%</b></div></section>
                {agent.id === 'trent' && serverData && <section className="drawer-section"><label>SERVER HEALTH · ISOLATED MOCK</label><div className="server-grid"><Metric icon={Cpu} label="CPU" value={serverData.cpu} /><Metric icon={MemoryStick} label="RAM" value={serverData.ram} /><Metric icon={HardDrive} label="Disk" value={serverData.disk} /><Metric icon={Thermometer} label="Temp" value={`${serverData.temperature}°C`} suffix="" /></div><div className="service-line"><span>Nginx <b>{serverData.nginx}</b></span><span>Docker <b>{serverData.docker}</b></span><span>Uptime <b>{serverData.uptime}</b></span></div></section>}
                {agent.id === 'finance-analyst' && financeData && <section className="drawer-section finance-summary"><label>FINANCE SNAPSHOT · ISOLATED MOCK</label><div className="finance-grid"><div><small>AI cost today</small><strong>${financeData.today.toFixed(2)}</strong></div><div><small>This month</small><strong>${financeData.month.toFixed(2)}</strong></div><div><small>API cost</small><strong>${financeData.api.toFixed(2)}</strong></div><div><small>Avg / task</small><strong>${financeData.averagePerTask.toFixed(2)}</strong></div></div><p>Integration pending — no provider billing account is connected.</p></section>}
                <section className="drawer-section"><label>CAPABILITIES</label><div className="capability-list">{agent.capabilities.map(item => <span key={item}><Check size={12} />{item}</span>)}</div></section>
                <section className="drawer-section"><label>RECENT RESULTS</label><div className="result-list">{agent.recentResults.map(item => <button key={item}><span><Box size={15} /></span><strong>{item}</strong><ExternalLink size={14} /></button>)}</div></section>
            </>}
            <div className="drawer-actions"><button disabled><Play size={16} />Run <small>Coming soon</small></button><button disabled><Coffee size={16} />Send to break <small>Soon</small></button><button disabled><MonitorCog size={16} />View logs <small>Soon</small></button></div>
            <p className="drawer-note"><PanelLeftClose size={13} /> Commands stay disabled until a private backend adapter is connected.</p>
        </aside>
    </>;
}

function App() {
    const { visualAgents: agents, activeEvent, simulateEvent, sendToRest, sendToServer } = useLivingOffice(agentRegistry);
    const ambience = useMemo(() => { const hour = new Date().getHours(); return hour >= 18 || hour < 6 ? 'night' : hour >= 16 ? 'evening' : 'day'; }, []);
    const [selectedId, setSelectedId] = useState('jauki-article');
    const [drawerOpen, setDrawerOpen] = useState(false);
    const [activeView, setActiveView] = useState('office');
    const [serverData, setServerData] = useState(null);
    const [financeData, setFinanceData] = useState(null);
    const [liveTasks, setLiveTasks] = useState(tasks);
    const [liveSystems, setLiveSystems] = useState(systems);

    const selectedAgent = useMemo(() => agents.find(a => a.id === selectedId) || agents[0], [agents, selectedId]);
    useEffect(() => { TrentService.getServerStatus().then(setServerData); FinanceService.getCostSummary().then(setFinanceData); }, []);
    
    // Poll tasks & systems
    useEffect(() => {
        const pollSystems = async () => {
            try {
                const [sysRes, taskRes] = await Promise.all([fetch('/api/office/system-status'), fetch('/api/office/tasks')]);
                if (sysRes.ok) {
                    const data = await sysRes.json();
                    setLiveSystems(current => current.map(s => data[s.id] ? { ...s, status: data[s.id].status } : s));
                }
                if (taskRes.ok) {
                    const data = await taskRes.json();
                    setLiveTasks(data.length ? data : tasks);
                }
            } catch (err) {}
        };
        pollSystems();
        const intv = setInterval(pollSystems, 5000);
        return () => clearInterval(intv);
    }, []);

    const selectAgent = (id) => { setSelectedId(id); setDrawerOpen(true); };
    return <div className="app-shell" data-ambience={ambience}><Header agents={agents} /><Sidebar activeView={activeView} setActiveView={setActiveView} agents={agents} /><main className={`main-stage ${activeView !== 'office' ? 'view-mode' : ''}`}>{activeView === 'office' ? <><OfficeMap agents={agents} selectedId={selectedId} onSelect={selectAgent} financeData={financeData} activeEvent={activeEvent} onSimulateEvent={simulateEvent} onServerCheck={() => sendToServer('trent')} onRest={() => selectedAgent.status !== 'not_installed' && sendToRest(selectedAgent.id)} /><BottomDock selectedAgent={selectedAgent} onSelect={selectAgent} agents={agents} /></> : activeView === 'agents' ? <AgentListView agents={agents} selectedId={selectedId} onSelect={selectAgent} liveSystems={liveSystems} /> : <GenericView view={activeView} agents={agents} tasks={liveTasks} systems={liveSystems} />}</main>{drawerOpen && <AgentDrawer agent={selectedAgent} serverData={serverData} financeData={financeData} onClose={() => setDrawerOpen(false)} />}</div>;
}

createRoot(document.getElementById('living-office-root')).render(<App />);
