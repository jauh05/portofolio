import React, { useState } from 'react';
import { createRoot } from 'react-dom/client';
import { PlannerGenerationCard } from '../../resources/js/living-office/PlannerGenerationCard';
import '../../resources/js/living-office/living-office.css';
import '../../resources/js/living-office/office-redesign.css';
import '../../resources/js/living-office/office-pages.css';
import '../../resources/js/living-office/office-refinement.css';
import '../../resources/js/living-office/planner-generation.css';

// Synthetic visual QA only. No Office API is called and no data is persisted.
const base = { id: 'demo', name: 'Tips belajar teknologi kompleks', platform: 'instagram', contentType: 'feed', generationMode: 'manual', publishingMode: 'review', isActive: true, metadata: { source: 'analyst' } };
const feed = { id: 'feed-ready', schedule_id: 'feed-ready', status: 'ready_for_review', imageUrl: '/tests/visual/generated-feed.svg', text: 'Tips belajar teknologi kompleks dimulai dari satu ide kecil. Pecah materi menjadi langkah yang dapat dicoba hari ini.' };
const story = { id: 'story-ready', schedule_id: 'story-ready', status: 'ready_for_review', imageUrl: '/tests/visual/generated-story.svg', text: '' };
const threads = { id: 'threads-ready', schedule_id: 'threads-ready', status: 'ready_for_review', text: 'Belajar lebih efektif dimulai dari rasa ingin tahu.\n\n1. Pilih satu ide.\n2. Jelaskan dengan kata sendiri.\n3. Uji lagi besok.' };
const cases = [
    ['draft', { schedule: { ...base, id: 'feed-draft', name: 'Feed belum dibuat' } }],
    ['requesting', { schedule: { ...base, id: 'feed-requesting', name: 'Feed: request sedang dikirim' }, requestState: { phase: 'requesting' } }],
    ['generating', { schedule: { ...base, id: 'feed-generating', name: 'Feed sedang dibuat' }, content: { id: 'feed-generating', status: 'generating' } }],
    ['feed', { schedule: { ...base, id: 'feed-ready', name: 'Feed: hasil siap direview' }, content: feed }],
    ['story', { schedule: { ...base, id: 'story-ready', name: 'Story: hasil siap direview', contentType: 'story' }, content: story }],
    ['threads', { schedule: { ...base, id: 'threads-ready', name: 'Threads: hasil siap direview', platform: 'threads', contentType: 'thread' }, content: threads }],
    ['failed', { schedule: { ...base, id: 'feed-failed', name: 'Feed: worker gagal' }, content: { id: 'feed-failed', status: 'queued_for_generation' }, command: { status: 'failed', error: 'Worker tidak dapat memproses Feed.' } }],
    ['http-error', { schedule: { ...base, id: 'feed-http', name: 'Feed: request gagal' }, requestState: { phase: 'error', message: 'Jaringan sedang tidak tersedia.' } }],
    ['article', { schedule: { ...base, id: 'article', name: 'Artikel editorial', platform: 'article', contentType: 'article' } }],
];
const worker = [{ id: 'jauki-article', status: 'not_installed', availableActions: ['generate_article'] }];

function Fixture() {
    const [selected, setSelected] = useState(new URLSearchParams(location.search).get('state') || 'all');
    const [theme, setTheme] = useState('light');
    const [mode, setMode] = useState('expanded');
    const [interactive, setInteractive] = useState({ phase: 'idle', content: null });
    const simulate = () => {
        setInteractive({ phase: 'requesting', content: null });
        setTimeout(() => setInteractive({ phase: 'queued', content: { id: 'interactive', status: 'queued_for_generation' } }), 900);
        setTimeout(() => setInteractive({ phase: 'generating', content: { id: 'interactive', status: 'generating' } }), 2200);
        setTimeout(() => setInteractive({ phase: 'ready', content: { ...feed, id: 'interactive' } }), 4300);
    };
    const shown = cases.filter(([id]) => selected === 'all' || selected === id);
    return <div className="app-shell office-redesign" data-theme={theme} style={{ display: 'block', minHeight: '100dvh', padding: '0 0 40px' }}><header style={{ maxWidth: 1380, margin: '0 auto', padding: '22px 18px', color: 'var(--text)' }}><small>DEV FIXTURE · synthetic records · no API</small><h1 style={{ margin: '5px 0' }}>Planner generation states</h1><div style={{ display: 'flex', gap: 8, flexWrap: 'wrap' }}><select aria-label="Tampilkan state" value={selected} onChange={event => setSelected(event.target.value)}>{['all', ...cases.map(([id]) => id)].map(id => <option key={id}>{id}</option>)}</select><select aria-label="Mode Planner" value={mode} onChange={event => setMode(event.target.value)}><option value="compact">Ringkas</option><option value="normal">Normal</option><option value="expanded">Luas</option></select><button onClick={() => setTheme(value => value === 'light' ? 'dark' : 'light')}>Theme</button></div></header><main style={{ width: 'min(1380px, 100%)', margin: '0 auto', padding: '0 18px' }}><section className="planner-manage" style={{ minHeight: 0 }}><h2>Alur klik</h2><PlannerGenerationCard schedule={{ ...base, id: 'interactive', name: 'Klik untuk uji transisi' }} content={interactive.content} requestState={interactive.phase === 'requesting' ? { phase: 'requesting' } : undefined} optimisticQueued={interactive.phase === 'queued'} mode={mode} agents={worker} onGenerate={simulate} onReview={() => {}} /><h2>State terpisah</h2><div style={{ display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(min(100%,510px),1fr))', gap: 12 }}>{shown.map(([id, props]) => <PlannerGenerationCard key={id} {...props} mode={mode} agents={worker} />)}</div></section></main></div>;
}

if (import.meta.env.DEV) {
    const root = import.meta.hot?.data.root || createRoot(document.getElementById('root'));
    if (import.meta.hot) import.meta.hot.data.root = root;
    root.render(<Fixture />);
}
