import React, { lazy, Suspense, useState } from 'react';
import './office-renderer.css';
const Preview=lazy(()=>import('./preview3d/Office3DPreview'));
export default function OfficeRenderer({children,selectedId,onSelect,domainData}) {
    const [preview,setPreview]=useState(()=>new URLSearchParams(location.search).get('office3d')!=='0');
    return <div className="office-renderer"><div className="office-renderer-tabs" aria-label="Renderer Office"><button aria-pressed={!preview} onClick={()=>setPreview(false)}>Office</button><button aria-pressed={preview} onClick={()=>setPreview(true)}>Office 3D</button></div>{preview?<Suspense fallback={<p>Memuat preview 3D…</p>}><Preview domainData={domainData} selectedId={selectedId} onSelect={onSelect} onFallback={()=>setPreview(false)} /></Suspense>:children}</div>;
}
