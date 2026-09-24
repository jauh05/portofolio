export const workerStates = {
    idle: { label: 'Idle', motion: 'idle' }, walking: { label: 'Walking', motion: 'walk' },
    working: { label: 'Working', motion: 'type' }, generating: { label: 'Generating', motion: 'create' },
    analyzing: { label: 'Analyzing', motion: 'inspect' }, monitoring: { label: 'Monitoring', motion: 'inspect' },
    planning: { label: 'Planning', motion: 'think' }, break: { label: 'On break', motion: 'rest' },
    completed: { label: 'Completed', motion: 'celebrate' }, error: { label: 'Error', motion: 'stop' },
    reporting: { label: 'Reporting', motion: 'inspect' }, meeting: { label: 'In meeting', motion: 'think' },
    warning: { label: 'Warning', motion: 'stop' },
    offline: { label: 'Offline', motion: 'sleep' }, not_installed: { label: 'Belum diinstal', motion: 'disabled' },
};

export const transitionAgent = (agent, event) => {
    const transitions = {
        SEND_TO_BREAK: 'break', WAKE_UP: 'idle', TASK_STARTED: 'working', GENERATION_STARTED: 'generating',
        ANALYSIS_STARTED: 'analyzing', TASK_COMPLETED: 'completed', TASK_FAILED: 'error', OFFLINE: 'offline',
    };
    if (agent.status === 'not_installed') return agent;
    const next = transitions[event];
    return next ? { ...agent, status: next } : agent;
};
