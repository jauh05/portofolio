import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { dirname, join } from 'node:path';
import React from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { build } from 'esbuild';
import { calendarRange, calendarTime, calendarTitle, datesInRange, groupCalendarOccurrences, readCalendarView, shiftCalendarDate, todayKey, writeCalendarView } from '../../resources/js/living-office/calendarViewModel.js';

const repo = join(dirname(fileURLToPath(import.meta.url)), '../..');
const output = join(repo, 'node_modules/.cache/office-planner-tests/calendar.mjs');
await mkdir(dirname(output), { recursive: true });
const bundled = await build({ entryPoints: [join(repo, 'resources/js/living-office/PlannerCalendar.jsx')], bundle: true, write: false, platform: 'node', format: 'esm', packages: 'external', jsx: 'automatic' });
await writeFile(output, bundled.outputFiles[0].contents);
const { PlannerCalendar } = await import(pathToFileURL(output).href);

const occurrence = (id, scheduledAt, name = 'Belajar Fokus') => ({ id, scheduleId: id, scheduledAt, name, topic: name, platform: 'instagram', contentType: 'feed', status: 'scheduled' });
const render = (view, selectedDate, occurrences = []) => renderToStaticMarkup(React.createElement(PlannerCalendar, { view, selectedDate, occurrences, statusFor: () => 'draft', onViewChange: () => {}, onPrevious: () => {}, onNext: () => {}, onToday: () => {}, onDateSelect: () => {}, onOpenOccurrence: () => {} }));

test('calendar defaults to Minggu and preserves selected mode locally', () => {
    const values = new Map();
    const storage = { getItem: key => values.get(key), setItem: (key, value) => values.set(key, value) };
    assert.equal(readCalendarView(storage), 'week');
    assert.equal(writeCalendarView('day', storage), 'day');
    assert.equal(readCalendarView(storage), 'day');
    assert.equal(writeCalendarView('month', storage), 'month');
    assert.equal(readCalendarView(storage), 'month');
    assert.equal(writeCalendarView('invalid', storage), 'week');
});

test('day week and month ranges follow Monday-first Indonesian calendar', () => {
    assert.deepEqual(calendarRange('day', '2026-10-05'), { start: '2026-10-05', end: '2026-10-05' });
    assert.deepEqual(calendarRange('week', '2026-10-05'), { start: '2026-10-05', end: '2026-10-11' });
    assert.deepEqual(calendarRange('week', '2026-01-01'), { start: '2025-12-29', end: '2026-01-04' });
    assert.deepEqual(calendarRange('month', '2026-02-05'), { start: '2026-02-01', end: '2026-02-28' });
    assert.equal(datesInRange('2026-10-05', '2026-10-11').length, 7);
});

test('previous next and Today cover month and year boundaries', () => {
    assert.equal(shiftCalendarDate('day', '2026-01-01', -1), '2025-12-31');
    assert.equal(shiftCalendarDate('week', '2025-12-30', 1), '2026-01-06');
    assert.equal(shiftCalendarDate('month', '2026-01-31', 1), '2026-02-28');
    assert.equal(shiftCalendarDate('month', '2026-01-15', -1), '2025-12-15');
    assert.equal(todayKey(new Date('2026-10-04T18:00:00Z')), '2026-10-05');
});

test('same-day occurrences sort by time without modifying source records', () => {
    const items = [occurrence('late', '2026-10-05T18:00:00+07:00'), occurrence('early', '2026-10-05T09:00:00+07:00')];
    const before = JSON.stringify(items);
    assert.deepEqual(groupCalendarOccurrences(items)['2026-10-05'].map(item => item.id), ['early', 'late']);
    assert.equal(JSON.stringify(items), before);
    assert.equal(calendarTime(items[1].scheduledAt), '09.00');
});

test('rendered selector keeps Hari Minggu Bulan separate from density controls', () => {
    const html = render('week', '2026-10-05');
    assert.match(html, /Tampilan kalender/);
    assert.match(html, /aria-pressed="true">Minggu/);
    assert.match(html, />Hari</);
    assert.match(html, />Bulan</);
    assert.match(html, /Hari Ini/);
    assert.doesNotMatch(html, /Ringkas|Normal|Luas/);
});

test('day view renders empty state and multiple events in chronological order', () => {
    assert.match(render('day', '2026-10-05'), /Belum ada jadwal pada tanggal ini|Belum ada jadwal pada hari ini/);
    const html = render('day', '2026-10-05', [occurrence('b', '2026-10-05T14:00:00+07:00', 'Story Siang'), occurrence('a', '2026-10-05T09:00:00+07:00', 'Feed Pagi')]);
    assert.ok(html.indexOf('Feed Pagi') < html.indexOf('Story Siang'));
    assert.match(html, /Draf/);
});

test('week and month render actual occurrences without mutating schedule time', () => {
    const entries = [occurrence('a', '2026-10-05T09:00:00+07:00'), occurrence('b', '2026-10-05T18:00:00+07:00'), occurrence('c', '2026-10-05T19:00:00+07:00')];
    const week = render('week', '2026-10-05', entries);
    const month = render('month', '2026-10-05', entries);
    assert.match(week, /09.00/);
    assert.match(week, /18.00/);
    assert.match(month, /\+1 lainnya/);
    assert.match(calendarTitle('week', '2026-10-05'), /5–11 Oktober 2026/);
    assert.match(calendarTitle('week', '2026-01-01'), /29 Desember 2025–4 Januari 2026/);
    assert.match(calendarTitle('month', '2026-10-05'), /Oktober 2026/);
    assert.equal(entries[0].scheduledAt, '2026-10-05T09:00:00+07:00');
});
