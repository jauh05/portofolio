import React from 'react';
import { Html } from '@react-three/drei';
export default function TaskBubble({ worker, onSelect, showName=false, showTask=true }) {
    const hasTask=showTask&&(worker.task||worker.stale||worker.error);
    if (!hasTask&&!showName) return null;
    return <Html center zIndexRange={[20,0]} style={{pointerEvents:'auto'}}><div className="office3d-bubble-group">
        {hasTask&&<button className="office3d-bubble" onClick={onSelect} title={worker.task||'Data belum tersedia'}>
            {(worker.stale||worker.error)&&<small>Data belum diperbarui</small>}
            <span>{worker.task||'Koneksi data belum tersedia'}</span>
            {worker.progress!==null&&!worker.stale&&!worker.error&&<i style={{width:`${worker.progress}%`}}/>}
        </button>}
        {showName&&<button className="office3d-name" onClick={onSelect}><strong>{worker.name}</strong><small>{worker.role}</small></button>}
    </div></Html>;
}
