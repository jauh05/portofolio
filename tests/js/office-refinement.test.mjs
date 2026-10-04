import test from 'node:test';
import assert from 'node:assert/strict';
import { workerVisualVariant, monitorLayoutVariant } from '../../resources/js/living-office/preview3d/full/visualProfiles.js';
import { normalizeRoster } from '../../resources/js/living-office/preview3d/full/roster.js';
import { makeWorld, syncWorld, stepWorld, ambientDestination, ambientEligible, MAX_AMBIENT_WALKERS } from '../../resources/js/living-office/preview3d/full/navigation.js';
import { channelReadiness } from '../../resources/js/living-office/connectionReadiness.js';
import { readPanelMode, writePanelMode } from '../../resources/js/living-office/panelPreferences.js';

const ids=['jauki-social','jauki-threads','jauki-article','jauki-planner','jauki-analyst','trent'];
test('worker appearance is stable by ID and has visible palette and accessory variation',()=>{
    assert.deepEqual(workerVisualVariant(ids[0]),workerVisualVariant(ids[0]));
    assert.ok(new Set(ids.map(id=>JSON.stringify(workerVisualVariant(id)))).size>=5);
    assert.ok(new Set(ids.map(id=>workerVisualVariant(id).hair)).size>=2);
    assert.ok(new Set(ids.map(id=>workerVisualVariant(id).skin)).size>=2);
});
test('monitor profile layout is stable and spans four compositions',()=>{
    assert.equal(monitorLayoutVariant(ids[0],'deskSocial'),monitorLayoutVariant(ids[0],'deskSocial'));
    assert.equal(new Set(Array.from({length:100},(_,i)=>monitorLayoutVariant(`same-role-${i}`,'desk'))).size,4);
});
test('active worker stays at desk; ambient scheduler limits simultaneous walks and returns home',()=>{
    const rows=ids.map((id,i)=>({id,status:i===0?'working':'idle',currentTask:i===0?'Actual task':'',progress:0}));
    const world=makeWorld(normalizeRoster(rows).workers);
    for(const m of world.motions.values())m.nextRoam=0;
    for(let i=0;i<800;i++){
        stepWorld(world,.05);
        const ambient=[...world.motions.values()].filter(m=>['ambience','return'].includes(m.reason)&&m.phase!=='seated');
        assert.ok(ambient.length<=MAX_AMBIENT_WALKERS);
    }
    const active=world.motions.get(ids[0]);
    assert.equal(active.target,active.home);
    assert.equal(active.phase,'seated');
    assert.match(ambientDestination(ids[1]),/^(recreation|pantry)-[12]$/);
    assert.equal(ambientEligible(active,world.elapsed),false);
});
test('worker becoming unavailable during an ambient trip walks home without teleporting',()=>{
    const world=makeWorld(normalizeRoster([{id:ids[1],status:'idle'}]).workers);
    const motion=world.motions.get(ids[1]);motion.nextRoam=0;
    for(let i=0;i<120;i++)stepWorld(world,.05);
    assert.notEqual(motion.phase,'seated');
    const position=[motion.x,motion.z];
    syncWorld(world,normalizeRoster([{id:ids[1],status:'not_connected'}]).workers);
    stepWorld(world,.05);
    assert.equal(motion.target,motion.home);
    assert.ok(Math.hypot(motion.x-position[0],motion.z-position[1])<.2);
    for(let i=0;i<5000&&motion.phase!=='seated';i++)stepWorld(world,.05);
    assert.equal(motion.phase,'seated');
    assert.equal(motion.station,motion.home);
});
test('panel mode persists with safe fallback',()=>{
    const values=new Map(),storage={getItem:key=>values.get(key),setItem:(key,value)=>values.set(key,value)};
    assert.equal(readPanelMode('plannerPanelMode',storage),'normal');
    assert.equal(writePanelMode('plannerPanelMode','compact',storage),'compact');
    assert.equal(readPanelMode('plannerPanelMode',storage),'compact');
    assert.equal(writePanelMode('kanbanPanelMode','expanded',storage),'expanded');
    assert.equal(readPanelMode('kanbanPanelMode',storage),'expanded');
    assert.equal(readPanelMode('x',{getItem(){throw Error('disabled');}}),'normal');
});
test('connector status remains unverified without backend health evidence',()=>{
    const ready=channelReadiness({status:'idle',availableActions:['generate_feed']},['generate_feed']);
    assert.equal(ready.worker,'READY');
    assert.equal(ready.generator,'NOT VERIFIED');
    assert.equal(ready.account,'NOT VERIFIED');
    assert.equal(ready.publishing,'NOT VERIFIED');
    assert.deepEqual(ready.offered,['generate_feed']);
    assert.equal(channelReadiness({status:'error'}).worker,'ERROR');
    assert.equal(channelReadiness(null).worker,'NOT CONFIGURED');
    assert.equal(channelReadiness({status:'unknown'}).worker,'NOT VERIFIED');
});
