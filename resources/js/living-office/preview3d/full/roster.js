import { agentRegistry } from '../../agentRegistry.js';
import { hashId } from './layout.js';
const ACTIVE=new Set(['working','generating','planning','analyzing','monitoring','reporting']);
const INACTIVE=new Set(['offline','not_connected','not_installed']);
const swatches=['#2e90fa','#1570ef','#42526b','#477c8e','#7074a3','#358b82','#c29b61','#5269a4'];
const additional={ 'data-analyst':{name:'Data Analyst',role:'Data Analyst',fullRole:'Data Analyst',parentSystem:'future-data-agent'} };
export function workerMetadata(id){
  const known=agentRegistry.find(a=>a.id===id)||additional[id]||{name:id,role:'Worker',fullRole:'Worker'};
  return {id,shortName:known.name.split(/\s+/).map(s=>s[0]).join('').slice(0,2).toUpperCase(),color:swatches[hashId(id)%swatches.length],capabilities:[],availableActions:[],recentResults:[],zoneLabel:'Office 3D',...known};
}
export function normalizeRoster(rows,{stale=false,error=false}={}){
  const seen=new Set(),duplicates=[];const workers=[];
  for(const raw of Array.isArray(rows)?rows:[]){
    if(!raw||typeof raw.id!=='string'||!raw.id)continue;
    if(seen.has(raw.id)){duplicates.push(raw.id);continue;}seen.add(raw.id);
    const meta=workerMetadata(raw.id);
    const task=typeof raw.currentTask==='string'?raw.currentTask:raw.currentTask?.title||raw.currentTask?.summary||'';
    workers.push({...meta,...raw,name:meta.name,role:meta.role,task:typeof task==='string'?task:'',currentTask:typeof task==='string'?task:'',progress:Number.isFinite(raw.progress)?Math.max(0,Math.min(100,raw.progress)):null,available:!INACTIVE.has(raw.status),working:ACTIVE.has(raw.status),meeting:raw.status==='meeting',resting:raw.status==='break',stale,error,variant:swatches[hashId(raw.id)%swatches.length]});
  }
  return {workers,duplicates,authoritativeCount:seen.size};
}
export function auditRoster(workers,motions,assignments){
  const ids=workers.map(w=>w.id),rendered=[...motions.keys()];const stationIds=[...assignments.values()];
  return {authoritative:ids.length,rendered:rendered.length,missing:ids.filter(id=>!motions.has(id)),extra:rendered.filter(id=>!ids.includes(id)),duplicateStations:stationIds.filter((id,i)=>stationIds.indexOf(id)!==i),unassigned:ids.filter(id=>!assignments.has(id))};
}
