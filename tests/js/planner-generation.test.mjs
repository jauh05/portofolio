import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { dirname, join } from 'node:path';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { build } from 'esbuild';
import { articleReady, contentPreview, generationStatus, resolvePlannerOutput, runGenerationRequest, safeGenerationError, selectGenerationCommand, selectScheduleContent, shouldPollGeneration } from '../../resources/js/living-office/plannerGeneration.js';

const repo = join(dirname(fileURLToPath(import.meta.url)), '../..');
const output = join(repo, 'node_modules/.cache/office-planner-tests/card.mjs');
await mkdir(dirname(output), { recursive: true });
const bundled = await build({ entryPoints: [join(repo, 'resources/js/living-office/PlannerGenerationCard.jsx')], bundle: true, write: false, platform: 'node', format: 'esm', packages: 'external', jsx: 'automatic' });
await writeFile(output, bundled.outputFiles[0].contents);
const { PlannerGenerationCard } = await import(pathToFileURL(output).href);

const schedule = (platform, contentType, extra = {}) => ({ id: 'schedule-1', name: 'Tips belajar teknologi kompleks', platform, contentType, isActive: true, generationMode: 'manual', publishingMode: 'review', metadata: { source: 'analyst' }, ...extra });
const agents = [{ id: 'jauki-article', status: 'not_installed', availableActions: ['generate_article'] }];
const render = (s, props = {}) => renderToStaticMarkup(React.createElement(PlannerGenerationCard, { schedule: s, agents, ...props }));

test('Feed label and typed button', () => {
    assert.equal(resolvePlannerOutput(schedule('instagram', 'feed')).label, 'Instagram Feed');
    assert.match(render(schedule('instagram', 'feed')), /Generate Feed Sekarang/);
});
test('Story typed button', () => assert.match(render(schedule('instagram', 'story')), /Generate Story Sekarang/));
test('Threads typed button', () => assert.match(render(schedule('threads', 'thread')), /Generate Threads Sekarang/));
test('Article worker unavailable disables generation and explains why', () => {
    const html = render(schedule('article', 'article'));
    assert.match(html, /Worker Artikel Belum Tersedia/);
    assert.match(html, /disabled/);
    assert.match(html, /belum aktif di production/);
});
test('unknown output never defaults to Feed', () => {
    const html = render(schedule('linkedin', 'video'));
    assert.match(html, /Output belum dikenali/);
    assert.match(html, /Tentukan Output/);
    assert.doesNotMatch(html, /Generate Feed/);
});
test('Analyst source stays separate from Feed output', () => {
    const html = render(schedule('instagram', 'feed'));
    assert.match(html, /<dt>Source<\/dt><dd>Analyst<\/dd>/);
    assert.match(html, /<dt>Output<\/dt><dd>Instagram Feed<\/dd>/);
    assert.doesNotMatch(html, /Output<\/dt><dd>Analysis/);
});
test('requesting state immediately disables button with visible spinner text', () => {
    const html = render(schedule('instagram', 'feed'), { requestState: { phase: 'requesting' } });
    assert.match(html, /Memulai generation/);
    assert.match(html, /disabled/);
    assert.match(html, /Mengirim permintaan ke Laravel/);
});
test('successful POST optimistic state reads as queued while initial draft is stale', () => {
    const html = render(schedule('instagram', 'feed'), { content: { id: 'content-1', status: 'draft' }, optimisticQueued: true });
    assert.match(html, /Antre/);
    assert.match(html, /Menunggu giliran worker/);
});
test('generating Feed shows spinner and image skeleton', () => {
    const html = render(schedule('instagram', 'feed'), { mode: 'expanded', content: { id: 'content-1', status: 'generating' } });
    assert.match(html, /Sedang dibuat/);
    assert.match(html, /planner-skeleton-media/);
});
test('Feed ready shows actual image URL and caption', () => {
    const content = { id: 'content-1', status: 'ready_for_review', imageUrl: 'https://example.org/feed.jpg', text: 'Caption hasil worker.' };
    const html = render(schedule('instagram', 'feed'), { mode: 'expanded', content });
    assert.match(html, /feed.jpg/);
    assert.match(html, /Caption hasil worker/);
});
test('Story ready shows vertical image without fake caption', () => {
    const html = render(schedule('instagram', 'story'), { mode: 'expanded', content: { id: 'content-1', status: 'ready_for_review', imageUrl: '/generated/story.jpg', text: '' } });
    assert.match(html, /planner-generated-image story/);
    assert.match(html, /generated\/story.jpg/);
    assert.doesNotMatch(html, /planner-result-text/);
});
test('Threads ready shows actual text without Instagram image', () => {
    const html = render(schedule('threads', 'thread'), { mode: 'expanded', content: { id: 'content-1', status: 'ready_for_review', text: 'Isi Threads dari worker.' } });
    assert.match(html, /Isi Threads dari worker/);
    assert.doesNotMatch(html, /planner-generated-image/);
});
test('failed HTTP request displays safe error and retry', () => {
    const html = render(schedule('instagram', 'feed'), { requestState: { phase: 'error', message: 'Jaringan tidak tersedia.' } });
    assert.match(html, /Generation gagal dimulai/);
    assert.match(html, /Jaringan tidak tersedia/);
    assert.match(html, /Coba Lagi/);
});
test('failed worker command displays safe backend error without unsafe retry', () => {
    const html = render(schedule('instagram', 'feed'), { content: { id: 'content-1', status: 'queued_for_generation' }, command: { status: 'failed', error: 'Worker tidak dapat memproses Feed.' } });
    assert.match(html, /Generation gagal/);
    assert.match(html, /Worker tidak dapat memproses Feed/);
    assert.match(html, /Periksa Worker/);
    assert.match(html, /disabled/);
});
test('ready for review replaces generation button with Lihat Hasil', () => {
    assert.match(render(schedule('instagram', 'feed'), { content: { id: 'content-1', status: 'ready_for_review' } }), /Lihat Hasil/);
});
test('Ringkas, Normal, and Luas preserve their presentation levels', () => {
    const content = { id: 'content-1', status: 'ready_for_review', text: 'Hasil sungguhan.' };
    const compact = render(schedule('threads', 'thread'), { mode: 'compact', content });
    const normal = render(schedule('threads', 'thread'), { mode: 'normal', content });
    const expanded = render(schedule('threads', 'thread'), { mode: 'expanded', content });
    assert.doesNotMatch(compact, /planner-card-meta/);
    assert.match(normal, /planner-card-meta/);
    assert.match(expanded, /Hasil sungguhan/);
});
test('schedule linkage picks newest content deterministically', () => {
    const old = { id: 'a', schedule_id: 'schedule-1', updatedAt: '2026-10-01T00:00:00Z' };
    const latest = { id: 'b', schedule_id: 'schedule-1', updatedAt: '2026-10-02T00:00:00Z' };
    assert.equal(selectScheduleContent([old, latest], 'schedule-1').id, 'b');
    assert.equal(selectScheduleContent([latest, old], 'schedule-1').id, 'b');
});
test('command failure is linked by content ID and updates effective status', () => {
    const content = { id: 'content-1', status: 'queued_for_generation', metadata: { generation_requested_at: '2026-10-02T00:00:00Z' } };
    const command = selectGenerationCommand([{ id: 'command-1', contentId: 'content-1', action: 'generate_feed', status: 'failed', createdAt: '2026-10-02T00:00:02Z' }], content);
    assert.equal(generationStatus(content, command), 'failed');
});
test('image URL and plain caption are taken from actual API fields', () => {
    assert.deepEqual(contentPreview({ imageUrl: 'https://example.org/a.jpg', text: 'Teks asli.' }), { imageUrl: 'https://example.org/a.jpg', text: 'Teks asli.', title: '' });
    assert.equal(contentPreview({ imageUrl: 'javascript:alert(1)' }).imageUrl, null);
});
test('unsafe backend details are hidden', () => assert.match(safeGenerationError('Traceback /Users/worker/secret.py:7 token=abc'), /belum berhasil/));
test('automatic publishing schedule cannot start a manual generation', () => {
    const html = render(schedule('instagram', 'feed', { publishingMode: 'automatic' }));
    assert.match(html, /Ubah Publishing ke Review/);
    assert.match(html, /disabled/);
});
test('Article becomes available only with real worker action and active status', () => {
    const output = resolvePlannerOutput(schedule('article', 'article'));
    assert.equal(articleReady(output, agents), false);
    assert.equal(articleReady(output, [{ id: 'jauki-article', status: 'idle', availableActions: ['generate_article'] }]), true);
});
test('click handler enters requesting before HTTP resolves, then queued and refreshes the same schedule', async () => {
    const states = [], refreshed = [];
    let resolvePost;
    const pending = runGenerationRequest({ scheduleId: 'schedule-1', post: () => new Promise(resolve => { resolvePost = resolve; }), refresh: async ids => refreshed.push(ids), onState: state => states.push(state.phase), onNotice: () => {} });
    assert.deepEqual(states, ['requesting']);
    resolvePost({ status: 'generated', content_id: 'content-1' });
    await pending;
    assert.deepEqual(states, ['requesting', 'queued']);
    assert.deepEqual(refreshed, [['schedule-1']]);
});
test('HTTP failure becomes a visible safe error and does not masquerade as queued', async () => {
    const states = [];
    await runGenerationRequest({ scheduleId: 'schedule-1', post: async () => { throw new Error('Jaringan tidak tersedia.'); }, refresh: async () => assert.fail('must not refresh after failure'), onState: state => states.push(state), onNotice: () => {} });
    assert.deepEqual(states.map(state => state.phase), ['requesting', 'error']);
    assert.equal(states[1].message, 'Jaringan tidak tersedia.');
});
test('targeted polling starts for active generation and stops at success or worker failure', () => {
    assert.equal(shouldPollGeneration(null, null, true), true);
    assert.equal(shouldPollGeneration({ status: 'queued_for_generation' }), true);
    assert.equal(shouldPollGeneration({ status: 'generating' }), true);
    assert.equal(shouldPollGeneration({ status: 'ready_for_review' }), false);
    assert.equal(shouldPollGeneration({ status: 'queued_for_generation' }, { status: 'failed' }), false);
    assert.equal(shouldPollGeneration({ status: 'published' }), false);
});
