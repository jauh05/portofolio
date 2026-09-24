import { useCallback, useEffect, useRef, useState } from 'react';
import { deskAssignments, meetingSeats, officeEvents, officeStations, restSeats, routeBetween, stationPosition } from './officeModel';

const workerSpeeds = {
    trent: .94, 'jauki-social': 1.04, 'jauki-threads': 1.08, 'jauki-article': .98,
    'jauki-planner': .92, 'data-analyst': 0, 'finance-analyst': .95,
};

const statePose = {
    seated_work: 'sit', standing_idle: 'idle', walking: 'walk1', meeting: 'meeting', resting: 'rest',
    server_check: 'analyze', returning_to_desk: 'walk1', offline: 'sleep', not_installed: 'sleep', error: 'error',
};

export function useLivingOffice(registry) {
    const timers = useRef([]);
    const agentsRef = useRef([]);
    const eventRef = useRef(null);
    const [activeEvent, setActiveEvent] = useState(null);
    const [visualAgents, setVisualAgents] = useState(() => registry.map((agent) => {
        const assignment = deskAssignments[agent.id];
        const initialState = agent.status === 'not_installed' ? 'not_installed' : 'seated_work';
        return {
            ...agent, homeDeskId: assignment.homeDeskId, currentStation: assignment.homeDeskId,
            targetLocation: assignment.homeDeskId, position: stationPosition(assignment.homeDeskId),
            officeState: initialState, movementState: initialState, animation: statePose[initialState],
            facing: assignment.deskSeat.facing, bubble: null, energy: initialState === 'not_installed' ? 0 : 86,
            walkDuration: 3, interactionState: initialState,
        };
    }));

    useEffect(() => { agentsRef.current = visualAgents; }, [visualAgents]);
    useEffect(() => { eventRef.current = activeEvent; }, [activeEvent]);

    // Backend polling for real status
    useEffect(() => {
        const poll = async () => {
            try {
                const res = await fetch('/office/api/agents');
                if (res.status === 401 || res.status === 419) {
                    window.location.href = '/office/login';
                    return;
                }
                if (res.ok) {
                    const serverAgents = await res.json();
                    setVisualAgents((current) => current.map((agent) => {
                        const serverAgent = serverAgents.find(sa => sa.id === agent.id);
                        if (serverAgent) {
                            return {
                                ...agent,
                                status: serverAgent.status,
                                currentTask: serverAgent.currentTask,
                                progress: serverAgent.progress,
                                lastActivity: serverAgent.lastActivity,
                                recentResults: serverAgent.recentResults || agent.recentResults,
                            };
                        }
                        return agent;
                    }));
                }
            } catch (error) {
                console.error('Office Bridge polling error:', error);
            }
        };
        poll();
        const interval = window.setInterval(poll, 4000);
        return () => window.clearInterval(interval);
    }, []);

    const rememberTimer = useCallback((timer) => { timers.current.push(timer); return timer; }, []);
    const clearTimers = useCallback(() => { timers.current.forEach(window.clearTimeout); timers.current = []; }, []);

    const moveLeg = useCallback((agentId, destination, finalState = 'standing_idle', bubble = null) => {
        const currentAgent = agentsRef.current.find((agent) => agent.id === agentId);
        if (!currentAgent || currentAgent.officeState === 'not_installed') return 0;
        const position = stationPosition(destination);
        const distance = Math.hypot(position.x - currentAgent.position.x, position.y - currentAgent.position.y);
        const duration = Math.max(1.8, Math.min(4.2, distance / (5.2 * workerSpeeds[agentId])));
        setVisualAgents((current) => current.map((agent) => agent.id === agentId ? {
            ...agent, targetLocation: destination, position, officeState: 'walking', movementState: 'walking',
            animation: statePose.walking, facing: position.x < agent.position.x ? 'left' : 'right',
            walkDuration: duration, bubble: null, interactionState: 'moving_with_purpose',
        } : agent));
        rememberTimer(window.setTimeout(() => {
            const anchor = officeStations[destination];
            setVisualAgents((current) => current.map((agent) => agent.id === agentId ? {
                ...agent, currentStation: destination, targetLocation: destination, position: stationPosition(destination),
                officeState: finalState, movementState: finalState, animation: statePose[finalState],
                facing: anchor?.facing || agent.facing, bubble, interactionState: finalState,
            } : agent));
        }, duration * 1000 + 80));
        if (bubble) rememberTimer(window.setTimeout(() => setVisualAgents((current) => current.map((agent) => agent.id === agentId ? { ...agent, bubble: null } : agent)), duration * 1000 + 5200));
        return duration * 1000;
    }, [rememberTimer]);

    const travelAgent = useCallback((agentId, destination, finalState, options = {}) => {
        const agent = agentsRef.current.find((item) => item.id === agentId);
        if (!agent || agent.officeState === 'not_installed') return 0;
        const path = routeBetween(agent.currentStation, destination);
        let elapsed = 0;
        path.forEach((stationId, index) => {
            const isFinal = index === path.length - 1;
            rememberTimer(window.setTimeout(() => moveLeg(
                agentId, stationId, isFinal ? finalState : 'standing_idle', isFinal ? options.bubble : null,
            ), elapsed));
            elapsed += 4350;
        });
        return elapsed;
    }, [moveLeg, rememberTimer]);

    const returnToDesk = useCallback((agentId, delay = 0) => {
        const agent = agentsRef.current.find((item) => item.id === agentId);
        if (!agent || agent.officeState === 'not_installed') return 0;
        return rememberTimer(window.setTimeout(() => travelAgent(agentId, agent.homeDeskId, 'seated_work', { bubble: 'Back to work' }), delay));
    }, [rememberTimer, travelAgent]);

    const sendToMeeting = useCallback((agentIds) => {
        agentIds.forEach((agentId, index) => rememberTimer(window.setTimeout(() => {
            travelAgent(agentId, meetingSeats[index], 'meeting', { bubble: index === 0 ? 'Meeting started' : null });
        }, index * 900)));
    }, [rememberTimer, travelAgent]);

    const sendToRest = useCallback((agentId) => {
        const activeIndex = registry.filter((agent) => agent.status !== 'not_installed').findIndex((agent) => agent.id === agentId);
        const seatId = restSeats[Math.max(0, activeIndex) % restSeats.length];
        const travelTime = travelAgent(agentId, seatId, seatId === 'daybed1' ? 'resting' : 'resting', { bubble: 'Taking a short break ☕' });
        rememberTimer(window.setTimeout(() => returnToDesk(agentId), travelTime + 18000));
        return travelTime;
    }, [registry, rememberTimer, returnToDesk, travelAgent]);

    const sendToServer = useCallback((agentId = 'trent') => {
        if (agentId !== 'trent') return 0;
        const travelTime = travelAgent('trent', 'serverMonitor', 'server_check', { bubble: 'Checking infrastructure...' });
        rememberTimer(window.setTimeout(() => returnToDesk('trent'), travelTime + 15000));
        return travelTime;
    }, [rememberTimer, returnToDesk, travelAgent]);

    const endMeeting = useCallback(() => {
        const event = eventRef.current;
        if (!event) return;
        event.participants.forEach((agentId, index) => returnToDesk(agentId, index * 1100));
        setActiveEvent(null);
    }, [returnToDesk]);

    const startMeeting = useCallback((type = 'weekly_content_meeting') => {
        if (eventRef.current) return;
        const event = officeEvents.find((item) => item.id === type) || officeEvents[0];
        setActiveEvent({ ...event, phase: 'gathering', endsAt: Date.now() + event.duration * 1000 });
        sendToMeeting(event.participants);
        rememberTimer(window.setTimeout(() => setActiveEvent((current) => current ? { ...current, phase: 'in_progress' } : null), 15000));
        rememberTimer(window.setTimeout(endMeeting, event.duration * 1000));
    }, [endMeeting, rememberTimer, sendToMeeting]);

    useEffect(() => () => clearTimers(), [clearTimers]);

    return {
        visualAgents, activeEvent, simulateEvent: startMeeting, startMeeting, endMeeting,
        sendToMeeting, sendToRest, sendToServer, returnToDesk,
    };
}
