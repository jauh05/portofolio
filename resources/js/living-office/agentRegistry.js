export const systems = [
    { id: 'trent', name: 'Trent', label: 'Infrastructure · integration pending', status: 'not_connected', color: '#22d3ee' },
    { id: 'jauki-content-bot', name: 'Jauki Content', label: 'Content automation', status: 'offline', color: '#3b82f6' },
    { id: 'future-data-agent', name: 'Data Intelligence', label: 'Integration pending', status: 'not_connected', color: '#64748b' },
    { id: 'future-finance-agent', name: 'Finance Intelligence', label: 'Integration pending', status: 'not_connected', color: '#fbbf24' },
];

const worker = (data) => ({
    progress: 0, lastActivity: data.status === 'not_connected' ? 'Not connected' : 'Idle',
    currentTask: data.status === 'not_connected' ? 'Integration pending' : 'Waiting for tasks', recentResults: [],
    ...data,
});

export const agentRegistry = [
    worker({ id: 'trent', name: 'Trent', shortName: 'TR', parentSystem: 'trent', role: 'Server Guardian', fullRole: 'Infrastructure Monitor', status: 'not_connected', zone: 'server', zoneLabel: 'Server / Monitoring Room', color: '#22d3ee', homeStation: 'deskTrent', homeDeskId: 'deskTrent', capabilities: ['Uptime', 'RAM & disk', 'Nginx', 'Docker', 'Service status', 'Error monitoring'], availableActions: [] }),
    worker({ id: 'jauki-social', name: 'Social Content Worker', shortName: 'SC', parentSystem: 'jauki-content-bot', role: 'IG Feed & Story', fullRole: 'Instagram Feed & Story Creator', status: 'idle', zone: 'content', zoneLabel: 'Social Media / Content Studio', color: '#a78bfa', homeStation: 'deskSocial', homeDeskId: 'deskSocial', capabilities: ['IG Feed', 'IG Story', 'Caption', 'Image generation', 'Content review'], availableActions: ['generate_feed', 'generate_story', 'publish_last'] }),
    worker({ id: 'jauki-threads', name: 'Threads Worker', shortName: 'TH', parentSystem: 'jauki-content-bot', role: 'Community Specialist', fullRole: 'Threads Content & Engagement Specialist', status: 'idle', zone: 'community', zoneLabel: 'Community / Threads Desk', color: '#f472b6', homeStation: 'deskThreads', homeDeskId: 'deskThreads', capabilities: ['Threads post', 'Reply monitoring', 'Reply suggestions', 'Engagement monitoring'], availableActions: ['generate_threads', 'publish_last'] }),
    worker({ id: 'jauki-article', name: 'Web Article Worker', shortName: 'WA', parentSystem: 'jauki-content-bot', role: 'Writer & Publisher', fullRole: 'Article Writer & Publisher', status: 'idle', zone: 'article', zoneLabel: 'Article / Writing Desk', color: '#38bdf8', homeStation: 'deskArticle', homeDeskId: 'deskArticle', capabilities: ['Article generation', 'SEO metadata', 'Cover image', 'Draft & publish', 'Scheduling'], availableActions: ['generate_article', 'publish_article', 'schedule_article'] }),
    worker({ id: 'data-analyst', name: 'Data Analyst', shortName: 'DA', parentSystem: 'future-data-agent', role: 'Analysis Worker', fullRole: 'Data Analysis Worker', status: 'not_connected', zone: 'analytics', zoneLabel: 'Data & Analytics Room', color: '#64748b', homeStation: 'deskData', homeDeskId: 'deskData', capabilities: ['Dataset analysis', 'Statistics', 'Charts', 'Reports', 'Trend analysis'], availableActions: [] }),
    worker({ id: 'jauki-planner', name: 'Weekly Content Planner', shortName: 'WP', parentSystem: 'jauki-content-bot', role: 'Strategy Planner', fullRole: 'Content Strategy & Weekly Theme Planner', status: 'idle', zone: 'strategy', zoneLabel: 'Strategy / Planning Room', color: '#34d399', homeStation: 'deskPlanner', homeDeskId: 'deskPlanner', capabilities: ['Weekly theme', 'Content ideas', 'Content calendar', 'Campaign suggestions'], availableActions: ['run_weekly_analysis'] }),
    worker({ id: 'finance-analyst', name: 'Finance Analyst', shortName: 'FA', parentSystem: 'future-finance-agent', role: 'AI Finance & Cost Controller', fullRole: 'AI Finance & Cost Analyst', status: 'not_connected', zone: 'finance-room', zoneLabel: 'Finance & Cost Analysis Room', color: '#fbbf24', homeStation: 'deskFinance', homeDeskId: 'deskFinance', capabilities: ['AI/API spending', 'Server & VPS cost', 'Cost per task', 'Monthly forecast', 'Cost efficiency', 'ROI preparation'], availableActions: [] }),
];

export const tasks = [];
