import React, { useEffect, useMemo, useRef } from 'react';
import { useFrame } from '@react-three/fiber';
import * as THREE from 'three';
import { hashId } from './layout';

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
function drawScreen(canvas, profile, worker, selected, tick, seed) {
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
    if (kind === 'server') {
        for (let c=0;c<3;c++) for(let r=0;r<5;r++) { rounded(ctx, 17+c*119, 70+r*18, 106, 13, 3, '#123655'); ctx.fillStyle = (r+c+seed)%3===0 ? line : '#36678a'; ctx.fillRect(24+c*119, 75+r*18, 5, 4); ctx.fillRect(37+c*119, 75+r*18, 43+(r%3)*12, 3); }
    } else if (kind === 'social') {
        for(let i=0;i<3;i++) { rounded(ctx, 18+i*119, 77, 104, 96, 9, '#163456'); rounded(ctx, 26+i*119, 85, 88, 54, 5, i===1?'#795576':'#285b86'); ctx.fillStyle=line; ctx.fillRect(28+i*119, 149, 44, 5); ctx.fillRect(28+i*119, 159, 66, 4); }
    } else if (kind === 'threads' || kind === 'article') {
        rounded(ctx, 17, 73, 350, 110, 6, '#123555');
        for(let r=0;r<6;r++){ctx.fillStyle=r===2?line:'#6284a1';ctx.fillRect(31,84+r*15,kind==='article'?250-(r%3)*35:170+(r%2)*95,4);if(kind==='threads'){ctx.fillStyle='#244f74';ctx.fillRect(31,90+r*15,125+(r%3)*38,3);}}
    } else if(kind==='planner') {
        for(let i=0;i<4;i++){rounded(ctx,17+i*91,75,83,101,5,'#123555');ctx.fillStyle=line;ctx.fillRect(25+i*91,84,39,5);for(let j=0;j<3;j++){rounded(ctx,24+i*91,99+j*23,67,17,3,j===1?'#28547a':'#1c456a');}}
    } else if(kind==='finance') {
        rounded(ctx,17,73,170,107,6,'#123555');rounded(ctx,196,73,171,107,6,'#123555');
        for(let i=0;i<7;i++){ctx.fillStyle=i%2?line:'#426c92';ctx.fillRect(29+i*21,157-(i*13+seed)%66,13,12+(i*13+seed)%66);}
        ctx.strokeStyle=line;ctx.lineWidth=3;ctx.beginPath();for(let i=0;i<8;i++){let x=208+i*21,y=149-(i*9+seed)%65;i?ctx.lineTo(x,y):ctx.moveTo(x,y);}ctx.stroke();
    } else {
        rounded(ctx,17,73,350,107,6,'#123555');ctx.strokeStyle=line;ctx.lineWidth=3;ctx.beginPath();for(let i=0;i<13;i++){let x=30+i*27,y=150-(i*17+seed)%62;i?ctx.lineTo(x,y):ctx.moveTo(x,y);}ctx.stroke();
        for(let i=0;i<5;i++){ctx.fillStyle='#225377';ctx.fillRect(32+i*66,161-(i*11+seed)%35,20,16+(i*11+seed)%35);}
    }
    if (worker?.task) { rounded(ctx, 13, 185, 358, 18, 3, '#0c2948'); ctx.font='10px sans-serif'; ctx.fillStyle='#e6f4ff'; ctx.fillText(worker.task.slice(0, 54), 20, 198); }
    if (selected) { ctx.strokeStyle='#d5efff';ctx.lineWidth=3;ctx.strokeRect(2,2,380,204); }
    const texture = canvas.__officeTexture; if(texture) texture.needsUpdate = true;
}
export default function WorkstationScreen({worker,roomId,stationId,selected=false,reduced=false}) {
    const profile=screenProfile(worker,roomId);
    const canvas=useMemo(()=>{const c=document.createElement('canvas');c.width=384;c.height=208;return c;},[]);
    const texture=useMemo(()=>{const t=new THREE.CanvasTexture(canvas);t.colorSpace=THREE.SRGBColorSpace;t.anisotropy=2;canvas.__officeTexture=t;return t;},[canvas]);
    const elapsed=useRef(0), seed=hashId(stationId)%31;
    useEffect(()=>{drawScreen(canvas,profile,worker,selected,seed,seed);},[canvas,profile[0],profile[1],worker?.id,worker?.status,worker?.task,worker?.stale,worker?.error,selected,seed]);
    useFrame((_,dt)=>{if(reduced||document.hidden||!worker?.working)return;elapsed.current+=dt;if(elapsed.current>1.2){elapsed.current=0;drawScreen(canvas,profile,worker,selected,Math.floor(performance.now()/1200),seed);}});
    useEffect(()=>()=>texture.dispose(),[texture]);
    return <mesh position={[0,1.28,-1.223]}><planeGeometry args={[.94,.5]}/><meshBasicMaterial map={texture} toneMapped={false}/></mesh>;
}
