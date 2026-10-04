import { canonicalRooms, stations, stationById, roomById, obstacles, hashId, allocateStations } from './layout.js';
const distance=(a,b)=>Math.hypot(a[0]-b[0],a[1]-b[1]);
export const BODY_RADIUS=.24;
export const MAX_AMBIENT_WALKERS=2;
export function ambientDestination(id){
  return ['recreation-1','pantry-1','recreation-2','pantry-2'][hashId(id)%4];
}
export function ambientEligible(m,elapsed,reduced=false){
  const w=m.worker;
  return !reduced&&w?.available&&!w.stale&&!w.error&&!['error','warning'].includes(w.status)&&!w.working&&!w.meeting&&!w.resting&&
    m.phase==='seated'&&m.station===m.home&&m.target===m.home&&elapsed>=m.nextRoam;
}
export function makeGraph(){
  const nodes=new Map(),edges=new Map();
  const add=(id,p,kind='room')=>{nodes.set(id,{id,p,kind});edges.set(id,[]);};
  const connect=(a,b,door=null)=>{const cost=distance(nodes.get(a).p,nodes.get(b).p);edges.get(a).push({to:b,cost,door});edges.get(b).push({to:a,cost,door});};
  const rows=[-3.2,5.2,13.4];
  rows.forEach((z,i)=>{add(`west-${i}`,[-19,z],'corridor');add(`east-${i}`,[19,z],'corridor');});
  for(let i=0;i<2;i++){connect(`west-${i}`,`west-${i+1}`);connect(`east-${i}`,`east-${i+1}`);}
  canonicalRooms.forEach(r=>{
    add(`${r.id}-in`,[r.doorX,r.front-.9]);add(`${r.id}-out`,[r.doorX,r.front+.9]);
    add(`${r.id}-hall`,[r.doorX,r.corridor],'corridor');
    connect(`${r.id}-in`,`${r.id}-out`,r.id);connect(`${r.id}-out`,`${r.id}-hall`);
  });
  stations.forEach(s=>{
    add(s.id,s.approach);
    if(s.zoneId){add(`${s.id}-hall`,[s.approach[0],13.4],'corridor');connect(s.id,`${s.id}-hall`);return;}
    const r=roomById[s.roomId];add(`${s.id}-side`,s.side);add(`${s.id}-aisle`,[s.side[0],r.front-.9]);
    connect(s.id,`${s.id}-side`);connect(`${s.id}-side`,`${s.id}-aisle`);connect(`${s.id}-aisle`,`${r.id}-in`);
  });
  for(const z of rows){const row=[...nodes.values()].filter(n=>n.kind==='corridor'&&n.p[1]===z).sort((a,b)=>a.p[0]-b.p[0]);for(let i=1;i<row.length;i++)connect(row[i-1].id,row[i].id);}
  return {nodes,edges};
}
export const graph=makeGraph();
export function route(from,to){
  if(from===to)return [];
  const costs=new Map([[from,0]]),previous=new Map(),open=new Set([from]);
  while(open.size){const id=[...open].sort((a,b)=>costs.get(a)-costs.get(b))[0];open.delete(id);if(id===to)break;
    for(const e of graph.edges.get(id)||[]){const cost=costs.get(id)+e.cost;if(cost<(costs.get(e.to)??Infinity)){costs.set(e.to,cost);previous.set(e.to,{from:id,door:e.door});open.add(e.to);}}}
  if(!previous.has(to))return [];
  const hops=[];let id=to;while(id!==from){const e=previous.get(id);hops.unshift({from:e.from,to:id,door:e.door});id=e.from;}
  const result=[];
  for(const e of hops){const a=graph.nodes.get(e.from),b=graph.nodes.get(e.to);
    if(e.door){const r=roomById[e.door],enter=a.p[1]>b.p[1],side=enter?.65:-.65,sign=enter?1:-1;
      result.push({p:[r.doorX+side,r.front+sign*1.05],anchor:e.from});
      result.push({p:[r.doorX,r.front],door:e.door,anchor:e.from});
      result.push({p:[r.doorX+side,r.front-sign*1.05],door:e.door,release:true,anchor:e.to});
    }else if(a.kind==='corridor'&&b.kind==='corridor'){
      const horizontal=Math.abs(a.p[1]-b.p[1])<.01;
      const sign=horizontal?Math.sign(b.p[0]-a.p[0]):Math.sign(b.p[1]-a.p[1]);
      const off=horizontal?[0,sign*.38]:[-sign*.38,0];
      result.push({p:[a.p[0]+off[0],a.p[1]+off[1]],anchor:e.from});
      result.push({p:[b.p[0]+off[0],b.p[1]+off[1]],anchor:e.to});
    }
    result.push({p:b.p,anchor:e.to});
  }
  return result;
}
export function makeWorld(workers=[]){const world={motions:new Map(),doors:new Map(canonicalRooms.map(r=>[r.id,{open:0,owner:null,queue:[],users:new Set()}])),assignments:new Map(),elapsed:0};syncWorld(world,workers);return world;}
export function syncWorld(world,workers){
  world.assignments=allocateStations(workers,world.assignments);const valid=new Set(workers.map(w=>w.id));
  for(const [id] of world.motions)if(!valid.has(id)){world.motions.delete(id);for(const d of world.doors.values()){d.queue=d.queue.filter(v=>v!==id);d.users.delete(id);if(d.owner===id)d.owner=null;}}
  for(const w of workers){const home=world.assignments.get(w.id);if(!home)continue;
    let m=world.motions.get(w.id);
    if(!m){const s=stationById[home];m={id:w.id,x:s.seat[0],z:s.seat[1],yaw:s.yaw,seated:1,phase:'seated',anchor:home,station:home,target:home,home,route:[],index:0,moving:false,nextRoam:world.elapsed+6+hashId(w.id)%30,returnAt:0,pending:null,blocked:0};world.motions.set(w.id,m);}
    m.worker=w;m.home=home;
  }
  const claimed=new Set([...world.motions.values()].map(m=>m.target));
  for(const m of world.motions.values()){
    const w=m.worker;let desired=m.home;
    const kind=w.meeting?'meeting':w.resting?'rest':null;
    if(kind){desired=stationById[m.target]?.kind===kind?m.target:stations.find(s=>s.kind===kind&&!claimed.has(s.id))?.id;}
    if(w.available&&desired&&(w.working||kind||m.domainSpecial)){
      if(desired!==m.target){claimed.delete(m.target);claimed.add(desired);requestDestination(world,m.id,desired,'domain');}
    }
    m.domainSpecial=!!kind;
  }
}
export function requestDestination(world,id,destination,reason='preview'){
  const m=world.motions.get(id),station=stationById[destination];if(!m||!station||(!m.worker.available&&reason!=='return'))return false;
  if([...world.motions.values()].some(other=>other.id!==id&&other.target===destination))return false;
  if(m.target===destination&&['seated','idle'].includes(m.phase))return true;
  if(['sitting','turning-seat'].includes(m.phase)){m.deferred={destination,reason};return true;}
  m.target=destination;m.reason=reason;m.pending=destination;m.returnAt=0;
  if(m.phase==='seated'){m.phase='standing';m.standFrom=[m.x,m.z];}
  else if(m.phase==='idle')plan(m);
  return true;
}
function plan(m){const destination=m.pending||m.target;m.route=route(m.anchor,destination);m.index=0;m.pending=null;m.phase=m.route.length?'walking':'turning-seat';}
const toward=(a,b,d)=>a+Math.sign(b-a)*Math.min(Math.abs(b-a),d);
export function stepWorld(world,dt,{reduced=false,ambience=true}={}){
  dt=Math.min(.05,Math.max(0,dt));world.elapsed+=dt;
  const motions=[...world.motions.values()].sort((a,b)=>a.id.localeCompare(b.id));
  let roaming=motions.filter(m=>(m.reason==='ambience'||m.reason==='return')&&m.phase!=='seated').length;
  for(const m of motions){m.moving=false;
    if(!m.worker.available){
      if(m.target!==m.home&&(m.reason==='ambience'||m.reason==='return'))requestDestination(world,m.id,m.home,'return');
      if(m.reason!=='return')continue;
    }
    if(m.deferred&&['seated','idle'].includes(m.phase)){const next=m.deferred;m.deferred=null;requestDestination(world,m.id,next.destination,next.reason);}
    if(m.worker.working&&m.target!==m.home)requestDestination(world,m.id,m.home,'domain');
    if(m.returnAt&&world.elapsed>=m.returnAt)requestDestination(world,m.id,m.home,'return');
    if(ambience&&roaming<MAX_AMBIENT_WALKERS&&ambientEligible(m,world.elapsed,reduced)){
      if(requestDestination(world,m.id,ambientDestination(m.id),'ambience')){
        roaming++;
        m.nextRoam=world.elapsed+110+hashId(`${m.id}:cooldown`)%100;
      }
    }
    if(reduced&&m.reason==='ambience'&&m.target!==m.home)requestDestination(world,m.id,m.home,'return');
    if(m.phase==='standing'){
      const source=stationById[m.station];m.seated=toward(m.seated,0,dt*1.5);m.x=source.approach[0]+(source.seat[0]-source.approach[0])*m.seated;m.z=source.approach[1]+(source.seat[1]-source.approach[1])*m.seated;
      if(!m.seated){m.anchor=m.station;plan(m);}continue;
    }
    if(['walking','waiting-door','waiting-worker','turning'].includes(m.phase)){
      const point=m.route[m.index];if(!point){m.phase='turning-seat';continue;}
      const d=point.door?world.doors.get(point.door):null;
      if(d){if(!d.queue.includes(m.id)&&d.owner!==m.id)d.queue.push(m.id);if(!d.owner&&d.queue[0]===m.id){d.owner=m.id;d.users.add(m.id);d.queue.shift();}
        if(d.owner!==m.id||d.open<.98){m.phase='waiting-door';continue;}}
      const dx=point.p[0]-m.x,dz=point.p[1]-m.z,length=Math.hypot(dx,dz);
      if(length<.018){m.x=point.p[0];m.z=point.p[1];m.anchor=point.anchor;m.index++;if(d&&point.release){d.users.delete(m.id);d.owner=null;}
        if(m.pending&&!d)plan(m);continue;}
      const wanted=Math.atan2(dx,dz),angle=Math.atan2(Math.sin(wanted-m.yaw),Math.cos(wanted-m.yaw));m.yaw+=Math.sign(angle)*Math.min(Math.abs(angle),dt*5);
      if(Math.abs(angle)>.22){m.phase='turning';continue;}
      const advance=Math.min(length,(reduced?5:1.3)*dt),nx=m.x+dx/length*advance,nz=m.z+dz/length*advance;
      const occupied=motions.find(other=>other!==m&&Math.hypot(nx-other.x,nz-other.z)<BODY_RADIUS*2+.03);
      if(occupied){m.phase='waiting-worker';m.blocked+=dt;
        // One deterministic worker yields into a free corridor pocket. Never reroute inside a doorway.
        if(m.blocked>(m.id>occupied.id?.6:1.4)&&!d){
          const ux=dx/length,uz=dz/length;
          for(const side of [1,-1]){
            const lateral=[-uz*side*.85,ux*side*.85];
            const detour=[[m.x+lateral[0],m.z+lateral[1]],[occupied.x+ux*.8+lateral[0],occupied.z+uz*.8+lateral[1]]];
            let from=[m.x,m.z];const safe=detour.every(to=>{for(let i=1;i<=12;i++){const x=from[0]+(to[0]-from[0])*i/12,z=from[1]+(to[1]-from[1])*i/12;if(!walkable(x,z)||motions.some(o=>o!==m&&Math.hypot(x-o.x,z-o.z)<BODY_RADIUS*2+.03))return false;}from=to;return true;});
            if(safe){m.route.splice(m.index,0,...detour.map(p=>({p,anchor:m.anchor})));m.blocked=0;break;}
          }
        }
        continue;}
      m.blocked=0;m.x=nx;m.z=nz;m.moving=true;m.phase='walking';
    }
    if(m.phase==='turning-seat'){
      const s=stationById[m.target];const diff=Math.atan2(Math.sin(s.yaw-m.yaw),Math.cos(s.yaw-m.yaw));m.yaw+=Math.sign(diff)*Math.min(Math.abs(diff),dt*5);
      if(Math.abs(diff)<.025){m.yaw=s.yaw;m.phase=s.kind==='standing'?'idle':'sitting';m.station=s.id;m.anchor=s.id;if(s.kind==='standing')m.returnAt=world.elapsed+12;}
    }
    if(m.phase==='sitting'){
      const s=stationById[m.target];m.seated=toward(m.seated,1,dt*1.5);m.x=s.approach[0]+(s.seat[0]-s.approach[0])*m.seated;m.z=s.approach[1]+(s.seat[1]-s.approach[1])*m.seated;
      if(m.seated===1){m.phase='seated';m.station=s.id;m.reason=null;}
    }
  }
  for(const [id,d] of world.doors){const r=roomById[id];const occupied=motions.some(m=>Math.abs(m.x-r.doorX)<1&&Math.abs(m.z-r.front)<.6);
    const target=d.users.size||d.owner||occupied?1:0;d.open=toward(d.open,target,reduced?1:dt*1.8);}
}
export function furnitureCollision(x,z){return obstacles.some(o=>Math.abs(x-o.x)<o.width/2+BODY_RADIUS&&Math.abs(z-o.z)<o.depth/2+BODY_RADIUS);}
export function visualRoom(m){return canonicalRooms.find(r=>m.x>r.x&&m.x<r.x+r.width&&m.z>r.z&&m.z<r.front)?.id||null;}

function walkable(x,z){
 if(x< -19.8||x>19.8||z< -12.7||z>18.8||furnitureCollision(x,z))return false;
 return !canonicalRooms.some(r=>((Math.abs(x-r.x)<.32||Math.abs(x-r.x-r.width)<.32)&&z>r.z-.25&&z<r.front+.25)||(Math.abs(z-r.z)<.32&&x>r.x-.25&&x<r.x+r.width+.25)||(Math.abs(z-r.front)<.32&&x>r.x-.25&&x<r.x+r.width+.25&&Math.abs(x-r.doorX)>.6));
}
