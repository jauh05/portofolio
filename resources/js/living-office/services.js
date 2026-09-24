// Phase 1 adapters. Replace these isolated implementations with private backend
// endpoints later; credentials and server commands must never enter this client.
const delay = (value) => Promise.resolve(structuredClone(value));

export const TrentService = {
    getServerStatus: () => delay({
        status: 'healthy', cpu: 32, ram: 46, disk: 33, temperature: 48, uptime: '18d 7h 24m',
        nginx: 'online', docker: 'online', services: { healthy: 12, total: 12 }, warnings: [], errors: [],
    }),
};

export const JaukiContentService = {
    getSystemStatus: () => delay({ status: 'online', transport: 'adapter-ready' }),
    getWorkers: (registry = []) => delay(registry.filter((agent) => agent.parentSystem === 'jauki-content-bot')),
    getWorkerStatus: (id, registry = []) => delay(registry.find((agent) => agent.id === id) || null),
    getTasks: (taskList = []) => delay(taskList.filter((task) => task.parentSystem === 'jauki-content-bot')),
    getRecentOutputs: () => delay([]),
    runCapability: () => Promise.reject(new Error('Backend action belum terhubung.')),
    pauseTask: () => Promise.reject(new Error('Backend action belum terhubung.')),
    stopTask: () => Promise.reject(new Error('Backend action belum terhubung.')),
};

export const FinanceService = {
    getCostSummary: () => delay({
        source: 'isolated_mock', integration: 'pending', currency: 'USD',
        today: 3.42, month: 74.86, server: 12, api: 38.74, imageGeneration: 14.28,
        content: 9.84, averagePerTask: 0.31, perArticle: 0.88, perImage: 0.19,
        successfulTaskCost: 0.34, efficiencyChange: 12, forecast: 92.4,
    }),
};

export const realtimeEventContract = [
    'agent.status.changed', 'agent.task.started', 'agent.task.progress', 'agent.task.completed',
    'agent.task.failed', 'trent.server.updated', 'content.generated', 'content.published',
    'weekly.plan.generated', 'threads.activity.detected',
    'finance.cost.updated', 'finance.report.generated', 'office.event.started', 'office.event.completed',
];
