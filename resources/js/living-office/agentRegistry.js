export const systems = [
    { id: 'trent', name: 'Trent', label: 'Infrastructure', status: 'online', color: '#22d3ee' },
    { id: 'jauki-content-bot', name: 'Jauki Content', label: 'Content automation', status: 'online', color: '#3b82f6' },
    { id: 'future-data-agent', name: 'Coming Soon', label: 'Future capabilities', status: 'not_installed', color: '#64748b' },
    { id: 'future-finance-agent', name: 'Finance Intelligence', label: 'Mock adapter · integration pending', status: 'integration_pending', color: '#fbbf24' },
];

export const agentRegistry = [
    {
        id: 'trent', name: 'Trent', shortName: 'TR', parentSystem: 'trent', role: 'Server Guardian',
        fullRole: 'Infrastructure Monitor', status: 'monitoring', zone: 'server', zoneLabel: 'Server / Monitoring Room',
        color: '#22d3ee', homeStation: 'deskTrent', homeDeskId: 'deskTrent',
        currentTask: 'Monitoring server health', progress: 72, lastActivity: 'Checked all services · 2m ago',
        capabilities: ['Uptime', 'RAM & disk', 'Nginx', 'Docker', 'Service status', 'Error monitoring'],
        availableActions: ['pause', 'break'], recentResults: ['12 services healthy', 'Uptime 18d 7h'],
    },
    {
        id: 'jauki-social', name: 'Social Content Worker', shortName: 'SC', parentSystem: 'jauki-content-bot', role: 'IG Feed & Story',
        fullRole: 'Instagram Feed & Story Creator', status: 'generating', zone: 'content', zoneLabel: 'Social Media / Content Studio',
        color: '#a78bfa', homeStation: 'deskSocial', homeDeskId: 'deskSocial',
        currentTask: 'Generating Instagram Feed', progress: 68, lastActivity: 'Cover variation generated · 1m ago',
        capabilities: ['IG Feed', 'IG Story', 'Caption', 'Image generation', 'Content review'],
        availableActions: ['pause', 'stop', 'break'], recentResults: ['Feed cover v3', 'Caption draft'],
    },
    {
        id: 'jauki-threads', name: 'Threads Worker', shortName: 'TH', parentSystem: 'jauki-content-bot', role: 'Community Specialist',
        fullRole: 'Threads Content & Engagement Specialist', status: 'monitoring', zone: 'community', zoneLabel: 'Community / Threads Desk',
        color: '#f472b6', homeStation: 'deskThreads', homeDeskId: 'deskThreads',
        currentTask: 'Monitoring Threads', progress: 41, lastActivity: 'Conversation scan · 4m ago',
        capabilities: ['Threads post', 'Reply monitoring', 'Reply suggestions', 'Engagement monitoring'],
        availableActions: ['pause', 'break'], recentResults: ['3 conversations detected'],
    },
    {
        id: 'jauki-article', name: 'Web Article Worker', shortName: 'WA', parentSystem: 'jauki-content-bot', role: 'Writer & Publisher',
        fullRole: 'Article Writer & Publisher', status: 'working', zone: 'article', zoneLabel: 'Article / Writing Desk',
        color: '#38bdf8', homeStation: 'deskArticle', homeDeskId: 'deskArticle',
        currentTask: 'Writing Kauiz Article', progress: 56, lastActivity: 'SEO description updated · now',
        capabilities: ['Article generation', 'SEO metadata', 'Cover image', 'Draft & publish', 'Scheduling'],
        availableActions: ['pause', 'stop', 'break'], recentResults: ['Outline approved', 'SEO title ready'],
        detail: { target: 'Kauiz', contentStatus: 'Draft', seoTitle: 'Ready', resultUrl: null },
    },
    {
        id: 'data-analyst', name: 'Data Analyst', shortName: 'DA', parentSystem: 'future-data-agent', role: 'Analysis Worker',
        fullRole: 'Data Analysis Worker', status: 'not_installed', zone: 'analytics', zoneLabel: 'Data & Analytics Room',
        color: '#64748b', homeStation: 'deskData', homeDeskId: 'deskData',
        currentTask: 'Not Installed', progress: 0, lastActivity: 'No adapter connected',
        capabilities: ['Dataset analysis', 'Statistics', 'Charts', 'Reports', 'Trend analysis'],
        availableActions: [], recentResults: [],
    },
    {
        id: 'jauki-planner', name: 'Weekly Content Planner', shortName: 'WP', parentSystem: 'jauki-content-bot', role: 'Strategy Planner',
        fullRole: 'Content Strategy & Weekly Theme Planner', status: 'planning', zone: 'strategy', zoneLabel: 'Strategy / Planning Room',
        color: '#34d399', homeStation: 'deskPlanner', homeDeskId: 'deskPlanner',
        currentTask: 'Preparing Weekly Content Theme', progress: 82, lastActivity: '4 topics suggested · 3m ago',
        capabilities: ['Weekly theme', 'Content ideas', 'Content calendar', 'Campaign suggestions'],
        availableActions: ['pause', 'stop', 'break'], recentResults: ['Theme: Build in Public', '4 topics proposed'],
        detail: { theme: 'Build in Public', upcoming: 4 },
    },
    {
        id: 'finance-analyst', name: 'Finance Analyst', shortName: 'FA', parentSystem: 'future-finance-agent', role: 'AI Finance & Cost Controller',
        fullRole: 'AI Finance & Cost Analyst', status: 'analyzing', zone: 'finance-room', zoneLabel: 'Finance & Cost Analysis Room',
        color: '#fbbf24', homeStation: 'deskFinance', homeDeskId: 'deskFinance',
        currentTask: 'Analyzing AI operating costs', progress: 64, lastActivity: 'Mock monthly cost model updated · 5m ago',
        capabilities: ['AI/API spending', 'Server & VPS cost', 'Cost per task', 'Monthly forecast', 'Cost efficiency', 'ROI preparation'],
        availableActions: [], recentResults: ['Mock: cost efficiency +12%', 'Mock: monthly projection ready'],
        detail: { integration: 'pending', dataSource: 'isolated mock' },
    },
];

export const tasks = agentRegistry.map((agent, index) => ({
    id: `task-${index + 1}`, agentId: agent.id, parentSystem: agent.parentSystem, title: agent.currentTask,
    type: agent.status, status: agent.status === 'not_installed' ? 'unavailable' : agent.progress === 100 ? 'completed' : 'running',
    progress: agent.progress, startedAt: 'Today', finishedAt: null, result: agent.recentResults[0] || null, error: null,
}));
