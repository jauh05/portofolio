import { useEffect, useState } from 'react';
export function usePreviewData(enabled) {
    const [state, setState] = useState({ rows: [], loading: true, stale: false, error: false });
    useEffect(() => {
        if (!enabled) return;
        let disposed = false, lastSuccess = 0, busy = false;
        const controller = new AbortController();
        const refresh = async () => {
            if (busy || document.hidden) return;
            busy = true;
            try {
                const response = await fetch('/office/api/agents', { headers: { Accept: 'application/json' }, signal: controller.signal });
                if (!response.ok) throw new Error('Office data unavailable');
                const rows = await response.json();
                if (!Array.isArray(rows)) throw new Error('Invalid agent response');
                lastSuccess = Date.now();
                if (!disposed) setState({ rows, loading: false, stale: false, error: false });
            } catch (error) {
                if (!disposed && error.name !== 'AbortError') setState(s => ({ ...s, loading: false, error: true, stale: !!lastSuccess }));
            } finally { busy = false; }
        };
        refresh(); const timer = setInterval(() => { if (lastSuccess && Date.now() - lastSuccess > 15000) setState(s => ({ ...s, stale: true })); refresh(); }, 5000);
        document.addEventListener('visibilitychange', refresh);
        return () => { disposed = true; controller.abort(); clearInterval(timer); document.removeEventListener('visibilitychange', refresh); };
    }, [enabled]);
    return state;
}
