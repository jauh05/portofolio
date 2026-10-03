import React, { useEffect, useMemo, useRef, useState } from 'react';
import { useFrame, useThree } from '@react-three/fiber';
import { OrbitControls, Html } from '@react-three/drei';
import * as THREE from 'three';
import Building, { SlidingDoor, DecorativeServerLights } from './Building';
import WorkerModel from '../WorkerModel';
import { canonicalRooms, ambientZones, roomById, WORLD_BOUNDS } from './layout';
import { stepWorld, syncWorld, visualRoom } from './navigation';
import { palette as p } from '../palette';
function Camera({command,world,reduced}){
 const {camera,size,setFrameloop}=useThree(),controls=useRef(),transition=useRef(null);
 useEffect(()=>{const change=()=>setFrameloop(document.hidden?'never':'always');change();document.addEventListener('visibilitychange',change);return()=>document.removeEventListener('visibilitychange',change);},[setFrameloop]);
 useEffect(()=>{const worker=world.current.motions.get(command.id),room=roomById[command.room]||ambientZones.find(z=>z.id===command.room);const focus=command.type==='focus'&&worker,area=command.type==='room'&&room;
 const target=new THREE.Vector3(focus?worker.x:area?room.x+room.width/2:0,.5,focus?worker.z:area?room.z+room.depth/2:3);
 const direction=new THREE.Vector3(command.type==='angle'?-8:4,17,22).normalize();const pos=target.clone().addScaledVector(direction,65);
 const virtual=camera.clone();virtual.position.copy(pos);virtual.lookAt(target);virtual.updateMatrixWorld();const min=focus?[target.x-3,0,target.z-3]:area?[room.x,0,room.z]:WORLD_BOUNDS.min,max=focus?[target.x+3,2.5,target.z+3]:area?[room.x+room.width,2.5,(room.front??room.z+room.depth)]:WORLD_BOUNDS.max;
 let left=Infinity,right=-Infinity,top=-Infinity,bottom=Infinity;for(const x of [min[0],max[0]])for(const y of [min[1],max[1]])for(const z of [min[2],max[2]]){const v=new THREE.Vector3(x,y,z).applyMatrix4(virtual.matrixWorldInverse);left=Math.min(left,v.x);right=Math.max(right,v.x);top=Math.max(top,v.y);bottom=Math.min(bottom,v.y);}
 const zoom=Math.min(size.width/(right-left),size.height/(top-bottom))*.91;
 transition.current={pos,target,zoom};if(reduced){camera.position.copy(pos);camera.zoom=zoom;camera.updateProjectionMatrix();controls.current?.target.copy(target);controls.current?.update();transition.current=null;}
 },[command,size.width,size.height,camera,reduced,world]);
 useFrame((_,dt)=>{const t=transition.current,c=controls.current;if(t&&c){const alpha=1-Math.exp(-dt*7);camera.position.lerp(t.pos,alpha);c.target.lerp(t.target,alpha);camera.zoom=THREE.MathUtils.lerp(camera.zoom,t.zoom,alpha);camera.updateProjectionMatrix();c.update();if(camera.position.distanceTo(t.pos)<.01)transition.current=null;}});
 return <OrbitControls ref={controls} makeDefault enableDamping dampingFactor={.12} minPolarAngle={.25} maxPolarAngle={1.3} minZoom={6} maxZoom={180} onStart={()=>{transition.current=null;}}/>;
}
function Avatar({worker,world,...props}){const motion=useMemo(()=>({get current(){return world.current.motions.get(worker.id);}}),[world,worker.id]);return <WorkerModel worker={worker} motion={motion} {...props}/>;}
export default function FullScene({world,workers,selectedId,onSelect,reduced,paused,cameraCommand,onInspect,onRoom}){
 const {gl,scene}=useThree(),tick=useRef(0);const [hovered,setHovered]=useState(null);const taskHistory=useRef(new Map()),recentTasks=useRef(new Map());
 for(const w of workers){if(taskHistory.current.has(w.id)&&taskHistory.current.get(w.id)!==w.task&&w.task)recentTasks.current.set(w.id,performance.now());taskHistory.current.set(w.id,w.task);}syncWorld(world.current,workers);
 useFrame((_,dt)=>{if(!paused&&!(import.meta.env.DEV&&world.current.devPaused))stepWorld(world.current,dt,{reduced});if(import.meta.env.DEV&&world.current.devPauseAt){const m=world.current.motions.get(selectedId);if(m&&(world.current.devPauseAt==='walking'?m.moving&&m.route[m.index]?.anchor?.includes('hall'):world.current.devPauseAt==='door'?m.route[m.index]?.door&&m.moving: m.phase==='idle')){world.current.devPaused=true;world.current.devPauseAt=null;}}tick.current+=dt;if(tick.current>.25){tick.current=0;let meshes=0;const sceneWorkers=[];scene.traverse(o=>{if(o.isMesh&&o.visible)meshes++;if(o.userData.officeWorkerId)sceneWorkers.push({id:o.userData.officeWorkerId,uuid:o.uuid});});onInspect({sceneWorkers,motions:[...world.current.motions.values()].map(m=>({id:m.id,phase:m.phase,room:visualRoom(m),x:m.x,z:m.z,seated:m.seated,station:m.station,target:m.target})),doors:[...world.current.doors].map(([id,d])=>({id,open:d.open,queue:d.queue.length})),calls:gl.info.render.calls,triangles:gl.info.render.triangles,meshes});}});
 // At most one task bubble in the overview. Selection has priority, so bubbles cannot overlap.
 const bubbleId=workers.find(w=>w.id===selectedId&&w.task)?.id||workers.find(w=>w.working&&w.task)?.id||workers.find(w=>w.task&&performance.now()-(recentTasks.current.get(w.id)||-Infinity)<5000)?.id||workers.find(w=>w.id===hovered&&w.task)?.id;
 return <><color attach="background" args={[p.soft]}/><hemisphereLight args={[p.white,'#99b2cc',2]}/><directionalLight position={[-12,25,10]} intensity={2.3} castShadow shadow-mapSize={[2048,2048]} shadow-camera-left={-28} shadow-camera-right={28} shadow-camera-top={28} shadow-camera-bottom={-28} shadow-normalBias={.04}/><directionalLight position={[15,10,-15]} intensity={1}/>
 <Building/><DecorativeServerLights reduced={reduced}/>{canonicalRooms.map(r=><React.Fragment key={r.id}><SlidingDoor room={r} world={world}/><Html position={[r.x+r.width/2,.55,r.front+.15]} center zIndexRange={[8,0]}><button className="office3d-room" title={r.id==='server'?'Lampu dekoratif · bukan indikator kesehatan server':r.label} onClick={()=>onRoom(r.id)}>{r.label||r.name}<small title="Posisi visual, bukan occupancy API">{onInspect&&world.current.motions.size?`${[...world.current.motions.values()].filter(m=>visualRoom(m)===r.id).length}/${r.capacity}`:''}</small></button></Html></React.Fragment>)}
 {ambientZones.map(z=><Html key={z.id} position={[z.x+z.width/2,.4,z.z+z.depth]} center zIndexRange={[7,0]}><span className="office3d-zone">{z.label} · visual</span></Html>)}
 {workers.filter(w=>world.current.motions.has(w.id)).map(w=><Avatar key={w.id} world={world} worker={w} selected={w.id===selectedId} onSelect={()=>onSelect(w.id)} reduced={reduced||!w.available} paused={paused||(import.meta.env.DEV&&world.current.devPaused)} onHover={value=>setHovered(value?w.id:null)} showBubble={w.id===bubbleId}/>)}
 <Camera command={cameraCommand} world={world} reduced={reduced}/></>;
}
