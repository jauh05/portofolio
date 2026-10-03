import { officeRooms, deskAssignments } from '../../officeModel.js';
// World coordinates are metres. Canonical IDs come from officeModel, never from artwork.
const bounds = {
  open: [-17,-12,11.2,7.2], content: [-4.8,-12,7.3,7.2], social: [3.5,-12,6,7.2], server: [10.5,-12,7,7.2],
  analyst: [-17,-1.6,10,5.2], threads: [-5.5,-1.6,8.5,5.2], meeting: [4.5,-1.6,13,5.2],
  article: [-17,7,9,5.5], lounge: [-6.5,7,10,5.5], control: [5,7,12.5,5.5],
};
export const canonicalRooms = officeRooms.map(r => {
  const [x,z,width,depth]=bounds[r.id]; const front=z+depth;
  return {...r,x,z,width,depth,front,doorX:x+width-1.4,corridor:front<0?-3.2:front<6?5.2:13.4};
});
export const ambientZones=[
  {id:'recreation',label:'Recreation',x:-17,z:14.5,width:10,depth:3.8},
  {id:'pantry',label:'Pantry & Coffee',x:-5.5,z:14.5,width:9,depth:3.8},
  {id:'reception',label:'Reception',x:5,z:14.5,width:12.5,depth:3.8},
];
export const roomById=Object.fromEntries(canonicalRooms.map(r=>[r.id,r]));
export const stations=[];
function desk(id,roomId,x,z){
  stations.push({id,roomId,kind:'desk',seat:[x,z],approach:[x,z-.15],yaw:Math.PI,side:[x-1.6,z-.15]});
}
// Existing seven home IDs are retained. Extra physical seats are presentation slots.
desk('deskTrent','open',-14.4,-9.9);desk('open-2','open',-10.9,-9.9);desk('open-3','open',-14.4,-7.1);desk('open-4','open',-10.9,-7.1);
desk('deskPlanner','content',-2.5,-9.9);desk('content-2','content',.85,-9.9);
desk('deskSocial','social',6,-9.9);desk('social-2','social',6,-7.1);
desk('deskData','analyst',-14.4,.5);desk('analyst-2','analyst',-10.9,.5);
desk('deskThreads','threads',-3,.5);desk('threads-2','threads',.4,.5);
desk('deskArticle','article',-14.4,9.2);desk('article-2','article',-11,9.2);
desk('deskFinance','control',7.6,9.2);desk('control-2','control',11.2,9.2);
desk('server-monitor','server',13,-9.9);
for(let i=0;i<8;i++){
  const top=i<4,x=8+i%4*2,z=top?-.4:1.8;
  const approach=[x,z+(top?.15:-.15)];
  stations.push({id:`meeting-${i+1}`,roomId:'meeting',kind:'meeting',seat:[x,z],approach,yaw:top?0:Math.PI,side:[5.15,approach[1]]});
}
for(let i=0;i<4;i++)stations.push({id:`lounge-${i+1}`,roomId:'lounge',kind:'rest',seat:[-4.5+i*1.8,9.4],approach:[-4.5+i*1.8,10],yaw:0,side:[-5.7,10]});
for(const zone of ambientZones)for(let i=0;i<2;i++)stations.push({id:`${zone.id}-${i+1}`,roomId:null,zoneId:zone.id,kind:'standing',seat:[zone.x+2+i*2.2,15.25],approach:[zone.x+2+i*2.2,15.25],yaw:Math.PI});
export const stationById=Object.fromEntries(stations.map(s=>[s.id,s]));
export const homeHints=Object.fromEntries(Object.entries(deskAssignments).map(([id,a])=>[id,a.homeDeskId]));
// Backend OfficeRegistry contains this additional worker, absent from the legacy UI list.
homeHints['data-analyst']='analyst-2';
export const obstacles=stations.filter(s=>s.kind==='desk').map(s=>({id:s.id,x:s.seat[0],z:s.seat[1]-1.1,width:2.45,depth:1.05}));
obstacles.push({id:'conference',x:11,z:.7,width:8,depth:1});
export const WORLD_BOUNDS={min:[-20,-.4,-13],max:[20,3,19]};
export function hashId(id){let hash=2166136261;for(const c of id)hash=Math.imul(hash^c.charCodeAt(0),16777619);return hash>>>0;}
export function allocateStations(workers,previous=new Map()){
  const output=new Map(),used=new Set();
  const ordered=[...workers].sort((a,b)=>a.id.localeCompare(b.id));
  for(const w of ordered){const old=previous.get(w.id);if(old&&stationById[old]&&stationById[old].kind==='desk'&&!used.has(old)){output.set(w.id,old);used.add(old);}}
  for(const w of ordered){if(output.has(w.id))continue;const hinted=homeHints[w.id];if(hinted&&!used.has(hinted)){output.set(w.id,hinted);used.add(hinted);}}
  for(const w of ordered){if(output.has(w.id))continue;const free=stations.find(s=>s.kind==='desk'&&!used.has(s.id));if(free){output.set(w.id,free.id);used.add(free.id);}}
  return output;
}
