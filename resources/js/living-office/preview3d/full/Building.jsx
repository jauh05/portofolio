import React, { useLayoutEffect, useRef } from 'react';
import { useFrame } from '@react-three/fiber';
import * as THREE from 'three';
import { Block, Plant, Workstation, Chair } from '../Furniture';
import { canonicalRooms, ambientZones, stations } from './layout';
import { palette as p } from '../palette';
import WorkstationScreen from './WorkstationScreen';
// Repeated opaque furniture is instanced once; animated doors and rigs stay independent.
function StaticBatch({children}) {
 const root=useRef();
 useLayoutEffect(()=>{const groups=new Map(),created=[];root.current.updateWorldMatrix(true,true);const inverse=root.current.matrixWorld.clone().invert();root.current.traverse(o=>{if(!o.isMesh||Array.isArray(o.material)||o.material.transparent)return;const key=o.geometry.uuid+o.material.uuid;if(!groups.has(key))groups.set(key,[]);groups.get(key).push(o);});
 for(const meshes of groups.values()){if(meshes.length<3)continue;const inst=new THREE.InstancedMesh(meshes[0].geometry,meshes[0].material,meshes.length);meshes.forEach((m,i)=>{inst.setMatrixAt(i,new THREE.Matrix4().multiplyMatrices(inverse,m.matrixWorld));m.visible=false;});inst.castShadow=true;inst.receiveShadow=true;inst.computeBoundingSphere();root.current.add(inst);created.push([inst,meshes]);}
 return()=>created.forEach(([i,meshes])=>{i.removeFromParent();i.dispose();meshes.forEach(m=>m.visible=true);});},[]);
 return <group ref={root}>{children}</group>;
}
function Screen({x,z,width=2.7}){return <group><Block position={[x,1.55,z]} size={[width,1.3,.1]} color={p.ink}/><Block position={[x,1.55,z+.06]} size={[width-.14,1.16,.02]} color={p.blueDark}/>{[0,1,2,3,4].map(i=><Block key={i} position={[x-width*.35+i*width*.17,1.25+(i%3)*.12,z+.08]} size={[.18,.25+(i%3)*.24,.015]} color={p.soft}/>)}</group>}
function Shelf({x,z}){return <group><Block position={[x,.85,z]} size={[1.4,1.7,.45]} color={p.line}/>{[.2,.7,1.2].map(y=><group key={y}><Block position={[x,y,z+.2]} size={[1.4,.06,.5]}/>{[0,1,2,3,4].map(i=><Block key={i} position={[x-.5+i*.23,y+.2,z+.25]} size={[.15,.34,.3]} color={i%2?p.blue:p.ink}/>)}</group>)}</group>}
function Sofa({x,z}){return <group><Block position={[x,.4,z]} size={[2.5,.6,.85]} color={p.blue}/><Block position={[x,.85,z+.4]} size={[2.5,.65,.2]} color={p.line}/>{[-1.2,1.2].map(a=><Block key={a} position={[x+a,.7,z]} size={[.2,.5,1]} color={p.line}/>)}</group>}
export function SlidingDoor({room,world}){
 const left=useRef(),right=useRef();useFrame(()=>{const open=world.current.doors.get(room.id)?.open||0;if(left.current){left.current.position.x=room.doorX-.43-open*.8;right.current.position.x=room.doorX+.43+open*.8;}});
 return <group><Block position={[room.doorX,1.65,room.front]} size={[2,.1,.18]} color={p.line}/>{[-1,1].map(s=><Block key={s} position={[room.doorX+s*.94,.8,room.front]} size={[.09,1.65,.16]} color={p.line}/>)}{[left,right].map((ref,i)=><group ref={ref} key={i} position={[room.doorX+(i? .43:-.43),0,room.front]}><Block position={[0,.22,0]} size={[.83,.44,.08]} color={p.line}/><mesh position={[0,.95,0]}><boxGeometry args={[.83,1,.035]}/><meshStandardMaterial color={p.blue} transparent opacity={.23} depthWrite={false}/></mesh><Block position={[i?-.3:.3,.85,.07]} size={[.035,.25,.04]} color={p.ink}/></group>)}</group>;
}
export default function Building({workers=[],world,selectedId,reduced=false}){
 const stationWorkers=new Map(workers.map(w=>[world.current.assignments.get(w.id),w]));
 return <><StaticBatch>
 <Block position={[0,-.22,3]} size={[40,.4,32]} color={p.line}/>
 <Block position={[0,-.008,3]} size={[39.6,.025,31.6]} color={p.soft}/>
 {[-3.2,5.2,13.4].map(z=><group key={z}><Block position={[0,.007,z]} size={[38,.02,1.5]} color={p.surface}/>{Array.from({length:38},(_,i)=><Block key={i} position={[-18.5+i,.022,z]} size={[.012,.006,1.5]} color={p.line}/>)}</group>)}
 {canonicalRooms.map(r=><group key={r.id}>
 <Block position={[r.x+r.width/2,.008,r.z+r.depth/2]} size={[r.width,.04,r.depth]} color={p.white}/>
 <Block position={[r.x+r.width/2,.03,r.z+r.depth/2-.45]} size={[r.width-1,.03,r.depth-1.8]} color={r.id==='lounge'?p.line:p.soft}/>
 <Block position={[r.x+r.width/2,.65,r.z]} size={[r.width,1.3,.12]}/><Block position={[r.x,.5,r.z+r.depth/2]} size={[.12,1,r.depth]}/><Block position={[r.x+r.width,.5,r.z+r.depth/2]} size={[.12,1,r.depth]}/>
 <Block position={[(r.x+r.doorX-.95)/2,.22,r.front]} size={[r.doorX-.95-r.x,.44,.10]}/>
 <mesh position={[(r.x+r.doorX-.95)/2,.91,r.front]}><boxGeometry args={[r.doorX-.95-r.x,.95,.035]}/><meshStandardMaterial color={p.blue} transparent opacity={.12} depthWrite={false}/></mesh>
 <Block position={[(r.x+r.doorX-.95)/2,1.4,r.front]} size={[r.doorX-.95-r.x,.035,.065]} color={p.line}/>
 <Plant position={[r.x+.55,0,r.z+.55]} scale={1.3}/><Plant position={[r.x+.5,0,r.front-.7]} scale={.85}/>
 {r.id!=='server'&&<Screen x={r.x+r.width/2} z={r.z+.12} width={r.id==='control'?4:2.4}/>}
 {['article','content','open'].includes(r.id)&&<Shelf x={r.x+r.width-1} z={r.z+.45}/>}
 </group>)}
 {stations.filter(s=>s.kind==='desk').map(s=><group key={s.id} position={[s.seat[0],0,s.seat[1]]} rotation={[0,s.yaw-Math.PI,0]}><group position={[0,0,-.05]}><Workstation x={0}/></group></group>)}
 <Block position={[11,.8,.7]} size={[8,.15,1]} color="#d3bb96"/>{[7.6,14.4].map(x=><Block key={x} position={[x,.4,.7]} size={[.15,.8,.6]} color={p.line}/>)}
 {stations.filter(s=>s.kind==='meeting').map(s=><group key={s.id} position={[... [s.seat[0],0,s.seat[1]]]} rotation={[0,s.yaw-Math.PI,0]}><Chair x={0} z={0}/></group>)}
 {stations.filter(s=>s.kind==='rest').map(s=><group key={s.id} position={[s.seat[0],0,s.seat[1]]}><Block position={[0,.4,0]} size={[1.3,.55,.85]} color={p.line}/><Block position={[0,.8,-.38]} size={[1.3,.7,.2]} color={p.blue}/>{[-.58,.58].map(x=><Block key={x} position={[x,.6,0]} size={[.2,.6,1]} color={p.line}/>)}</group>)}
 <Block position={[-1.5,.45,11.2]} size={[3,.12,.8]} color="#d3bb96"/>
 {[15.2,16.65].map(x=><group key={x}><Block position={[x,1.1,-11.35]} size={[1.3,2.2,.85]} color={p.ink}/>{[0,1,2,3,4,5].map(i=><group key={i}><Block position={[x,.3+i*.3,-10.91]} size={[1.1,.21,.04]} color={p.blueDark}/><Block position={[x+.38,.3+i*.3,-10.875]} size={[.06,.05,.015]} color={p.green}/></group>)}</group>)}
 <Block position={[-7.7,.8,-9.3]} size={[2.4,.12,1.3]} color="#d3bb96"/><Block position={[-7.7,.4,-9.3]} size={[.3,.8,.7]} color={p.line}/>
 <Block position={[1.5,1,-7.7]} size={[.045,2,.045]} color={p.ink}/><Block position={[1.5,.05,-7.7]} size={[.8,.08,.8]} color={p.ink}/><Block position={[1.5,2,-7.7]} size={[.8,.8,.12]} rotation={[0,-.6,0]} color={p.white}/>
 <Screen x={-14.4} z={-1.25} width={2}/><Screen x={-11.8} z={-1.25} width={2}/><Screen x={8.2} z={7.2} width={2.3}/><Screen x={14} z={7.2} width={2.3}/>
 {ambientZones.map(z=><group key={z.id}><Block position={[z.x+z.width/2,.03,z.z+z.depth/2]} size={[z.width,.04,z.depth]} color={p.white}/><Plant position={[z.x+z.width-.6,0,z.z+z.depth-.5]}/></group>)}
 <Sofa x={-13} z={17.4}/><Screen x={-13} z={16.0} width={3.4}/><Block position={[-13,.65,16]} size={[.2,1.3,.2]} color={p.ink}/><Block position={[-13,.4,16.35]} size={[2,.13,.55]} color="#d3bb96"/>
 <Block position={[-1, .55,17.6]} size={[6,1.1,.65]} color={p.line}/><Block position={[-1,1.14,17.6]} size={[6,.08,.75]} color="#d3bb96"/><Block position={[-2.6,1.5,17.6]} size={[.7,.65,.55]} color={p.ink}/><Block position={[1.6,1,17.6]} size={[.7,2,.7]} color={p.white}/>
 {[[-2.1,16.9],[-.5,16.9],[1,16.9]].map(([x,z])=><group key={x}><Block position={[x,.52,z]} size={[.48,.07,.45]} color={p.blueDark}/><Block position={[x,.27,z]} size={[.07,.5,.07]} color={p.ink}/><mesh position={[x,1.22,17.15]}><cylinderGeometry args={[.09,.075,.16,12]}/><meshStandardMaterial color={p.white}/></mesh></group>)}
 <Block position={[-2.6,1.51,17.87]} size={[.48,.17,.1]} color={p.blue}/>
 <Block position={[11,.65,17.4]} size={[6,1.3,.9]} color={p.blue}/><Block position={[11,1.34,17.4]} size={[6.3,.08,1]} color={p.white}/>
 </StaticBatch>
 {stations.filter(s=>s.kind==='desk').map(s=><group key={`screen-${s.id}`} position={[s.seat[0],0,s.seat[1]]} rotation={[0,s.yaw-Math.PI,0]}><group position={[0,0,-.05]}><WorkstationScreen worker={stationWorkers.get(s.id)} stationId={s.id} roomId={s.roomId} selected={stationWorkers.get(s.id)?.id===selectedId} reduced={reduced}/></group></group>)}
 </>;}

export function DecorativeServerLights({reduced}){
 const lamps=useRef(),material=useRef(),elapsed=useRef(0);
 useLayoutEffect(()=>{let i=0;for(const x of [15.2,16.65])for(let row=0;row<6;row++){const m=new THREE.Matrix4().makeTranslation(x+.38,.3+row*.3,-10.84);lamps.current.setMatrixAt(i++,m);}lamps.current.instanceMatrix.needsUpdate=true;},[]);
 useFrame((_,dt)=>{elapsed.current+=dt;if(material.current)material.current.opacity=reduced?.7:.65+Math.sin(elapsed.current*1.4)*.18;});
 return <instancedMesh ref={lamps} args={[null,null,12]}><boxGeometry args={[.065,.055,.02]}/><meshBasicMaterial ref={material} color="#9de1ff" transparent opacity={.7}/></instancedMesh>;
}
