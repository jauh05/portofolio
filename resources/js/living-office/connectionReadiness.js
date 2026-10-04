const unavailable = new Set(['not_connected', 'not_installed']);
const unhealthy = new Set(['error', 'failed']);
const active = new Set(['idle','working','generating','planning','analyzing','monitoring','reporting','meeting','break','completed']);

export function channelReadiness(agent, actions = []) {
    const worker = !agent ? 'NOT CONFIGURED'
        : !agent.status ? 'NOT VERIFIED'
        : unavailable.has(agent.status) ? 'NOT CONFIGURED'
        : unhealthy.has(agent.status) ? 'ERROR'
        : agent.status === 'offline' || agent.status === 'warning' ? 'WARNING'
        : active.has(agent.status) ? 'READY' : 'NOT VERIFIED';
    const offered = actions.filter(action => agent?.availableActions?.includes(action));
    // The Office agent contract exposes actions, not connector health or credentials.
    return { worker, generator: 'NOT VERIFIED', account: 'NOT VERIFIED', publishing: 'NOT VERIFIED', offered };
}
