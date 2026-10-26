import React, { useState } from 'react';
import { chipLabel, chipKey, chipTone, resultDateParts, resultStatus } from './plannerGridModel';
import { PANEL_MODES } from './panelPreferences';

const DENSITY_LABELS = { compact: 'Ringkas', normal: 'Normal', expanded: 'Luas' };
const TABLE_LIMIT = 15;
const rowKey = (row) => `${row.source}:${row.id}`;

const BUSY_STATUSES = ['queued_for_generation', 'generating', 'scheduled_for_publish', 'publishing'];

// Generate ulang hanya untuk konten database yang punya jadwal dan tidak sedang diproses.
// Reels dibuat oleh bot, jadi tidak bisa diulang dari grid.
function regenerateState(row) {
    if (row.source !== 'db') return { allowed: false, reason: 'Reels belum bisa diulang dari grid.' };
    if (!row.scheduleId || !row.scheduledAt) return { allowed: false, reason: 'Konten ini tidak punya jadwal untuk diulang.' };
    if (BUSY_STATUSES.includes(row.status)) return { allowed: false, reason: 'Konten ini sedang diproses. Tunggu sampai selesai.' };
    return { allowed: true, reason: '' };
}

export function ResultDetail({ row, onClose }) {
    return (
        <div className="planner-modal-wrap" role="dialog" aria-modal="true">
            <button type="button" className="planner-modal-scrim" onClick={onClose} aria-label="Tutup" />
            <div className="planner-modal result-detail">
                <header>
                    <div>
                        <span>{chipLabel(chipKey(row.brand, row.contentType))}</span>
                        <h2>{row.title || 'Hasil generate'}</h2>
                    </div>
                    <button type="button" onClick={onClose} aria-label="Tutup">×</button>
                </header>
                {row.videoUrl && <video controls src={row.videoUrl} className="result-media" />}
                {!row.videoUrl && row.imageUrl && <img src={row.imageUrl} alt={row.title || 'Hasil generate'} className="result-media" />}
                {row.text && <p className="result-text">{row.text}</p>}
            </div>
        </div>
    );
}

function OpsiDialog({ row, onRegenerate, onDelete, onClose }) {
    const regen = regenerateState(row);

    return (
        <div className="planner-modal-wrap" role="dialog" aria-modal="true" aria-label="Opsi hasil">
            <button type="button" className="planner-modal-scrim" onClick={onClose} aria-label="Tutup" />
            <div className="planner-modal opsi-dialog">
                <header>
                    <div>
                        <span>Opsi</span>
                        <h2>{row.title || 'Hasil generate'}</h2>
                    </div>
                    <button type="button" onClick={onClose} aria-label="Tutup">×</button>
                </header>
                <button type="button" className="opsi-action" disabled={!regen.allowed} onClick={() => { onClose(); onRegenerate(row); }}>
                    <b>Generate ulang</b>
                    <small>{regen.allowed ? 'Membuat ulang konten untuk jadwal ini.' : regen.reason}</small>
                </button>
                <button type="button" className="opsi-action is-danger" onClick={() => { onClose(); onDelete([row]); }}>
                    <b>Sembunyikan dari dashboard</b>
                    <small>Hasil ini tidak tampil lagi. Datanya tetap tersimpan, dan konten yang sudah terposting tetap ada di Instagram.</small>
                </button>
            </div>
        </div>
    );
}

function Preview({ row }) {
    if (row.videoUrl) return <video className="result-thumb" src={row.videoUrl} muted preload="metadata" />;
    if (row.imageUrl) return <img className="result-thumb" src={row.imageUrl} alt="" />;
    return <span className="result-thumb is-empty">-</span>;
}

export function ContentResultsTable({ results, density, onDensity, onRegenerate, onDelete, onClear, onOpenHistory }) {
    const [detail, setDetail] = useState(null);
    const [opsiRow, setOpsiRow] = useState(null);
    const [selected, setSelected] = useState(() => new Set());
    const [busy, setBusy] = useState(false);
    const shown = results.slice(0, TABLE_LIMIT);
    const showDay = density !== 'compact';
    const showOpsi = density !== 'compact';
    const expanded = density === 'expanded';
    const selectable = shown.filter((row) => row.id);
    const allSelected = selectable.length > 0 && selectable.every((row) => selected.has(rowKey(row)));

    const toggle = (row) => setSelected((current) => {
        const next = new Set(current);
        const key = rowKey(row);
        if (next.has(key)) next.delete(key);
        else next.add(key);
        return next;
    });
    const toggleAll = () => setSelected(allSelected ? new Set() : new Set(selectable.map(rowKey)));

    const deleteRows = async (rows) => {
        setBusy(true);
        try {
            await onDelete(rows);
        } finally {
            setBusy(false);
        }
    };

    const deleteSelected = async () => {
        const rows = selectable.filter((row) => selected.has(rowKey(row)));
        if (rows.length === 0 || !window.confirm(`Sembunyikan ${rows.length} hasil dari dashboard? Datanya tetap tersimpan.`)) return;
        await deleteRows(rows);
        setSelected(new Set());
    };

    const clearAll = async () => {
        if (!results.length || !window.confirm(`Sembunyikan semua ${results.length} hasil dari dashboard? Datanya tetap tersimpan.`)) return;
        setBusy(true);
        try {
            await onClear();
            setSelected(new Set());
        } finally {
            setBusy(false);
        }
    };

    return (
        <section className={`results-panel density-${density}`}>
            <header className="results-head">
                <h3>Hasil Generate</h3>
                <div className="results-head-actions">
                    <div className="density-toggle" role="group" aria-label="Tampilan tabel">
                        {PANEL_MODES.map((mode) => (
                            <button type="button" key={mode} className={density === mode ? 'active' : ''} onClick={() => onDensity(mode)}>
                                {DENSITY_LABELS[mode]}
                            </button>
                        ))}
                    </div>
                    <button type="button" className="results-clear" disabled={busy || results.length === 0} onClick={clearAll}>Hapus semua</button>
                </div>
            </header>
            {selected.size > 0 && (
                <div className="results-bulk" role="status">
                    <span>{selected.size} dipilih</span>
                    <button type="button" className="is-danger" disabled={busy} onClick={deleteSelected}>Sembunyikan terpilih</button>
                    <button type="button" onClick={() => setSelected(new Set())}>Batal pilih</button>
                </div>
            )}
            {results.length === 0 ? (
                <p className="results-empty">Belum ada hasil. Hasil akan muncul setelah jadwal pertama dibuat.</p>
            ) : (
                <div className="results-scroll">
                    <table className="results-table">
                        <thead>
                            <tr>
                                <th className="results-check"><input type="checkbox" aria-label="Pilih semua hasil" checked={allSelected} disabled={selectable.length === 0} onChange={toggleAll} /></th>
                                <th>Jam</th>
                                {showDay && <><th>Hari</th><th>Tanggal</th></>}
                                <th>Jenis</th>
                                <th>Status</th>
                                {expanded && <><th>Pesan error</th><th>Preview</th></>}
                                {showOpsi && <th>Opsi</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {shown.map((row) => {
                                const when = resultDateParts(row.scheduledAt);
                                const status = resultStatus(row);
                                const key = chipKey(row.brand, row.contentType);
                                return (
                                    <tr key={`${row.source}-${row.id}`}>
                                        <td className="results-check">
                                            <input
                                                type="checkbox"
                                                aria-label={`Pilih ${row.title || 'hasil'}`}
                                                disabled={!row.id}
                                                checked={row.id ? selected.has(rowKey(row)) : false}
                                                onChange={() => toggle(row)}
                                            />
                                        </td>
                                        <td>{when?.time ?? '-'}</td>
                                        {showDay && <><td>{when?.weekday ?? '-'}</td><td>{when?.date ?? '-'}</td></>}
                                        <td><span className={`type-pill tone-${chipTone(key)}`}>{chipLabel(key)}</span></td>
                                        <td>
                                            <span className={`status-pill ${status.tone}`}>{status.label}</span>
                                            <button type="button" className="results-view" onClick={() => setDetail(row)}>Lihat hasil</button>
                                        </td>
                                        {expanded && <><td className="results-error">{row.error || '-'}</td><td><Preview row={row} /></td></>}
                                        {showOpsi && (
                                            <td>
                                                <button type="button" className="results-opsi" onClick={() => setOpsiRow(row)}>Opsi</button>
                                            </td>
                                        )}
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}
            {results.length > TABLE_LIMIT && (
                <button type="button" className="results-more" onClick={onOpenHistory}>Selengkapnya · {results.length} riwayat</button>
            )}
            {detail && <ResultDetail row={detail} onClose={() => setDetail(null)} />}
            {opsiRow && <OpsiDialog row={opsiRow} onRegenerate={onRegenerate} onDelete={deleteRows} onClose={() => setOpsiRow(null)} />}
        </section>
    );
}
