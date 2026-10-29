import test from 'node:test';
import assert from 'node:assert/strict';
import { buildChips, chipKey, chipLabel, CHIP_TYPES } from '../../resources/js/living-office/plannerGridModel.js';

test('Threads schedules land on the Threads chip, not on Feed', () => {
    assert.equal(chipKey('jauki', 'thread'), 'jauki-threads');
    assert.equal(chipKey('jauki', 'threads'), 'jauki-threads');
    assert.equal(chipLabel(chipKey('jauki', 'thread')), 'Threads Jauki');
});

test('Article schedules of both brands land on their own article chip', () => {
    assert.equal(chipKey('kauiz', 'article'), 'kauiz-article');
    assert.equal(chipKey('jauki', 'article'), 'jauki-article');
    assert.equal(chipKey('kauiz', 'blog'), 'kauiz-article');
    assert.equal(chipLabel('kauiz-article'), 'Artikel Kauiz');
});

test('Feed and story keep their own chips per brand', () => {
    assert.equal(chipKey('kauiz', 'feed'), 'kauiz-feed');
    assert.equal(chipKey('kauiz', 'story'), 'kauiz-story');
    assert.equal(chipKey('jauki', 'post'), 'jauki-feed');
});

test('a type without a chip is shown as Lainnya instead of silently becoming Feed', () => {
    assert.equal(chipKey('kauiz', 'thread'), 'other');
    assert.equal(chipKey('jauki', 'video'), 'other');
    assert.equal(chipLabel('other'), 'Lainnya');
    assert.equal(CHIP_TYPES.other, undefined, 'menu tambah tidak boleh menampilkan Lainnya');
});

test('weekly schedules become one chip per day with their time', () => {
    const schedules = [{ id: 's1', name: 'Tips Threads', contentType: 'thread', brand: { slug: 'jauki' }, isActive: true,
        recurrenceRule: { frequency: 'weekly', days: [1, 3], time: '09:00' } }];
    const chips = buildChips(schedules, []);
    assert.deepEqual(chips.map(c => [c.day, c.time, c.key]), [[1, '09:00', 'jauki-threads'], [3, '09:00', 'jauki-threads']]);
});
