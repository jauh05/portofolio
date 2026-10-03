import React from 'react';
import { BarChart3, Coffee, FileText, MessageCircle, MonitorCog, Server, Users } from 'lucide-react';
import { officeRooms, officeStations } from './officeModel';

const icons = { chart: BarChart3, coffee: Coffee, control: MonitorCog, file: FileText, message: MessageCircle, server: Server, users: Users };
const unavailable = new Set(['offline', 'not_connected', 'not_installed']);

export function workersInRoom(roomId, agents) {
    return agents.filter((agent) => agent.officeState !== 'walking'
        && officeStations[agent.currentStation]?.roomId === roomId
        && !unavailable.has(agent.status));
}

function Plant({ place = '' }) {
    return <span className={`diorama-plant ${place}`}><i /><i /><i /><b /></span>;
}

function Monitor({ type = 'code' }) {
    return <span className={`diorama-monitor type-${type}`}><i /><b /></span>;
}

function DeskStation({ place, type = 'code' }) {
    return <span className={`diorama-station ${place}`}><span className="diorama-chair" /><span className="diorama-desk"><Monitor type={type} /><i className="diorama-keyboard" /><i className="diorama-cup" /></span></span>;
}

function ServerRack({ place }) {
    return <span className={`diorama-rack ${place}`}><i /><i /><i /><b /></span>;
}

function RoomFurniture({ roomId }) {
    return <span className={`diorama-furniture furniture-${roomId}`} aria-hidden="true">
        {roomId === 'open' && <><DeskStation place="prop-left" type="code" /><DeskStation place="prop-right" type="code" /><span className="diorama-window window-a" /><Plant place="plant-a" /><Plant place="plant-b" /></>}
        {roomId === 'content' && <><span className="diorama-board board-content"><i /><i /><i /></span><span className="diorama-shelf shelf-a"><i /><i /><i /></span><Plant place="plant-a" /></>}
        {roomId === 'social' && <><span className="diorama-board board-social"><i /><i /><i /></span><Plant place="plant-a" /><span className="diorama-window window-a" /></>}
        {roomId === 'server' && <><ServerRack place="rack-a" /><ServerRack place="rack-b" /><ServerRack place="rack-c" /><Plant place="plant-a" /></>}
        {roomId === 'analyst' && <><span className="diorama-board board-analyst"><i /><i /><i /></span><Plant place="plant-a" /></>}
        {roomId === 'threads' && <><span className="diorama-board board-threads"><i /><i /><i /></span><Plant place="plant-a" /></>}
        {roomId === 'meeting' && <><span className="diorama-board board-meeting"><i /><i /><i /></span><span className="diorama-meeting-table"><i /><i /><i /><i /><i /><i /></span><Plant place="plant-a" /></>}
        {roomId === 'article' && <><span className="diorama-shelf shelf-article"><i /><i /><i /></span><Plant place="plant-a" /></>}
        {roomId === 'lounge' && <><span className="diorama-sofa sofa-a"><i /></span><span className="diorama-sofa sofa-b"><i /></span><span className="diorama-coffee-table"><i /></span><span className="diorama-shelf shelf-lounge"><i /><i /><i /></span><Plant place="plant-a" /></>}
        {roomId === 'control' && <><span className="diorama-board board-control"><i /><i /><i /></span><DeskStation place="prop-control" type="chart" /><Plant place="plant-a" /></>}
    </span>;
}

function OfficeRoom({ room, occupied, selected, doorOpen, onRoomSelect }) {
    const Icon = icons[room.icon];
    return <button type="button" className={`floor-room floor-room-${room.id} ${selected ? 'selected' : ''} ${doorOpen ? 'door-open' : ''}`} onClick={() => onRoomSelect(room.id)} aria-pressed={selected} aria-label={`${room.label}, ${occupied} dari ${room.capacity} posisi aktif`}>
        <span className="diorama-world" aria-hidden="true">
            <span className="diorama-floor" />
            <span className="diorama-wall wall-back"><i /></span>
            <span className="diorama-wall wall-left" />
            <span className="diorama-wall wall-right" />
            <span className="diorama-wall wall-front front-left" />
            <span className="diorama-wall wall-front front-right" />
            <RoomFurniture roomId={room.id} />
        </span>
        <span className="office-door" aria-hidden="true"><i className="office-door-frame" /><i className="office-door-leaf" /><i className="office-door-light" /></span>
        <span className="floor-room-head"><span className="floor-room-icon"><Icon size={20} strokeWidth={2} /></span><strong>{room.label}</strong><span className={`floor-room-status ${occupied ? 'occupied' : ''}`}><i />{occupied}/{room.capacity}</span></span>
    </button>;
}

export function OfficeFloor({ agents, selectedRoomId, onRoomSelect }) {
    return <div className="office-floor">
        <span className="office-building-floor" aria-hidden="true" />
        <span className="office-corridor corridor-north" aria-hidden="true" />
        <span className="office-corridor corridor-south" aria-hidden="true" />
        <span className="office-corridor corridor-west" aria-hidden="true" />
        <span className="office-corridor corridor-center" aria-hidden="true" />
        <span className="office-corridor corridor-east" aria-hidden="true" />
        {officeRooms.map((room) => <OfficeRoom key={room.id} room={room} occupied={workersInRoom(room.id, agents).length} selected={selectedRoomId === room.id} doorOpen={agents.some((agent) => {
            if (agent.movementState !== 'walking' || unavailable.has(agent.status)) return false;
            const from = officeStations[agent.currentStation]?.roomId;
            const to = officeStations[agent.targetLocation]?.roomId;
            return from !== to && (from === room.id || to === room.id);
        })} onRoomSelect={onRoomSelect} />)}
    </div>;
}

export function OfficeRoomRail({ agents, selectedRoomId, onRoomSelect, onSelectWorker }) {
    const selectedRoom = officeRooms.find((room) => room.id === selectedRoomId) || officeRooms[0];
    const present = workersInRoom(selectedRoom.id, agents);
    return <aside className="office-room-rail" aria-label="Daftar ruang kantor">
        <div className="room-rail-heading"><span>RUANG KANTOR</span><strong>{officeRooms.length} ruang</strong></div>
        <div className="room-rail-list">
            {officeRooms.map((room) => {
                const Icon = icons[room.icon];
                const occupied = workersInRoom(room.id, agents).length;
                return <button type="button" key={room.id} className={`room-rail-item ${selectedRoom.id === room.id ? 'selected' : ''}`} onClick={() => onRoomSelect(room.id)} aria-pressed={selectedRoom.id === room.id}>
                    <span className={`room-rail-icon tone-${room.tone} room-rail-preview-${room.id}`}><Icon size={21} strokeWidth={1.8} /></span>
                    <span className="room-rail-copy"><strong>{room.label}</strong><small>{occupied}/{room.capacity} posisi aktif</small></span>
                    <span className="room-rail-arrow" aria-hidden="true">›</span>
                </button>;
            })}
        </div>
        <div className="room-rail-detail"><span>SEDANG DI RUANG INI</span><strong>{selectedRoom.label}</strong>
            {present.length ? <div className="room-rail-workers">{present.map((agent) => <button type="button" key={agent.id} onClick={() => onSelectWorker(agent.id)}><span style={{ '--agent-color': agent.color }}>{agent.shortName}</span><b>{agent.name}</b></button>)}</div> : <p>Belum ada worker aktif di ruangan ini.</p>}
        </div>
    </aside>;
}
