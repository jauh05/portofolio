import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { agentRegistry, systems, tasks } from '../../resources/js/living-office/agentRegistry.js';
import { deskAssignments, meetingSeats, officeEvents, officeStations, restSeats, routeBetween } from '../../resources/js/living-office/officeModel.js';

test('registry exposes seven dynamic workers without fabricated task records', () => {
    assert.equal(agentRegistry.length, 7);
    assert.equal(tasks.length, 0);
    assert.equal(new Set(agentRegistry.map((agent) => agent.id)).size, agentRegistry.length);
    assert.ok(agentRegistry.every((agent) => systems.some((system) => system.id === agent.parentSystem)));
});

test('future agents are represented honestly', () => {
    assert.equal(agentRegistry.find((agent) => agent.id === 'data-analyst').status, 'not_connected');
    assert.equal(agentRegistry.find((agent) => agent.id === 'finance-analyst').status, 'not_connected');
});

test('browser source never contains the office bridge secret', () => {
    const sourceFiles = ['main.jsx', 'useOfficeData.js', 'useLivingOffice.js', 'services.js'];
    for (const file of sourceFiles) {
        const source = readFileSync(new URL(`../../resources/js/living-office/${file}`, import.meta.url), 'utf8');
        assert.doesNotMatch(source, /OFFICE_BRIDGE_TOKEN|secret_token_here/);
    }
});

test('every worker has one unique home desk and fixed seat anchor', () => {
    assert.equal(Object.keys(deskAssignments).length, agentRegistry.length);
    assert.equal(new Set(Object.values(deskAssignments).map((assignment) => assignment.homeDeskId)).size, agentRegistry.length);
    for (const agent of agentRegistry) {
        assert.equal(agent.homeDeskId, deskAssignments[agent.id].homeDeskId);
        assert.equal(officeStations[agent.homeDeskId].type, 'desk');
    }
});

test('meeting and lounge expose only fixed seats', () => {
    assert.equal(meetingSeats.length, 8);
    assert.equal(restSeats.length, 4);
    assert.ok(meetingSeats.every((seat) => officeStations[seat].type === 'meeting'));
    assert.ok(restSeats.every((seat) => officeStations[seat].type === 'rest'));
    assert.ok(officeEvents.every((event) => event.participants.length <= meetingSeats.length));
});

test('paths are deterministic and pass through safe waypoints', () => {
    const meetingRoute = routeBetween('deskArticle', 'meetingSeat1');
    assert.deepEqual(meetingRoute, routeBetween('deskArticle', 'meetingSeat1'));
    assert.ok(meetingRoute.includes('hallwayCenter'));
    assert.ok(meetingRoute.includes('meetingEntry'));
    assert.equal(meetingRoute.at(-1), 'meetingSeat1');

    const serverRoute = routeBetween('deskTrent', 'serverMonitor');
    assert.ok(serverRoute.includes('serverEntry'));
    assert.equal(serverRoute.at(-1), 'serverMonitor');

    const restRoute = routeBetween('deskSocial', 'daybed1');
    assert.ok(restRoute.includes('loungeEntry'));
    assert.equal(restRoute.at(-1), 'daybed1');
});

test('movement implementation guarantees translation-only sprites and no free walking', () => {
    const hook = readFileSync(new URL('../../resources/js/living-office/useLivingOffice.js', import.meta.url), 'utf8');
    const main = readFileSync(new URL('../../resources/js/living-office/main.jsx', import.meta.url), 'utf8');
    const css = readFileSync(new URL('../../resources/js/living-office/living-office.css', import.meta.url), 'utf8');
    const workerCss = css.split('\n').filter((line) => line.includes('.worker') || line.includes('@keyframes walk')).join('\n');

    assert.doesNotMatch(hook, /Math\.random|Math\.atan2|\bangle\b|\brotation\b|rotate\(/i);
    assert.match(main, /translate3d\(/);
    assert.doesNotMatch(workerCss, /rotate\(/i);
    assert.doesNotMatch(hook, /wander|random/i);
    for (const command of ['sendToMeeting', 'sendToRest', 'sendToServer', 'returnToDesk', 'startMeeting', 'endMeeting']) {
        assert.match(hook, new RegExp(`const ${command}`));
    }
});
