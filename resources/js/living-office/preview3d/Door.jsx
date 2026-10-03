import React, { useRef } from 'react';
import { useFrame } from '@react-three/fiber';
import { Block } from './Furniture';
import { palette as p } from './palette';
export default function Door({ motion }) {
    const hinge = useRef();
    useFrame(() => { if (hinge.current) hinge.current.rotation.y = -Math.PI / 2 * motion.current.door; });
    return <group position={[2.55,0,2]}>
        <Block position={[0,1.1,0]} size={[.09,2.2,.12]} color={p.line} />
        <Block position={[1.6,1.1,0]} size={[.09,2.2,.12]} color={p.line} />
        <Block position={[.8,2.18,0]} size={[1.68,.09,.12]} />
        <group ref={hinge}>
            <mesh position={[.79,1.08,0]}><boxGeometry args={[1.48,2.03,.04]} /><meshPhysicalMaterial color={p.blue} transparent opacity={.23} roughness={.12} depthWrite={false} /></mesh>
            {[.055,1.53].map(x=><Block key={x} position={[x,1.08,0]} size={[.055,2.1,.065]} color="#7593ac" />)}
            {[.055,2.1].map(y=><Block key={y} position={[.79,y,0]} size={[1.53,.055,.065]} color="#7593ac" />)}
            <Block position={[1.38,1.04,.085]} size={[.04,.29,.06]} color={p.blueDark} />
        </group>
    </group>;
}
