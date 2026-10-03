import React, { useEffect, useRef } from 'react';
import { useFrame, useThree } from '@react-three/fiber';
import { OrbitControls, Html } from '@react-three/drei';
import * as THREE from 'three';
import { FloorRoom } from './Furniture';
import Door from './Door';
import WorkerModel from './WorkerModel';
import { stepMotion } from './navigation';
import { palette as p } from './palette';
function Camera({ command, motion }) {
    const { camera,size,invalidate,setFrameloop }=useThree(); const controls=useRef();
    useEffect(()=>{
        const visibility=()=>setFrameloop(document.hidden?'never':'always'); visibility(); document.addEventListener('visibilitychange',visibility);return()=>document.removeEventListener('visibilitychange',visibility);
    },[setFrameloop]);
    useEffect(()=>{
        const focus=command.type==='focus'; const target=new THREE.Vector3(focus?motion.current.x:0,focus?.8:.6,focus?motion.current.z:.7);
        const direction=(focus?new THREE.Vector3(8,6,-6):new THREE.Vector3(command.type==='angle'?-9:5,10,13)).normalize();
        camera.position.copy(target).addScaledVector(direction,22);camera.lookAt(target);camera.updateMatrixWorld();
        const box=focus?new THREE.Box3(new THREE.Vector3(target.x-1.8,0,target.z-1.7),new THREE.Vector3(target.x+1.8,2.3,target.z+1.7)):new THREE.Box3(new THREE.Vector3(-5.4,-.4,-3.3),new THREE.Vector3(5.4,2.5,4.8));
        let minX=Infinity,maxX=-Infinity,minY=Infinity,maxY=-Infinity;
        for(const x of [box.min.x,box.max.x])for(const y of [box.min.y,box.max.y])for(const z of [box.min.z,box.max.z]){const v=new THREE.Vector3(x,y,z).applyMatrix4(camera.matrixWorldInverse);minX=Math.min(minX,v.x);maxX=Math.max(maxX,v.x);minY=Math.min(minY,v.y);maxY=Math.max(maxY,v.y);}
        camera.zoom=Math.min(size.width/(maxX-minX),size.height/(maxY-minY))*(focus?.92:1.04);camera.updateProjectionMatrix();
        if(controls.current){controls.current.target.copy(target);controls.current.update();} invalidate();
    },[command,size.width,size.height,camera,invalidate,motion]);
    return <OrbitControls ref={controls} makeDefault enableDamping dampingFactor={.12} minPolarAngle={.3} maxPolarAngle={1.3} minZoom={25} maxZoom={240} />;
}
export default function OfficeScene({ motion,worker,selected,onSelect,reduced,paused,cameraCommand,onInspect }) {
    const { gl,scene,camera }=useThree(); const elapsed=useRef(0);
    useFrame((_,dt)=>{
        if(!paused)stepMotion(motion.current,dt,reduced);
        elapsed.current+=dt;if(elapsed.current>.25){elapsed.current=0;let meshes=0;scene.traverse(o=>{if(o.isMesh)meshes++;});onInspect({phase:motion.current.phase,door:motion.current.door,x:motion.current.x,z:motion.current.z,meshes,camera:JSON.stringify({p:camera.position.toArray(),q:camera.quaternion.toArray(),zoom:camera.zoom,left:camera.left,right:camera.right,top:camera.top,bottom:camera.bottom}),triangles:gl.info.render.triangles,calls:gl.info.render.calls});}
    });
    return <>
        <color attach="background" args={[p.soft]} />
        <hemisphereLight args={[p.white,'#b6c9de',2]} />
        <directionalLight position={[-3,9,5]} intensity={2.3} castShadow shadow-mapSize={[2048,2048]} shadow-camera-left={-8} shadow-camera-right={8} shadow-camera-top={8} shadow-camera-bottom={-8} shadow-normalBias={.025} shadow-bias={-.0001} />
        <directionalLight position={[5,5,-3]} intensity={1.3} />
        <FloorRoom /><Door motion={motion} />
        <Html position={[-3.6,2.1,-2.45]} center zIndexRange={[15,0]}><button className="office3d-room" onClick={onSelect}><span>▦</span> Open Office <small>{worker.available&&motion.current.z<2?1:0} / 2</small></button></Html>
        {worker.available&&<WorkerModel motion={motion} worker={worker} selected={selected} onSelect={onSelect} reduced={reduced} paused={paused} />}
        <Camera command={cameraCommand} motion={motion} />
    </>;
}
