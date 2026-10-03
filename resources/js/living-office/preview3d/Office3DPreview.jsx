import React, { Component, Suspense, useMemo, useRef, useState, useEffect } from 'react';
import { Canvas } from '@react-three/fiber';
import { PCFShadowMap } from 'three';
import { Move, RotateCcw, Focus, Camera } from 'lucide-react';
import FullScene from './full/FullScene';
import { makeWorld } from './full/navigation';
import { normalizeRoster, auditRoster } from './full/roster';
import { canonicalRooms, ambientZones } from './full/layout';
import './preview3d.css';
import './full/full.css';
const CAMERA={position:[10,35,45],zoom:15,near:.1,far:150},SHADOWS={type:PCFShadowMap},GL={antialias:true,alpha:false},DPR=[1,1.5];
class Boundary extends Component{state={failed:false};static getDerivedStateFromError(){return{failed:true};}render(){return this.state.failed?<div className="office3d-fallback">Office 3D belum dapat dimuat. <button onClick={this.props.onFallback}>Kembali ke Office</button></div>:this.props.children;}}
function useReduced(){const [v,set]=useState(()=>matchMedia('(prefers-reduced-motion: reduce)').matches);useEffect(()=>{const m=matchMedia('(prefers-reduced-motion: reduce)'),change=()=>set(m.matches);m.addEventListener('change',change);return()=>m.removeEventListener('change',change);},[]);return v;}
export default function Office3DPreview({domainData={rows:[],loading:true},selectedId,onSelect,onFallback,devControls,devReducedMotion=false}){
 const {workers,duplicates}=useMemo(()=>normalizeRoster(domainData.rows,domainData),[domainData]);const world=useRef(null);if(!world.current)world.current=makeWorld(workers);
 const reduced=useReduced()||(import.meta.env.DEV&&devReducedMotion),[camera,setCamera]=useState({type:'fit',revision:0}),[inspection,setInspection]=useState({motions:[],doors:[]});
 const view=(type,room)=>setCamera(c=>({type,room,id:selectedId,revision:c.revision+1}));const selected=workers.find(w=>w.id===selectedId),audit=auditRoster(workers,world.current.motions,world.current.assignments);
 return <section className="office3d office3d-full" aria-label="Living AI Office 3D" data-reduced-motion={reduced} data-scene-ids={inspection.sceneWorkers?.map(w=>w.id).join(',')} data-scene-objects={inspection.sceneWorkers?.map(w=>w.uuid).join(',')} data-workers={workers.length} data-rendered={inspection.sceneWorkers?.length||0} data-camera-command={`${camera.type}:${camera.room||camera.id||""}`}>
 <header className="office3d-toolbar"><div><b><i/> LIVING AI OFFICE <span>3D</span></b><small>{domainData.loading?'Menghubungkan roster…':domainData.stale||domainData.error?'Data terakhir · koneksi belum diperbarui':`${workers.length} worker · 10 room · penempatan visual`}</small></div><div className="office3d-actions"><select aria-label="Fokus ruang" value={camera.type==='room'?camera.room:''} onChange={e=>e.target.value?view('room',e.target.value):view('fit')}><option value="">Seluruh kantor</option>{[...canonicalRooms,...ambientZones].map(r=><option key={r.id} value={r.id}>{r.label}</option>)}</select><button onClick={()=>view('fit')}><RotateCcw size={15}/> Reset</button><button onClick={()=>view('angle')}><Camera size={15}/> Sudut</button><button disabled={!selected} onClick={()=>view('focus')}><Focus size={15}/> Fokus</button></div></header>
 <div className="office3d-layout"><div className="office3d-canvas"><Boundary onFallback={onFallback}><Suspense fallback={<div className="office3d-loading">Memuat kantor & karakter…</div>}><Canvas orthographic camera={CAMERA} shadows={SHADOWS} dpr={DPR} gl={GL}><FullScene world={world} workers={workers} selectedId={selectedId} onSelect={onSelect} reduced={reduced} cameraCommand={camera} onInspect={setInspection} onRoom={room=>view('room',room)}/></Canvas></Suspense></Boundary><div className="office3d-hint"><Move size={13}/> Orbit · klik kanan / dua jari untuk geser · scroll untuk zoom</div></div>
 <aside className="office3d-detail"><span className="office3d-eyebrow">WORKER ROSTER</span><h2>Di dalam Office</h2><div className="office3d-roster">{workers.map(w=><button key={w.id} aria-pressed={selectedId===w.id} onClick={()=>onSelect(w.id)}><i style={{background:w.variant}}>{w.shortName}</i><span><strong>{w.name}</strong><small>{w.status}</small></span><em className={w.available?'online':''}/></button>)}</div>
 {!workers.length&&!domainData.loading&&<p>Roster belum tersedia. Tidak ada worker contoh pada mode live.</p>}
 <details><summary>Room & posisi visual</summary><p className="office3d-note">Jumlah di bawah adalah posisi di scene. API belum menyediakan occupancy atau koordinat room.</p>{canonicalRooms.map(r=><button className="office3d-room-row" key={r.id} onClick={()=>view('room',r.id)}><span>{r.label||r.name}</span><b>{inspection.motions.filter(m=>m.room===r.id).length}</b></button>)}</details>
 {selected&&<div className="office3d-selected"><b>{selected.name}</b><small>{selected.role}</small><p>{selected.task||'Tidak ada tugas aktif'}</p><small>{inspection.motions.find(m=>m.id===selectedId)?.phase}</small></div>}
 {(duplicates.length>0||audit.missing.length>0)&&<p role="status">Periksa mapping roster: {duplicates.length} ID duplikat, {audit.missing.length} belum ditempatkan.</p>}
 {import.meta.env.DEV&&devControls?.(world,inspection)}
 <p className="office3d-note">{reduced?'Gerakan dikurangi. Perjalanan idle dinonaktifkan.':'Idle dapat berkunjung singkat. Status dan tugas tetap mengikuti API.'}</p></aside></div>
 <footer className="office3d-footer"><span>{inspection.sceneWorkers?.length||0} / {audit.authoritative} worker · {workers.filter(w=>w.working).length} bekerja</span><span>{inspection.calls||'—'} draw calls · {inspection.triangles?.toLocaleString()||'—'} triangles</span></footer></section>;
}
