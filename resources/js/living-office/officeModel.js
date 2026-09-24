export const OFFICE_WORLD = { width: 1800, height: 1050 };

export const officeStations = {
    deskTrent: { id: 'deskTrent', label: 'Trent Desk', x: 22, y: 26, capacity: 1, type: 'desk', facing: 'right' },
    deskSocial: { id: 'deskSocial', label: 'Social Desk', x: 40, y: 24, capacity: 1, type: 'desk', facing: 'left' },
    deskThreads: { id: 'deskThreads', label: 'Threads Desk', x: 59, y: 27, capacity: 1, type: 'desk', facing: 'left' },
    deskArticle: { id: 'deskArticle', label: 'Article Desk', x: 29, y: 53, capacity: 1, type: 'desk', facing: 'right' },
    deskPlanner: { id: 'deskPlanner', label: 'Planner Desk', x: 48, y: 50, capacity: 1, type: 'desk', facing: 'left' },
    deskData: { id: 'deskData', label: 'Data Analyst Desk', x: 64, y: 55, capacity: 1, type: 'desk', facing: 'left' },
    deskFinance: { id: 'deskFinance', label: 'Finance Desk', x: 56, y: 77, capacity: 1, type: 'desk', facing: 'left' },

    hallwayNorth: { id: 'hallwayNorth', label: 'North Hallway', x: 70, y: 25, capacity: 5, type: 'waypoint' },
    hallwayCenter: { id: 'hallwayCenter', label: 'Central Hallway', x: 70, y: 52, capacity: 6, type: 'waypoint' },
    hallwaySouth: { id: 'hallwaySouth', label: 'South Hallway', x: 70, y: 78, capacity: 5, type: 'waypoint' },
    openNorth: { id: 'openNorth', label: 'Open Office North', x: 52, y: 37, capacity: 5, type: 'waypoint' },
    openSouth: { id: 'openSouth', label: 'Open Office South', x: 45, y: 66, capacity: 5, type: 'waypoint' },

    serverEntry: { id: 'serverEntry', label: 'Server Entry', x: 76, y: 25, capacity: 2, type: 'waypoint' },
    serverMonitor: { id: 'serverMonitor', label: 'Server Monitor', x: 86, y: 19, capacity: 2, type: 'server', facing: 'right' },
    meetingEntry: { id: 'meetingEntry', label: 'Meeting Entry', x: 76, y: 52, capacity: 4, type: 'waypoint' },
    meetingSeat1: { id: 'meetingSeat1', label: 'Meeting Seat 1', x: 84, y: 43, capacity: 1, type: 'meeting', facing: 'right' },
    meetingSeat2: { id: 'meetingSeat2', label: 'Meeting Seat 2', x: 90, y: 44, capacity: 1, type: 'meeting', facing: 'left' },
    meetingSeat3: { id: 'meetingSeat3', label: 'Meeting Seat 3', x: 84, y: 53, capacity: 1, type: 'meeting', facing: 'right' },
    meetingSeat4: { id: 'meetingSeat4', label: 'Meeting Seat 4', x: 91, y: 54, capacity: 1, type: 'meeting', facing: 'left' },
    meetingSeat5: { id: 'meetingSeat5', label: 'Meeting Seat 5', x: 84, y: 60, capacity: 1, type: 'meeting', facing: 'right' },
    meetingSeat6: { id: 'meetingSeat6', label: 'Meeting Seat 6', x: 91, y: 61, capacity: 1, type: 'meeting', facing: 'left' },
    meetingSeat7: { id: 'meetingSeat7', label: 'Meeting Seat 7', x: 87, y: 39, capacity: 1, type: 'meeting', facing: 'right' },
    meetingSeat8: { id: 'meetingSeat8', label: 'Meeting Seat 8', x: 88, y: 64, capacity: 1, type: 'meeting', facing: 'left' },

    loungeEntry: { id: 'loungeEntry', label: 'Lounge Entry', x: 76, y: 79, capacity: 3, type: 'waypoint' },
    sofa1: { id: 'sofa1', label: 'Lounge Sofa', x: 86, y: 77, capacity: 1, type: 'rest', facing: 'right' },
    sofa2: { id: 'sofa2', label: 'Lounge Sofa', x: 91, y: 78, capacity: 1, type: 'rest', facing: 'left' },
    beanBag1: { id: 'beanBag1', label: 'Bean Bag', x: 84, y: 88, capacity: 1, type: 'rest', facing: 'right' },
    daybed1: { id: 'daybed1', label: 'Daybed', x: 91, y: 89, capacity: 1, type: 'rest', facing: 'left' },
};

export const deskAssignments = {
    trent: { homeDeskId: 'deskTrent', deskSeat: officeStations.deskTrent, role: 'trent' },
    'jauki-social': { homeDeskId: 'deskSocial', deskSeat: officeStations.deskSocial, role: 'social' },
    'jauki-threads': { homeDeskId: 'deskThreads', deskSeat: officeStations.deskThreads, role: 'threads' },
    'jauki-article': { homeDeskId: 'deskArticle', deskSeat: officeStations.deskArticle, role: 'article' },
    'jauki-planner': { homeDeskId: 'deskPlanner', deskSeat: officeStations.deskPlanner, role: 'planner' },
    'data-analyst': { homeDeskId: 'deskData', deskSeat: officeStations.deskData, role: 'data' },
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
    deskTrent: 'openNorth', deskSocial: 'openNorth', deskThreads: 'openNorth',
    deskArticle: 'openSouth', deskPlanner: 'openSouth', deskData: 'openSouth', deskFinance: 'openSouth',
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
