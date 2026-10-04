import { useCallback, useEffect, useRef, useState } from 'react';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

async function request(url, options = {}) {
    const response = await fetch(url, {
        ...options,
        headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf(), ...(options.headers || {}) },
    });
    if (response.status === 401 || response.status === 419) {
        window.location.href = '/office/login';
        throw new Error('Session expired');
    }
    const body = await response.json().catch(() => ({}));
    if (!response.ok) throw new Error(body.message || 'Unable to load data');
    return body;
}

export function useOfficeData() {
    const [data, setData] = useState({ tasks: [], activity: [], content: [], brands: [], planner: { occurrences: [], upcoming: [], range: null }, reports: [], notifications: [], commands: [], summary: {}, unread: 0 });
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(true);
    const [toast, setToast] = useState(null);
    const initialized = useRef(false);
    const seenNotifications = useRef(new Set());

    const refresh = useCallback(async () => {
        try {
            const [tasks, activity, content, brands, planner, reports, notifications, commands, summary] = await Promise.all([
                request('/office/api/tasks'), request('/office/api/activity'), request('/office/api/content'),
                request('/office/api/content-brands'), request('/office/api/content-planner'), request('/office/api/analyst-reports'), request('/office/api/notifications'), request('/office/api/commands'), request('/office/api/summary'),
            ]);
            if (initialized.current) {
                const important = notifications.data.find((item) => !item.readAt && !seenNotifications.current.has(item.id));
                if (important) setToast(important);
            }
            notifications.data.forEach((item) => seenNotifications.current.add(item.id));
            initialized.current = true;
            setData({ tasks: tasks.data, activity: activity.data, content: content.data, brands: brands.data, planner, reports: reports.data, notifications: notifications.data, commands: commands.data, summary, unread: notifications.unread });
            setError(null);
        } catch (caught) {
            if (caught.message !== 'Session expired') setError(caught.message || 'Unable to load data');
        } finally { setLoading(false); }
    }, []);

    useEffect(() => {
        refresh();
        const interval = window.setInterval(refresh, 5000);
        return () => window.clearInterval(interval);
    }, [refresh]);
    useEffect(() => {
        if (!toast) return undefined;
        const timeout = window.setTimeout(() => setToast(null), 5000);
        return () => window.clearTimeout(timeout);
    }, [toast]);

    const markRead = async (id) => { await request(`/office/api/notifications/${id}/read`, { method: 'PATCH', body: '{}' }); await refresh(); };
    const markAllRead = async () => { await request('/office/api/notifications/read-all', { method: 'PATCH', body: '{}' }); await refresh(); };
    const enqueue = async (agentId, action, payload = {}) => {
        const command = await request(`/office/api/agents/${agentId}/command`, { method: 'POST', body: JSON.stringify({ action, payload }) });
        await refresh();
        return command;
    };
    const loadPlanner = async (start, end) => {
        const query = new URLSearchParams({ start, end, timezone: 'Asia/Jakarta' });
        const planner = await request(`/office/api/content-planner?${query}`);
        setData(current => ({ ...current, planner }));
        return planner;
    };
    const loadPlannerGeneration = useCallback(async (scheduleIds) => {
        const ids = [...new Set(scheduleIds)].filter(Boolean);
        if (!ids.length) return;
        const batches = [];
        for (let index = 0; index < ids.length; index += 100) batches.push(ids.slice(index, index + 100));
        const [contents, commands] = await Promise.all([
            Promise.all(batches.map(batch => {
                const query = new URLSearchParams();
                batch.forEach(id => query.append('schedule_ids[]', id));
                return request(`/office/api/content?${query}`);
            })),
            request('/office/api/commands'),
        ]);
        setData(current => ({
            ...current,
            content: [...current.content.filter(item => !ids.includes(item.schedule_id)), ...contents.flatMap(result => result.data)],
            commands: commands.data,
        }));
    }, []);
    const createSchedule = async (payload) => {
        const schedule = await request('/office/api/content-schedules', { method: 'POST', body: JSON.stringify(payload) });
        await refresh();
        return schedule;
    };
    const analyzePlan = async (prompt) => request('/office/api/content-planner/analyze', { method: 'POST', body: JSON.stringify({ prompt }) });
    const updateSchedule = async (id, payload) => {
        const schedule = await request(`/office/api/content-schedules/${id}`, { method: 'PATCH', body: JSON.stringify(payload) });
        await refresh();
        return schedule;
    };
    const deactivateSchedule = async (id) => {
        await request(`/office/api/content-schedules/${id}`, { method: 'DELETE' });
        await refresh();
    };
    const generateReport = async (payload) => {
        const report = await request('/office/api/analyst-reports/generate', { method: 'POST', body: JSON.stringify(payload) });
        await refresh(); return report;
    };
    const approveReport = async (id, actionIds) => {
        const report = await request(`/office/api/analyst-reports/${id}/approve`, { method: 'POST', body: JSON.stringify({ plan_ids: actionIds }) });
        await refresh(); return report;
    };
    
    const generateContent = async (id) => { const item = await request(`/office/api/content/${id}/generate`, { method: 'POST', body: '{}' }); await refresh(); return item; };
    const reviseContent = async (id, instruction) => request(`/office/api/content/${id}/revise`, { method: 'POST', body: JSON.stringify({ instruction }) });
    const updateContent = async (id, payload) => { const item = await request(`/office/api/content/${id}`, { method: 'PATCH', body: JSON.stringify(payload) }); await refresh(); return item; };
    const approveContent = async (id) => { const item = await request(`/office/api/content/${id}/approve`, { method: 'POST', body: '{}' }); await refresh(); return item; };

    const dismissReport = async (id, actionIds) => {
        const report = await request(`/office/api/analyst-reports/${id}/dismiss`, { method: 'POST', body: JSON.stringify({ plan_ids: actionIds }) });
        await refresh(); return report;
    };
    return { ...data, error, loading, toast, dismissToast: () => setToast(null), refresh, markRead, markAllRead, enqueue, loadPlanner, loadPlannerGeneration, createSchedule, analyzePlan, updateSchedule, deactivateSchedule, generateReport, approveReport, dismissReport, generateContent, reviseContent, updateContent, approveContent };
}
