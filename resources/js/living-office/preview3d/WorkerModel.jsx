import React, { useEffect, useMemo, useRef } from 'react';
import { useFrame } from '@react-three/fiber';
import { useGLTF } from '@react-three/drei';
import { clone } from 'three/examples/jsm/utils/SkeletonUtils.js';
import * as THREE from 'three';
import TaskBubble from './TaskBubble';
import { workerVisualVariant } from './full/visualProfiles';
const V = new THREE.Vector3(), Q = new THREE.Quaternion();
const modelUrl = import.meta.env.DEV && window.location.pathname.startsWith('/tests/visual/')
    ? '/public/models/living-office/casual-worker.glb' : '/models/living-office/casual-worker.glb';
const modeFor = id => id === 'trent' ? 'monitor' : id.includes('planner') ? 'plan' : id.includes('analyst') ? 'analyze' : id.includes('article') || id.includes('threads') ? 'write' : 'design';
function snapshot(bones) { return bones.map(b => ({ p: b.position.clone(), q: b.quaternion.clone() })); }
function aim(bone, child, targetDirection) {
    bone.updateWorldMatrix(true,true);
    const start = bone.getWorldPosition(new THREE.Vector3());
    const direction = child.getWorldPosition(new THREE.Vector3()).sub(start).normalize();
    const world = bone.getWorldQuaternion(new THREE.Quaternion());
    const delta = new THREE.Quaternion().setFromUnitVectors(direction, new THREE.Vector3(...targetDirection).normalize());
    world.premultiply(delta);
    const parent = bone.parent.getWorldQuaternion(new THREE.Quaternion()).invert();
    bone.quaternion.copy(parent.multiply(world)); bone.updateWorldMatrix(true,true);
}
export default function WorkerModel({ motion, worker, selected, onSelect, reduced, paused, showBubble=true, onHover }) {
    const gltf = useGLTF(modelUrl);
    const group = useRef(), bubble = useRef();
    const visual = useMemo(() => workerVisualVariant(worker.id), [worker.id]);
    const rig = useMemo(() => {
        const model = clone(gltf.scene); const bones = [];
        model.traverse(o => { if(o.isBone) bones.push(o); if(o.isMesh) {o.castShadow=true;o.receiveShadow=true; o.material=o.material.clone();
            const name=o.material.name;
            if(name==='Red_Dark')o.material.color.set(visual.pants);
            if(name==='LightBlue')o.material.color.set(visual.shirt);
            if(name==='LightBrown')o.material.color.set(visual.jacket?visual.pants:visual.shirt);
            if(name==='Hair'||name==='Eyebrows')o.material.color.set(visual.hair);
            if(name==='Skin')o.material.color.set(visual.skin);
            if(name==='Skin_Darker')o.material.color.set(visual.skin).multiplyScalar(.83);
        } });
        const mixer = new THREE.AnimationMixer(model);
        const idle = mixer.clipAction(gltf.animations.find(c=>c.name==='Idle_Neutral')); idle.play(); mixer.update(0); model.updateMatrixWorld(true);
        const neutral = snapshot(bones);
        const bone = name => model.getObjectByName(name);
        const shinWorld = ['L','R'].map(side=>bone(`LowerLeg${side}`).getWorldQuaternion(new THREE.Quaternion()));
        bone('Body').position.y -= .39; bone('Torso').position.z += .07; model.updateMatrixWorld(true);
        ['L','R'].forEach((side,i)=>{
            aim(bone(`UpperLeg${side}`),bone(`LowerLeg${side}`),[side==='L'?.025:-.025,-.05,1]);
            bone(`LowerLeg${side}`).quaternion.copy(bone(`LowerLeg${side}`).parent.getWorldQuaternion(new THREE.Quaternion()).invert().multiply(shinWorld[i]));
            bone(`Foot${side}`).position.z += .35;
            aim(bone(`UpperArm${side}`),bone(`LowerArm${side}`),[side==='L'?.08:-.08,-1,.35]);
            aim(bone(`LowerArm${side}`),bone(`Wrist${side}`),[side==='L'?-.12:.12,.02,1]);
            aim(bone(`Wrist${side}`),bone(`Middle1${side}`),[0,-.1,1]);
        });
        model.updateMatrixWorld(true); const sitting = snapshot(bones);
        bones.forEach((b,i)=>{ b.position.copy(neutral[i].p); b.quaternion.copy(neutral[i].q); });
        const walkClip = gltf.animations.find(c=>c.name==='Walk').clone();
        // In-place gait only; world translation belongs to navigation.
        walkClip.tracks = walkClip.tracks.filter(t=>!/^Root\.position$/.test(t.name));
        const walk = mixer.clipAction(walkClip); walk.play(); walk.weight=0;
        return { model,bones,neutral,sitting,mixer,idle,walk,head:bone('Head'),torso:bone('Torso'),wrists:[bone('WristL'),bone('WristR')], locomotion:0 };
    },[gltf,worker.id,visual]);
    useEffect(()=>()=>{rig.mixer.stopAllAction();rig.mixer.uncacheRoot(rig.model);rig.model.traverse(o=>{if(o.isMesh)o.material.dispose();});},[rig]);
    useFrame((state,dt)=>{
        const m=motion.current; if(!group.current)return;
        group.current.position.set(m.x,0,m.z);group.current.rotation.y=m.yaw;
        if(!paused){
        rig.locomotion=THREE.MathUtils.damp(rig.locomotion,m.moving&&!reduced?1:0,10,Math.min(dt,.05));
        rig.walk.weight=rig.locomotion;rig.idle.weight=1-rig.locomotion;
        }
        rig.mixer.update(paused||reduced?0:Math.min(dt,.05));
        if(m.seated>0) rig.bones.forEach((b,i)=>{b.position.lerp(rig.sitting[i].p,m.seated);b.quaternion.slerp(rig.sitting[i].q,m.seated);});
        if(m.seated>.99&&!reduced&&!paused){
            const phase=state.clock.elapsedTime+(visual.hairStyle*3.7),mode=modeFor(worker.id);
            const active=worker.working&&!worker.stale&&!worker.error;
            const typing=active&&Math.sin(phase*.31)<.74;
            const cadence=mode==='write'?6.1:mode==='design'?4.8:mode==='monitor'?2.1:3.2;
            rig.wrists.forEach((b,i)=>{const pulse=Math.sin(phase*cadence+i*2.3);Q.setFromAxisAngle(V.set(1,0,0),pulse*(typing?(mode==='monitor'?.012:.027):.003));b.quaternion.multiply(Q);});
            rig.head.rotation.y+=Math.sin(phase*(active?.28:.16))*(active?.12:.19);
            rig.head.rotation.x+=Math.sin(phase*.71)*.025;
            rig.torso.rotation.z+=Math.sin(phase*.8)*.012;
        }else if(m.phase==='idle'&&!reduced&&!paused){
            const phase=state.clock.elapsedTime+visual.hairStyle*2;
            rig.head.rotation.y+=Math.sin(phase*.24)*.14;
            rig.torso.rotation.z+=Math.sin(phase*.65)*.025;
        }
        group.current.updateWorldMatrix(true,true);rig.head.getWorldPosition(V);group.current.worldToLocal(V);bubble.current.position.copy(V);bubble.current.position.y+=.85;
    });
    return <group ref={group} userData={{officeWorkerId:worker.id}} onPointerOver={e=>{e.stopPropagation();onHover?.(true);}} onPointerOut={()=>onHover?.(false)} onClick={e=>{e.stopPropagation();onSelect();}}>
        <primitive object={rig.model} />
        <group position={[0,1.79,0]}>
            {visual.hairStyle===1&&<mesh position={[0,.085,-.06]}><sphereGeometry args={[.105,12,8]}/><meshStandardMaterial color={visual.hair}/></mesh>}
            {visual.hairStyle===2&&<mesh position={[0,.06,0]}><cylinderGeometry args={[.12,.13,.07,12]}/><meshStandardMaterial color={visual.hair}/></mesh>}
            {visual.hairStyle===3&&<mesh position={[0,.09,-.07]}><sphereGeometry args={[.065,10,8]}/><meshStandardMaterial color={visual.hair}/></mesh>}
            {visual.glasses&&<group position={[0,-.075,.153]}>{[-.052,.052].map(x=><mesh key={x} position={[x,0,0]}><torusGeometry args={[.041,.008,5,12]}/><meshStandardMaterial color="#1d344f"/></mesh>)}<mesh><boxGeometry args={[.04,.008,.01]}/><meshStandardMaterial color="#1d344f"/></mesh></group>}
        </group>
        {selected&&<mesh rotation={[-Math.PI/2,0,0]} position={[0,.08,0]}><ringGeometry args={[.39,.44,48]} /><meshBasicMaterial color="#1570ef" transparent opacity={.85} /></mesh>}
        <group ref={bubble}><TaskBubble worker={worker} onSelect={onSelect} showName={selected} showTask={showBubble}/></group>
    </group>;
}
