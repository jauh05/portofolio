import React, { Component, lazy, Suspense, useState } from 'react';
import './office-renderer.css';

const Office3D = lazy(() => import('./preview3d/Office3DPreview'));

function supportsWebGL() {
    try {
        const canvas = document.createElement('canvas');
        return Boolean(canvas.getContext('webgl2') || canvas.getContext('webgl'));
    } catch { return false; }
}

class RendererBoundary extends Component {
    state = { failed: false };
    static getDerivedStateFromError() { return { failed: true }; }
    render() {
        return this.state.failed ? <div className="office-renderer-error" role="alert">
            <strong>Office 3D tidak dapat ditampilkan.</strong>
            <p>Periksa dukungan grafis browser, lalu muat ulang halaman.</p>
            <button onClick={() => window.location.reload()}>Muat ulang</button>
        </div> : this.props.children;
    }
}

export default function OfficeRenderer({ selectedId, onSelect, domainData }) {
    const [supported] = useState(supportsWebGL);
    return <div className="office-renderer">
        {supported ? <RendererBoundary><Suspense fallback={<div className="office-renderer-loading">Memuat Living AI Office 3D…</div>}>
            <Office3D domainData={domainData} selectedId={selectedId} onSelect={onSelect} />
        </Suspense></RendererBoundary> : <div className="office-renderer-error" role="alert">
            <strong>Office 3D membutuhkan WebGL.</strong>
            <p>Aktifkan akselerasi grafis di browser lalu coba lagi.</p>
            <button onClick={() => window.location.reload()}>Coba lagi</button>
        </div>}
    </div>;
}
