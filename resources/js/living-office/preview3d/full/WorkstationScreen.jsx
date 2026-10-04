import React, { useEffect, useMemo, useRef } from 'react';
import { useFrame } from '@react-three/fiber';
import * as THREE from 'three';
import { hashId } from './layout';
import { monitorLayoutVariant } from './visualProfiles';

const profiles = {
    trent: ['INFRASTRUCTURE', 'server'],
    'jauki-social': ['SOCIAL STUDIO', 'social'],
    'jauki-threads': ['THREADS', 'threads'],
    'jauki-article': ['ARTICLE EDITOR', 'article'],
    'jauki-analyst': ['ANALYST', 'analytics'],
    'jauki-planner': ['CONTENT PLAN', 'planner'],
    'finance-analyst': ['FINANCE', 'finance'],
    'data-analyst': ['DATA ANALYSIS', 'data'],
};
const roomProfiles = { open: ['OFFICE', 'server'], content: ['CONTENT', 'planner'], social: ['SOCIAL', 'social'], analyst: ['ANALYSIS', 'analytics'], threads: ['THREADS', 'threads'], article: ['EDITOR', 'article'], control: ['CONTROL', 'data'], server: ['SERVER', 'server'] };
const colors = { server: '#31d1ed', social: '#ff83ad', threads: '#b29cff', article: '#ffc27e', analytics: '#52ddbb', planner: '#91b8ff', finance: '#e9c67b', data: '#76e1e8' };
export function screenProfile(worker, roomId) { return (worker && profiles[worker.id]) || roomProfiles[roomId] || ['WORKSPACE', 'data']; }

function rounded(ctx, x, y, w, h, radius, fill) {
    ctx.fillStyle = fill; ctx.beginPath(); ctx.roundRect(x, y, w, h, radius); ctx.fill();
}
function drawModule(ctx,kind,module,x,y,w,h,line,seed,tick) {
    rounded(ctx,x,y,w,h,5,'#123555');
    ctx.save();ctx.beginPath();ctx.rect(x+3,y+3,w-6,h-6);ctx.clip();
    ctx.fillStyle=line;ctx.fillRect(x+9,y+9,Math.min(w-18,24+module*11),3);
    if(kind==='server'||(kind==='data'&&module===2)){
        for(let row=0;row<5;row++){ctx.fillStyle=row===(tick+seed)%5?line:'#39789b';ctx.fillRect(x+10,y+22+row*12,5,4);ctx.fillStyle='#396280';ctx.fillRect(x+21,y+22+row*12,Math.max(9,w-39-row*7),3);}
    }else if(kind==='social'||kind==='threads'){
        if(module%2===0){rounded(ctx,x+10,y+23,w-20,Math.max(22,h*.45),5,module===0?'#285b86':'#674f83');for(let i=0;i<3;i++){ctx.fillStyle=i===(tick+seed)%3?line:'#53728e';ctx.fillRect(x+12+i*13,y+h-17,8,3);}}
        else for(let i=0;i<5;i++){ctx.fillStyle=i===(tick+seed)%5?line:'#477895';ctx.fillRect(x+11,y+27+i*12,Math.max(12,w-32-i*9),4);}
    }else if(kind==='article'||kind==='planner'){
        for(let i=0;i<6;i++){ctx.fillStyle=i===(tick+seed)%6?line:'#577c9b';rounded(ctx,x+10,y+23+i*12,Math.max(14,w-25-(i%3)*19),4,2,ctx.fillStyle);}
        if(kind==='planner')for(let i=0;i<3;i++){ctx.strokeStyle='#558ab0';ctx.strokeRect(x+12+i*22,y+h-22,17,13);}
    }else{
        ctx.strokeStyle=line;ctx.lineWidth=2;ctx.beginPath();for(let i=0;i<8;i++){const px=x+11+i*(w-22)/7,py=y+h-14-((i*11+seed+module*7)%Math.max(20,h-38));i?ctx.lineTo(px,py):ctx.moveTo(px,py);}ctx.stroke();
        for(let i=0;i<4;i++){ctx.fillStyle='#427392';ctx.fillRect(x+14+i*17,y+h-16-(i*8+seed)%21,10,10+(i*8+seed)%21);}
    }
    ctx.restore();
}
function drawScreen(canvas, profile, worker, selected, tick, seed, layout) {
    const ctx = canvas.getContext('2d'); if (!ctx) return;
    const [title, kind] = profile, accent = colors[kind];
    ctx.fillStyle = '#071a38'; ctx.fillRect(0, 0, 384, 208);
    ctx.fillStyle = '#102c50'; ctx.fillRect(0, 0, 384, 30);
    ctx.fillStyle = accent; ctx.fillRect(13, 10, 7, 7);
    ctx.font = 'bold 12px sans-serif'; ctx.fillStyle = '#f4fbff'; ctx.fillText(title, 29, 18);
    ctx.fillStyle = '#1e4568'; ctx.fillRect(13, 42, 358, 1);
    const active = worker?.working && !worker.stale && !worker.error;
    const line = active ? accent : '#6586a3';
    ctx.fillStyle = line; ctx.font = '10px sans-serif';
    ctx.fillText(worker ? (worker.stale || worker.error ? 'DATA BELUM DIPERBARUI' : String(worker.status || 'idle').toUpperCase()) : 'VISUAL KANTOR', 15, 59);
    // Every mark below is decorative; no fabricated metric or progress value is shown.
    const panes=[
        [[17,72,220,108],[244,72,123,52],[244,130,123,50]],
        [[17,72,123,52],[17,130,123,50],[147,72,220,108]],
        [[17,72,350,47],[17,125,169,55],[193,125,174,55]],
        [[17,72,83,108],[107,72,126,108],[240,72,127,108]],
    ][layout];
    panes.forEach(([x,y,w,h],index)=>drawModule(ctx,kind,(index+layout)%3,x,y,w,h,line,seed,tick));
    ctx.fillStyle=tick%2===0?line:'#325474';ctx.fillRect(355,11,2,11);
    if (worker?.task) { rounded(ctx, 13, 185, 358, 18, 3, '#0c2948'); ctx.font='10px sans-serif'; ctx.fillStyle='#e6f4ff'; ctx.fillText(worker.task.slice(0, 54), 20, 198); }
    if (selected) { ctx.strokeStyle='#d5efff';ctx.lineWidth=3;ctx.strokeRect(2,2,380,204); }
    const texture = canvas.__officeTexture; if(texture) texture.needsUpdate = true;
}
export default function WorkstationScreen({worker,roomId,stationId,selected=false,reduced=false}) {
    const profile=screenProfile(worker,roomId);
    const canvas=useMemo(()=>{const c=document.createElement('canvas');c.width=384;c.height=208;return c;},[]);
    const texture=useMemo(()=>{const t=new THREE.CanvasTexture(canvas);t.colorSpace=THREE.SRGBColorSpace;t.anisotropy=2;canvas.__officeTexture=t;return t;},[canvas]);
    const elapsed=useRef(0), seed=hashId(stationId)%31, layout=monitorLayoutVariant(worker?.id||roomId,stationId);
    useEffect(()=>{drawScreen(canvas,profile,worker,selected,seed,seed,layout);},[canvas,profile[0],profile[1],worker?.id,worker?.status,worker?.task,worker?.stale,worker?.error,selected,seed,layout]);
    useFrame((_,dt)=>{if(reduced||document.hidden||!worker?.working)return;elapsed.current+=dt;if(elapsed.current>1.2){elapsed.current=0;drawScreen(canvas,profile,worker,selected,Math.floor(performance.now()/1200),seed,layout);}});
    useEffect(()=>()=>texture.dispose(),[texture]);
    return <mesh position={[0,1.28,-1.223]}><planeGeometry args={[.94,.5]}/><meshBasicMaterial map={texture} toneMapped={false}/></mesh>;
}
