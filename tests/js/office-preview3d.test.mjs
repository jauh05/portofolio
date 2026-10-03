import test from 'node:test';
import assert from 'node:assert/strict';
import {createMotion,requestMove,stepMotion,hitsDesk,DOOR} from '../../resources/js/living-office/preview3d/navigation.js';
import {adaptWorker,fixtureWorker} from '../../resources/js/living-office/preview3d/stateAdapter.js';
function travel(m, direction, reduced=false) {
    assert.equal(requestMove(m,direction),true);const phases=new Set();let crossed=false;
    for(let i=0;i<5000;i++){
        const previousZ=m.z;stepMotion(m,1/60,reduced);phases.add(m.phase);
        assert.equal(hitsDesk(m.x,m.z),false,`desk collision ${m.x},${m.z}`);
        const ax=2.55,az=2,bx=ax+1.58*Math.cos(m.door*Math.PI/2),bz=az+1.58*Math.sin(m.door*Math.PI/2);
        const u=Math.max(0,Math.min(1,((m.x-ax)*(bx-ax)+(m.z-az)*(bz-az))/1.58**2));
        assert.ok(Math.hypot(m.x-ax-u*(bx-ax),m.z-az-u*(bz-az))>=DOOR.radius+.02,'door leaf must not hit body');
        if((previousZ-DOOR.z)*(m.z-DOOR.z)<0){crossed=true;assert.ok(m.door>=.96,'door must be open at crossing');}
        if(Math.abs(m.z-DOOR.z)<.5&&Math.abs(m.x-DOOR.x)<.5)assert.ok(m.door>=.96,'cannot close on body');
        if(m.phase===(direction==='enter'?'seated':'idle'))break;
    }
    assert.ok(crossed);return phases;
}
test('exit and enter use doorway and avoid desks, returning to seated without a loop',()=>{const m=createMotion();travel(m,'exit');const phases=travel(m,'enter');assert.ok(phases.has('waiting'));assert.ok(phases.has('sitting'));assert.equal(m.phase,'seated');for(let i=0;i<500;i++)stepMotion(m,1/60);assert.equal(m.door,0);assert.equal(m.phase,'seated');assert.equal(m.x,-2.25);});
test('reduced motion retains door ordering and reaches the chair',()=>{const m=createMotion();travel(m,'exit',true);travel(m,'enter',true);assert.equal(m.seated,1);});
test('occupied reservation from another worker holds the door open',()=>{const m=createMotion();m.door=1;m.reservations.add('another-worker');for(let i=0;i<100;i++)stepMotion(m,1/60);assert.equal(m.door,1);});
test('refresh of task content cannot reset presentation position',()=>{const m=createMotion();requestMove(m,'exit');stepMotion(m,.05);const before={...m};const changed=adaptWorker(fixtureWorker('Updated task'));assert.equal(changed.task,'Updated task');assert.equal(m.seated,before.seated);assert.equal(m.phase,before.phase);});
test('empty, disconnected, stale and failed domain data stay distinct',()=>{assert.equal(adaptWorker([]).available,false);assert.equal(adaptWorker([{id:'trent',status:'not_connected'}]).available,false);assert.equal(adaptWorker(fixtureWorker(),{stale:true}).stale,true);assert.equal(adaptWorker([{id:'trent',status:'error',currentTask:'Failure'}]).working,false);assert.equal(adaptWorker([], {error:true}).error,true);});
test('task strings are retained as text and progress is not fabricated',()=>{const w=adaptWorker([{id:'trent',status:'idle',currentTask:'<script>unsafe</script>'}]);assert.equal(w.task,'<script>unsafe</script>');assert.equal(w.progress,null);assert.equal(w.working,false);});
