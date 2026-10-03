import { useEffect, useState } from 'react';

// Read-only roster transport. All visual movement is owned by the 3D scene.
export function useLivingOffice() {
    const [domainData, setDomainData] = useState({ rows: [], loading: true, stale: false, error: false });
    useEffect(() => {
        let live = true;
        const poll = async () => {
            try {
                const response = await fetch('/office/api/agents');
                if (response.status === 401 || response.status === 419) {
                    window.location.href = '/office/login';
                    return;
                }
                if (!response.ok) throw new Error(`Office roster HTTP ${response.status}`);
                const rows = await response.json();
                if (!Array.isArray(rows)) throw new Error('Invalid Office roster');
                if (live) setDomainData({ rows, loading: false, stale: false, error: false });
            } catch {
                if (live) setDomainData(previous => ({ ...previous, loading: false, stale: true, error: true }));
            }
        };
        poll();
        const timer = window.setInterval(poll, 4000);
        return () => { live = false; window.clearInterval(timer); };
    }, []);
    return { domainData };
}
