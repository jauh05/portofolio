import React, { useEffect, useState } from 'react';

const DURATION_OPTIONS = [20, 25, 30, 35, 40];
const SPEED_OPTIONS = [1.0, 1.1, 1.2];
const WEEKLY_OPTIONS = [1, 2, 3, 4, 5, 6, 7];

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
const csvToList = (text) => text.split(',').map(item => item.trim()).filter(Boolean);
const linesToList = (text) => text.split('\n').map(item => item.trim()).filter(Boolean);

const toForm = (settings) => ({
    videos_per_week: String(settings.videos_per_week),
    durations: settings.durations.map(Number),
    speed_choices: settings.speed_choices.map(Number),
    skip_head_s: String(settings.skip_head_s),
    skip_tail_s: String(settings.skip_tail_s),
    music_moods: settings.music_moods.join(', '),
    music_volume: String(settings.music_volume),
    brand_hashtags: settings.brand_hashtags.join(', '),
    services: settings.services.join('\n'),
});

const toPayload = (form) => ({
    videos_per_week: Number(form.videos_per_week),
    durations: form.durations,
    speed_choices: form.speed_choices,
    skip_head_s: Number(form.skip_head_s),
    skip_tail_s: Number(form.skip_tail_s),
    music_moods: csvToList(form.music_moods),
    music_volume: Number(form.music_volume),
    brand_hashtags: csvToList(form.brand_hashtags),
    services: linesToList(form.services),
});

const toggle = (list, value) => (list.includes(value) ? list.filter(item => item !== value) : [...list, value].sort((a, b) => a - b));

export function ReelsSettingsPanel() {
    const [form, setForm] = useState(null);
    const [message, setMessage] = useState('');
    const [errors, setErrors] = useState([]);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        fetch('/office/api/reels-settings', { headers: { Accept: 'application/json' } })
            .then(response => response.json())
            .then(json => setForm(toForm(json.settings)))
            .catch(() => setMessage('Pengaturan Reels belum bisa dimuat.'));
    }, []);

    if (!form) {
        return <section className="planner-form-section"><span>REELS</span><p>{message || 'Memuat pengaturan Reels…'}</p></section>;
    }

    const change = (key, value) => setForm(current => ({ ...current, [key]: value }));

    const save = async (event) => {
        event.preventDefault();
        setSaving(true);
        setMessage('');
        setErrors([]);
        try {
            const response = await fetch('/office/api/reels-settings', {
                method: 'PUT',
                headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf() },
                body: JSON.stringify(toPayload(form)),
            });
            const json = await response.json().catch(() => ({}));
            if (!response.ok) {
                setErrors(Object.values(json.errors || {}).flat());
                setMessage(json.message || 'Pengaturan belum tersimpan.');
                return;
            }
            setForm(toForm(json.settings));
            setMessage('Pengaturan Reels tersimpan. Berlaku untuk generate berikutnya.');
        } catch {
            setMessage('Koneksi terputus, pengaturan belum tersimpan.');
        } finally {
            setSaving(false);
        }
    };

    return (
        <form className="planner-modal" onSubmit={save}>
            <section className="planner-form-section">
                <span>REELS · JADWAL</span>
                <div className="planner-form-grid">
                    <label>Video per minggu
                        <select value={form.videos_per_week} onChange={e => change('videos_per_week', e.target.value)}>
                            {WEEKLY_OPTIONS.map(n => <option value={n} key={n}>{n}</option>)}
                        </select>
                    </label>
                    <label>Jeda buang di awal (detik)
                        <input type="number" min="0" max="15" value={form.skip_head_s} onChange={e => change('skip_head_s', e.target.value)} />
                    </label>
                    <label>Jeda buang di akhir (detik)
                        <input type="number" min="0" max="15" value={form.skip_tail_s} onChange={e => change('skip_tail_s', e.target.value)} />
                    </label>
                </div>
            </section>

            <section className="planner-form-section">
                <span>REELS · DURASI DAN KECEPATAN</span>
                <fieldset>
                    <legend>Durasi video (detik)</legend>
                    {DURATION_OPTIONS.map(value => (
                        <label key={value}>
                            <input type="checkbox" checked={form.durations.includes(value)} onChange={() => change('durations', toggle(form.durations, value))} /> {value}
                        </label>
                    ))}
                </fieldset>
                <fieldset>
                    <legend>Kecepatan yang boleh dipakai</legend>
                    {SPEED_OPTIONS.map(value => (
                        <label key={value}>
                            <input type="checkbox" checked={form.speed_choices.includes(value)} onChange={() => change('speed_choices', toggle(form.speed_choices, value))} /> {value.toFixed(1)}x
                        </label>
                    ))}
                </fieldset>
            </section>

            <section className="planner-form-section">
                <span>REELS · MUSIK DAN TEKS</span>
                <label>Mood musik (pisahkan dengan koma)
                    <input value={form.music_moods} onChange={e => change('music_moods', e.target.value)} placeholder="jj, pop_indonesia, chill" />
                </label>
                <label>Volume musik ({Number(form.music_volume).toFixed(2)})
                    <input type="range" min="0" max="1" step="0.05" value={form.music_volume} onChange={e => change('music_volume', e.target.value)} />
                </label>
                <label>Hashtag brand (pisahkan dengan koma)
                    <input value={form.brand_hashtags} onChange={e => change('brand_hashtags', e.target.value)} placeholder="#jaukiacademy" />
                </label>
                <label>Topik layanan (satu baris satu topik)
                    <textarea rows={5} value={form.services} onChange={e => change('services', e.target.value)} />
                </label>
            </section>

            {message && <p role="status">{message}</p>}
            {errors.length > 0 && <ul role="alert">{errors.map(item => <li key={item}>{item}</li>)}</ul>}
            <button type="submit" disabled={saving}>{saving ? 'Menyimpan…' : 'Simpan pengaturan Reels'}</button>
        </form>
    );
}
