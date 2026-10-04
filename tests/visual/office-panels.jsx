import React,{useState} from 'react';
import{createRoot}from'react-dom/client';
import {OperationsView} from '../../resources/js/living-office/OperationsViews';
import {ApiStatus} from '../../resources/js/living-office/ApiStatus';
import {AnalystReports} from '../../resources/js/living-office/AnalystReports';
import {ContentPlanner} from '../../resources/js/living-office/ContentPlanner';
import '../../resources/js/living-office/living-office.css';
import '../../resources/js/living-office/office-redesign.css';
import '../../resources/js/living-office/office-pages.css';
import '../../resources/js/living-office/office-refinement.css';
import '../../resources/js/living-office/planner-generation.css';

// Development fixture only. Every record below is synthetic and never reaches Office APIs.
const agents=[
 {id:'jauki-social',name:'Social Content Worker',role:'Social Content',status:'idle',availableActions:['generate_feed','generate_story'],shortName:'SC',color:'#5678c9'},
 {id:'jauki-threads',name:'Threads Worker',role:'Community',status:'working',availableActions:['generate_threads'],shortName:'TH',color:'#7968b8'},
 {id:'jauki-article',name:'Web Article Worker',role:'Editorial',status:'error',availableActions:['generate_article'],shortName:'WA',color:'#458a98'},
];
const activity=Array.from({length:9},(_,i)=>({id:i+1,agentId:agents[i%3].id,type:['task.started','content.preview_ready','task.completed','task.failed'][i%4],activity:['Generasi konten dimulai','Draft siap ditinjau','Worker menyelesaikan tugas','Tugas gagal diproses'][i%4],status:['running','ready_for_review','completed','failed'][i%4],createdAt:new Date(Date.now()-i*120000).toISOString()}));
const tasks=['queued','claimed','running','completed','failed'].map((status,i)=>({id:i,agentId:agents[i%3].id,status,title:`Contoh tugas ${i+1}`,type:'content',progress:i*20,startedAt:new Date().toISOString()}));
const schedule={id:1,name:'Contoh jadwal konten',scheduleType:'one_time',scheduledAt:new Date().toISOString(),brand:{id:1,name:'Demo workspace'},platform:'instagram',contentType:'feed',generationTiming:'manual',publishingMode:'review',isActive:true,metadata:{source:'manual'}};
const noop=async()=>({});
function Fixture(){const[view,setView]=useState('reports'),[dark,setDark]=useState(false);return <div className="app-shell office-redesign" data-theme={dark?'dark':'light'} style={{display:'block',minHeight:'100dvh'}}><nav style={{display:'flex',flexWrap:'wrap',gap:8,padding:10,background:'var(--surface)',color:'var(--text)'}}><b style={{marginRight:12}}>DEV FIXTURE · synthetic records · no API</b>{['reports','activity','api','planner','kanban'].map(id=><button type="button" key={id} onClick={()=>setView(id)} aria-pressed={view===id}>{id}</button>)}<button type="button" onClick={()=>setDark(v=>!v)}>Theme</button></nav><main className="main-stage view-mode" style={{position:'relative',left:0,top:'auto',width:'100%',height:'calc(100dvh - 42px)',overflow:'auto'}}>{view==='reports'?<AnalystReports reports={[]} brands={[]} loading={false} generateReport={noop} approveReport={noop} dismissReport={noop}/>:view==='api'?<ApiStatus agents={agents} onSelectWorker={()=>{}}/>:view==='planner'?<ContentPlanner planner={{schedules:[schedule],occurrences:[],upcoming:[]}} brands={[schedule.brand]} loading={false} loadPlanner={noop} loadPlannerGeneration={noop} createSchedule={noop} analyzePlan={noop} contentItems={[]} commands={[]} agents={agents} generateContent={noop} reviseContent={noop} updateContent={noop} approveContent={noop}/>:<OperationsView view={view} agents={agents} tasks={tasks} activity={activity} error={null} loading={false} retry={noop} onSelectWorker={()=>{}}/>}</main></div>}
if(import.meta.env.DEV){
 const root=import.meta.hot?.data.root||createRoot(document.getElementById('root'));
 if(import.meta.hot)import.meta.hot.data.root=root;
 root.render(<Fixture/>);
}
