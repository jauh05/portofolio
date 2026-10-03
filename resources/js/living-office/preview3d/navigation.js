// World units are metres. Furniture and navigation share these anchors.
export const SEAT = [-2.25, 0.05];
export const DOOR = { x: 3.35, z: 2, halfWidth: 0.8, radius: 0.27 };
export const DESKS = [-2.25, 1.15].map(x => ({ x, z: -1.05, width: 2.45, depth: 1.05 }));
export const ENTRY = [[3.35, 4], [3.35, 1.2], [-3.75, 1.2], [-3.75, -0.1], [-2.25, -0.1]];
export function createMotion(seated = true) {
    return { x: seated ? SEAT[0] : -3.8, z: seated ? SEAT[1] : 4, yaw: Math.PI, seated: seated ? 1 : 0, phase: seated ? 'seated' : 'idle', door: 0, route: [], index: 0, direction: null, reservations: new Set(), moving: false };
}
export function requestMove(m, direction) {
    if (!['seated', 'idle'].includes(m.phase)) return false;
    m.direction = direction; m.index = 0;
    if (direction === 'enter' && m.phase === 'idle') { m.route = ENTRY; m.phase = 'walking'; }
    else if (direction === 'exit' && m.phase === 'seated') { m.route = [...ENTRY].reverse().concat([[-3.8, 4]]); m.phase = 'standing'; }
    else return false;
    return true;
}
const approach = (v, target, amount) => v + Math.sign(target - v) * Math.min(Math.abs(target - v), amount);
export function stepMotion(m, dt, reduced = false) {
    dt = Math.min(dt, 0.05); m.moving = false; const previousZ = m.z;
    if (m.phase === 'standing') {
        m.seated = approach(m.seated, 0, dt * 1.25); m.z = -0.1 + 0.15 * m.seated;
        if (!m.seated) m.phase = 'walking';
    }
    const thresholdOccupied = Math.abs(m.x - DOOR.x) < 0.7 && Math.abs(m.z - DOOR.z) < 0.95;
    if (m.phase === 'walking' || m.phase === 'waiting') {
        const target = m.route[m.index];
        if (target) {
            const crossesDoor = (m.z - DOOR.z) * (target[1] - DOOR.z) < 0 && Math.abs(target[0] - DOOR.x) < 0.1;
            if (crossesDoor) m.reservations.add('preview-worker');
            if (crossesDoor && m.door < 0.96) m.phase = 'waiting';
            else {
                m.phase = 'walking';
                const dx = target[0] - m.x, dz = target[1] - m.z, distance = Math.hypot(dx, dz);
                const speed = reduced ? 5 : 1.12;
                if (distance < speed * dt) { m.x = target[0]; m.z = target[1]; m.index++; }
                else { m.x += dx / distance * speed * dt; m.z += dz / distance * speed * dt; m.moving = true; }
                if (distance > 0.02) { const wanted = Math.atan2(dx, dz); const diff = Math.atan2(Math.sin(wanted - m.yaw), Math.cos(wanted - m.yaw)); m.yaw += Math.sign(diff) * Math.min(Math.abs(diff), dt * 5); }
            }
        } else m.phase = m.direction === 'enter' ? 'sitting' : 'idle';
    }
    if ((previousZ - DOOR.z) * (m.z - DOOR.z) < 0) m.doorCrossed = true;
    const outsideSwing = m.z < 1.6 || m.z > 3.95 || m.x < 2.2 || m.x > 4.5;
    if (m.doorCrossed && outsideSwing) { m.reservations.delete('preview-worker'); m.doorCrossed = false; }
    const open = m.reservations.size > 0 || thresholdOccupied;
    m.door = approach(m.door, open ? 1 : 0, reduced ? 1 : dt * 1.6);
    if (m.phase === 'sitting') {
        const diff = Math.atan2(Math.sin(Math.PI - m.yaw), Math.cos(Math.PI - m.yaw)); m.yaw += diff * Math.min(1, dt * 8);
        if (Math.abs(diff) < 0.06) {
            m.seated = approach(m.seated, 1, dt * 1.25); m.z = -0.1 + 0.15 * m.seated;
            if (m.seated === 1) m.phase = 'seated';
        }
    }
    return m;
}
export function hitsDesk(x, z, radius = DOOR.radius) { return DESKS.some(d => Math.abs(x-d.x) < d.width/2+radius && Math.abs(z-d.z) < d.depth/2+radius); }
