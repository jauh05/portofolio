import React from 'react';
import { RoundedBoxGeometry } from 'three/examples/jsm/geometries/RoundedBoxGeometry.js';
import * as THREE from 'three';
import { DESKS } from './navigation';
import { palette as p } from './palette';
const box = new RoundedBoxGeometry(1, 1, 1, 2, .045);
const colors = [p.white, p.ink, p.blue, p.blueDark, p.soft, p.surface, p.line, p.green, '#7593ac', '#d3bb96', '#235b44'];
const materials = Object.fromEntries(colors.map(c => [c, new THREE.MeshStandardMaterial({ color: c, roughness: .65 })]));
const potGeometry=new THREE.CylinderGeometry(.23,.16,.44,16), stemGeometry=new THREE.CylinderGeometry(.018,.026,1,6), leafGeometry=new THREE.SphereGeometry(1,8,6), poleGeometry=new THREE.CylinderGeometry(.045,.045,.48,10), wheelGeometry=new THREE.CylinderGeometry(.06,.06,.055,10), cupGeometry=new THREE.CylinderGeometry(.07,.065,.15,12);
export function Block({ position, size, color = p.white, rotation, ...props }) { return <mesh geometry={box} material={materials[color] || materials[p.white]} position={position} scale={size} rotation={rotation} castShadow receiveShadow {...props} />; }
export function Plant({ position, scale = 1 }) {
    return <group position={position} scale={scale}>
        <mesh position={[0,.22,0]} castShadow geometry={potGeometry} material={materials[p.white]}/>
        <mesh position={[0,.72,0]} geometry={stemGeometry} material={materials["#235b44"]}/>
        {Array.from({ length: 9 }, (_, i) => <group key={i} position={[0, .5 + i*.08, 0]} rotation={[.3, i * 2.4, .65]}><mesh position={[0,.22,0]} castShadow scale={[.12,.34,.055]} geometry={leafGeometry} material={materials[i%2?"#235b44":p.green]}/></group>)}
    </group>;
}
export function Chair({ x, z }) {
    return <group position={[x, 0, z]}>
        <mesh position={[0,.27,0]} castShadow geometry={poleGeometry} material={materials["#7593ac"]}/>
        {[0,1,2,3,4].map(i => <group key={i} rotation={[0,i*Math.PI*.4,0]}><Block position={[.18,.075,0]} size={[.4,.045,.055]} color="#7593ac" /><mesh position={[.36,.065,0]} rotation={[Math.PI/2,0,0]} geometry={wheelGeometry} material={materials[p.ink]}/></group>)}
        <Block position={[0,.47,0]} size={[.62,.13,.58]} color={p.blue} />
        <Block position={[0,.8,.28]} size={[.59,.58,.10]} rotation={[-.1,0,0]} color={p.blueDark} />
        {[-.34,.34].map(v => <group key={v}><Block position={[v,.6,.05]} size={[.035,.26,.035]} color="#7593ac" /><Block position={[v,.73,0]} size={[.08,.045,.36]} color={p.ink} /></group>)}
    </group>;
}
export function Workstation({ x }) {
    return <group>
        <Block position={[x,.83,-1.05]} size={[2.45,.12,1.05]} />
        {[-.99,.99].map(v => <Block key={v} position={[x+v,.4,-1.05]} size={[.09,.8,.83]} color={p.line} />)}
        <Block position={[x+.84,.43,-1.04]} size={[.49,.7,.8]} />
        {[.26,.46,.65].map(y => <Block key={y} position={[x+.84,y,-.631]} size={[.16,.015,.02]} color="#7593ac" />)}
        <Block position={[x,1.27,-1.27]} size={[1.04,.61,.055]} color={p.ink} />
        <Block position={[x,1.28,-1.235]} size={[.94,.5,.012]} color={p.blueDark} />
        <Block position={[x,1,-1.29]} size={[.065,.32,.065]} color="#7593ac" />
        <Block position={[x,.905,-1.25]} size={[.38,.03,.24]} color="#7593ac" />
        <Block position={[x,.912,-.68]} size={[.63,.028,.22]} color={p.ink} />
        {Array.from({length:10},(_,i)=><Block key={i} position={[x-.27+i*.06,.928,-.68]} size={[.035,.008,.16]} color={p.line} />)}
        <Block position={[x+.46,.913,-.66]} size={[.11,.04,.16]} color={p.blue} />
        {[.04,.13,.22].map((h,i)=><Block key={i} position={[x-.32+i*.16,1.15+h/2,-1.225]} size={[.085,h,.01]} color={i===2?p.white:p.soft} />)}
        <Block position={[x+.2,1.44,-1.22]} size={[.32,.022,.01]} color={p.white} />
        <Block position={[x+.2,1.37,-1.22]} size={[.3,.015,.01]} color={p.line} />
        <mesh position={[x-.91,.96,-.79]} castShadow geometry={cupGeometry} material={materials[p.blue]}/>
        <Plant position={[x+.98,.9,-1.34]} scale={.31} />
        <Chair x={x} z={.05} />
    </group>;
}
export function FloorRoom() {
    return <group>
        <Block position={[0,-.2,.65]} size={[10.5,.3,8]} color={p.line} />
        <Block position={[0,-.035,-.5]} size={[10.1,.045,5]} color={p.surface} />
        <Block position={[0,-.03,3.3]} size={[10.1,.05,2.5]} color={p.soft} />
        {[-4,-3,-2,-1,0,1,2,3,4].map(x=><Block key={x} position={[x,.001,3.3]} size={[.012,.006,2.5]} color={p.line} />)}
        {[2.5,3.3,4.1].map(z=><Block key={z} position={[0,.001,z]} size={[10,.006,.012]} color={p.line} />)}
        <Block position={[0,.022,-.45]} size={[7.9,.025,3.5]} color={p.soft} />
        <Block position={[0,.55,-3]} size={[10.1,1.1,.15]} />
        <Block position={[-5,.55,-.5]} size={[.14,1.1,5]} />
        <Block position={[5,.55,-.5]} size={[.14,1.1,5]} />
        <Block position={[-1.22,.32,2]} size={[7.55,.64,.13]} />
        <Block position={[4.57,.32,2]} size={[.95,.64,.13]} />
        {[-4.95,-2.8,-.65,1.5,4.97].map(x=><Block key={x} position={[x,.78,2]} size={[.045,.9,.055]} color="#7593ac" />)}
        <mesh position={[-1.22,.92,2]}><boxGeometry args={[7.55,.57,.025]} /><meshPhysicalMaterial color={p.blue} transparent opacity={.13} roughness={.1} depthWrite={false} /></mesh>
        <Block position={[-1.22,1.22,2]} size={[7.55,.035,.045]} color={p.line} />
        <Block position={[0,1.3,-2.86]} size={[2.6,1.12,.09]} color={p.line} />
        <Block position={[0,1.3,-2.80]} size={[2.45,.97,.02]} />
        {[-.9,-.45,0,.45,.9].map((x,i)=><Block key={x} position={[x,1.3,-2.78]} size={[.24,.2+(i%3)*.15,.01]} color={i%2?p.blue:p.soft} />)}
        <Block position={[3.95,.74,-2.45]} size={[1.35,1.48,.55]} color={p.line} />
        {[.27,.77,1.23].map(y=><group key={y}><Block position={[3.95,y,-2.1]} size={[1.2,.04,.56]} />{[0,1,2,3,4].map(i=><Block key={i} position={[3.5+i*.2,y+.16,-2.14]} size={[.11,.28,.26]} color={i%2?p.blue:p.ink} rotation={[0,0,i===4?.12:0]} />)}</group>)}
        <Plant position={[-4.35,0,-2.3]} scale={1.4} /><Plant position={[4.55,0,1.3]} /><Plant position={[-4.4,0,1.35]} scale={.85} />
        {DESKS.map(d=><Workstation key={d.x} x={d.x} />)}
    </group>;
}
