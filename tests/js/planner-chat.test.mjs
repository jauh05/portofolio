import test from 'node:test';
import assert from 'node:assert/strict';
import { approvalNotice, formatScheduledAt, historyFrom, platformLabel, selectAll, timingLabel, toggleSelection } from '../../resources/js/living-office/plannerChat.js';

test('platform labels match the planner names and fall back for unknown outputs', () => {
    assert.equal(platformLabel({ platform: 'instagram', content_type: 'feed' }), 'Instagram Feed');
    assert.equal(platformLabel({ platform: 'instagram', content_type: 'story' }), 'Instagram Story');
    assert.equal(platformLabel({ platform: 'threads', content_type: 'thread' }), 'Threads');
    assert.equal(platformLabel({ platform: 'youtube', content_type: 'short' }), 'youtube short');
});

test('generation timing labels follow the H-1, H-2, now, and manual semantics', () => {
    assert.equal(timingLabel({ generation_timing_label: 'H-1' }), 'H-1 · otomatis');
    assert.equal(timingLabel({ generation_timing_label: 'H-2' }), 'H-2 · otomatis');
    assert.equal(timingLabel({ generation_timing_label: 'now' }), 'Sekarang');
    assert.equal(timingLabel({ generation_timing_label: 'manual' }), 'Manual');
    assert.equal(timingLabel({ generation_timing_label: 'something-else' }), 'Manual');
});

test('scheduled times from the backend are read as Asia/Jakarta wall time', () => {
    assert.match(formatScheduledAt('2026-10-21 10:00:00'), /10[.:]00/);
    assert.equal(formatScheduledAt(null), '—');
});

test('history keeps only earlier user turns, newest last, up to the limit', () => {
    const messages = [
        { role: 'user', text: 'satu' },
        { role: 'assistant', text: 'pertanyaan' },
        { role: 'user', text: 'dua' },
        { role: 'user', text: 'tiga' },
    ];
    assert.deepEqual(historyFrom(messages), ['satu', 'dua', 'tiga']);
    assert.deepEqual(historyFrom(messages, 2), ['dua', 'tiga']);
});

test('history stops at a proposal or notice so a new request does not merge with a finished one', () => {
    const finished = [
        { role: 'user', text: 'Buat 2 konten Instagram Feed besok jam 10' },
        { role: 'assistant', text: 'Usulan', proposal: { items: [] } },
    ];
    assert.deepEqual(historyFrom(finished), []);
    const approved = [...finished, { role: 'system', text: 'Jadwal dibuat' }, { role: 'user', text: 'Aku ingin konten seminggu sekali' }, { role: 'assistant', text: 'Maksudnya 1 konten per minggu?' }];
    assert.deepEqual(historyFrom(approved), ['Aku ingin konten seminggu sekali']);
});

test('selection helpers select all proposals and toggle one at a time', () => {
    const items = [{ plan_id: 'a' }, { plan_id: 'b' }];
    assert.deepEqual(selectAll(items), ['a', 'b']);
    assert.deepEqual(toggleSelection(['a', 'b'], 'a'), ['b']);
    assert.deepEqual(toggleSelection(['b'], 'a'), ['b', 'a']);
});

test('approval notice states the count and explains immediate versus scheduled generation', () => {
    const text = approvalNotice(2);
    assert.match(text, /^2 jadwal dibuat/);
    assert.match(text, /Sekarang langsung masuk antrean generate/);
});
