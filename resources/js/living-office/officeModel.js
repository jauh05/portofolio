export const OFFICE_WORLD = { width: 1500, height: 660 };

// The floor plan and the visual station model share room ids. Occupancy is
// always derived from the live workers, never stored as a separate status.
export const officeRooms = [
    { id: 'open', label: 'Open Office', icon: 'users', capacity: 4, tone: 'blue' },
    { id: 'content', label: 'Content Studio', icon: 'file', capacity: 2, tone: 'sky' },
    { id: 'social', label: 'Social Room', icon: 'message', capacity: 2, tone: 'lavender' },
    { id: 'server', label: 'Server Room', icon: 'server', capacity: 2, tone: 'blue' },
    { id: 'analyst', label: 'Analyst Room', icon: 'chart', capacity: 2, tone: 'sky' },
    { id: 'threads', label: 'Threads Room', icon: 'message', capacity: 2, tone: 'lavender' },
    { id: 'meeting', label: 'Meeting Room', icon: 'users', capacity: 8, tone: 'blue' },
    { id: 'article', label: 'Article Room', icon: 'file', capacity: 2, tone: 'sky' },
    { id: 'lounge', label: 'Rest Lounge', icon: 'coffee', capacity: 4, tone: 'lavender' },
    { id: 'control', label: 'Control Center', icon: 'control', capacity: 2, tone: 'blue' },
];

export const officeStations = {
    deskTrent: { id: 'deskTrent', label: 'Open Office Desk', x: 16, y: 19, capacity: 1, type: 'desk', roomId: 'open', facing: 'right' },
    deskSocial: { id: 'deskSocial', label: 'Social Desk', x: 67, y: 19, capacity: 1, type: 'desk', roomId: 'social', facing: 'left' },
    deskThreads: { id: 'deskThreads', label: 'Threads Desk', x: 42, y: 51, capacity: 1, type: 'desk', roomId: 'threads', facing: 'left' },
    deskArticle: { id: 'deskArticle', label: 'Article Desk', x: 18, y: 82, capacity: 1, type: 'desk', roomId: 'article', facing: 'right' },
    deskPlanner: { id: 'deskPlanner', label: 'Content Studio Desk', x: 45, y: 19, capacity: 1, type: 'desk', roomId: 'content', facing: 'left' },
    deskData: { id: 'deskData', label: 'Data Analyst Desk', x: 18, y: 51, capacity: 1, type: 'desk', roomId: 'analyst', facing: 'right' },
    deskFinance: { id: 'deskFinance', label: 'Finance Desk', x: 84, y: 82, capacity: 1, type: 'desk', roomId: 'control', facing: 'left' },

    hallwayNorth: { id: 'hallwayNorth', label: 'North Hallway', x: 56, y: 35, capacity: 5, type: 'waypoint' },
    hallwayCenter: { id: 'hallwayCenter', label: 'Central Hallway', x: 56, y: 66, capacity: 6, type: 'waypoint' },
    hallwaySouth: { id: 'hallwaySouth', label: 'South Hallway', x: 79, y: 66, capacity: 5, type: 'waypoint' },
    openNorth: { id: 'openNorth', label: 'West Hallway', x: 25, y: 35, capacity: 5, type: 'waypoint' },
    openSouth: { id: 'openSouth', label: 'West South Hallway', x: 25, y: 66, capacity: 5, type: 'waypoint' },

    serverEntry: { id: 'serverEntry', label: 'Server Entry', x: 79, y: 35, capacity: 2, type: 'waypoint' },
    serverMonitor: { id: 'serverMonitor', label: 'Server Monitor', x: 89, y: 19, capacity: 2, type: 'server', roomId: 'server', facing: 'right' },
    meetingEntry: { id: 'meetingEntry', label: 'Meeting Entry', x: 79, y: 66, capacity: 4, type: 'waypoint' },
    meetingSeat1: { id: 'meetingSeat1', label: 'Meeting Seat 1', x: 69, y: 45, capacity: 1, type: 'meeting', roomId: 'meeting', facing: 'right' },
    meetingSeat2: { id: 'meetingSeat2', label: 'Meeting Seat 2', x: 74, y: 45, capacity: 1, type: 'meeting', roomId: 'meeting', facing: 'left' },
    meetingSeat3: { id: 'meetingSeat3', label: 'Meeting Seat 3', x: 79, y: 45, capacity: 1, type: 'meeting', roomId: 'meeting', facing: 'right' },
    meetingSeat4: { id: 'meetingSeat4', label: 'Meeting Seat 4', x: 84, y: 45, capacity: 1, type: 'meeting', roomId: 'meeting', facing: 'left' },
    meetingSeat5: { id: 'meetingSeat5', label: 'Meeting Seat 5', x: 69, y: 57, capacity: 1, type: 'meeting', roomId: 'meeting', facing: 'right' },
    meetingSeat6: { id: 'meetingSeat6', label: 'Meeting Seat 6', x: 74, y: 57, capacity: 1, type: 'meeting', roomId: 'meeting', facing: 'left' },
    meetingSeat7: { id: 'meetingSeat7', label: 'Meeting Seat 7', x: 79, y: 57, capacity: 1, type: 'meeting', roomId: 'meeting', facing: 'right' },
    meetingSeat8: { id: 'meetingSeat8', label: 'Meeting Seat 8', x: 84, y: 57, capacity: 1, type: 'meeting', roomId: 'meeting', facing: 'left' },

    loungeEntry: { id: 'loungeEntry', label: 'Lounge Entry', x: 56, y: 66, capacity: 3, type: 'waypoint' },
    sofa1: { id: 'sofa1', label: 'Lounge Sofa', x: 38, y: 79, capacity: 1, type: 'rest', roomId: 'lounge', facing: 'right' },
    sofa2: { id: 'sofa2', label: 'Lounge Sofa', x: 44, y: 79, capacity: 1, type: 'rest', roomId: 'lounge', facing: 'left' },
    beanBag1: { id: 'beanBag1', label: 'Bean Bag', x: 38, y: 88, capacity: 1, type: 'rest', roomId: 'lounge', facing: 'right' },
    daybed1: { id: 'daybed1', label: 'Daybed', x: 45, y: 88, capacity: 1, type: 'rest', roomId: 'lounge', facing: 'left' },
};

export const deskAssignments = {
    trent: { homeDeskId: 'deskTrent', deskSeat: officeStations.deskTrent, role: 'trent' },
    'jauki-social': { homeDeskId: 'deskSocial', deskSeat: officeStations.deskSocial, role: 'social' },
    'jauki-threads': { homeDeskId: 'deskThreads', deskSeat: officeStations.deskThreads, role: 'threads' },
    'jauki-article': { homeDeskId: 'deskArticle', deskSeat: officeStations.deskArticle, role: 'article' },
    'jauki-planner': { homeDeskId: 'deskPlanner', deskSeat: officeStations.deskPlanner, role: 'planner' },
    'jauki-analyst': { homeDeskId: 'deskData', deskSeat: officeStations.deskData, role: 'data' },
    'finance-analyst': { homeDeskId: 'deskFinance', deskSeat: officeStations.deskFinance, role: 'finance' },
};

export const meetingSeats = ['meetingSeat1', 'meetingSeat2', 'meetingSeat3', 'meetingSeat4', 'meetingSeat5', 'meetingSeat6', 'meetingSeat7', 'meetingSeat8'];
export const restSeats = ['sofa1', 'sofa2', 'beanBag1', 'daybed1'];

export const officeEvents = [
    { id: 'weekly_content_meeting', label: 'Content Meeting', participants: ['jauki-social', 'jauki-threads', 'jauki-article', 'jauki-planner'], message: 'Planning weekly content...', duration: 42 },
    { id: 'monthly_finance_review', label: 'Finance Meeting', participants: ['finance-analyst', 'trent', 'jauki-planner'], message: 'Reviewing AI operating cost...', duration: 46 },
    { id: 'full_team_meeting', label: 'Full Team Meeting', participants: ['trent', 'jauki-social', 'jauki-threads', 'jauki-article', 'jauki-planner', 'finance-analyst'], message: 'Team review in progress...', duration: 52 },
];

const deskToOpenAnchor = {
    deskTrent: 'openNorth', deskSocial: 'serverEntry', deskThreads: 'hallwayNorth',
    deskArticle: 'openSouth', deskPlanner: 'hallwayNorth', deskData: 'openNorth', deskFinance: 'hallwaySouth',
};

const graph = {
    openNorth: ['openSouth', 'hallwayNorth', 'hallwayCenter'],
    openSouth: ['openNorth', 'hallwayCenter', 'hallwaySouth'],
    hallwayNorth: ['openNorth', 'hallwayCenter', 'serverEntry'],
    hallwayCenter: ['openNorth', 'openSouth', 'hallwayNorth', 'hallwaySouth', 'meetingEntry'],
    hallwaySouth: ['openSouth', 'hallwayCenter', 'loungeEntry'],
    serverEntry: ['hallwayNorth', 'serverMonitor'], serverMonitor: ['serverEntry'],
    meetingEntry: ['hallwayCenter', ...meetingSeats],
    loungeEntry: ['hallwaySouth', ...restSeats],
    ...Object.fromEntries(meetingSeats.map((seat) => [seat, ['meetingEntry']])),
    ...Object.fromEntries(restSeats.map((seat) => [seat, ['loungeEntry']])),
};

for (const [deskId, anchor] of Object.entries(deskToOpenAnchor)) {
    graph[deskId] = [anchor];
    graph[anchor].push(deskId);
}

export const stationPosition = (stationId) => {
    const station = officeStations[stationId] || officeStations.deskArticle;
    return { x: station.x, y: station.y };
};

export const routeBetween = (fromId, toId) => {
    if (fromId === toId) return [toId];
    const queue = [[fromId]];
    const visited = new Set([fromId]);
    while (queue.length) {
        const path = queue.shift();
        const current = path.at(-1);
        for (const next of graph[current] || []) {
            if (visited.has(next)) continue;
            const nextPath = [...path, next];
            if (next === toId) return nextPath.slice(1);
            visited.add(next);
            queue.push(nextPath);
        }
    }
    return [toId];
};
