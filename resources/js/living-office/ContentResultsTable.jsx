import React, { useState } from 'react';
import { chipLabel, chipKey, chipTone, resultDateParts, resultStatus } from './plannerGridModel';
import { PANEL_MODES } from './panelPreferences';

const DENSITY_LABELS = { compact: 'Ringkas', normal: 'Normal', expanded: 'Luas' };

// Opsi hanya untuk konten database yang punya jadwal. Reels dibuat oleh bot, jadi tidak bisa diulang dari grid.
function opsiState(row) {
    if (row.source !== 'db') return { disabled: true, reason: 'Reels belum bisa diulang dari grid.' };
    if (!row.scheduleId || !row.scheduledAt) return { disabled: true, reason: 'Konten ini tidak punya jadwal untuk diulang.' };
    return { disabled: false, reason: 'Generate ulang konten ini' };
}

function ResultDetail({ row, onClose }) {
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

function Preview({ row }) {
    if (row.videoUrl) return <video className="result-thumb" src={row.videoUrl} muted preload="metadata" />;
    if (row.imageUrl) return <img className="result-thumb" src={row.imageUrl} alt="" />;
    return <span className="result-thumb is-empty">-</span>;
}

export function ContentResultsTable({ results, density, onDensity, onRegenerate }) {
    const [detail, setDetail] = useState(null);
    const showDay = density !== 'compact';
    const expanded = density === 'expanded';

    return (
        <section className={`results-panel density-${density}`}>
            <header className="results-head">
                <h3>Hasil Generate</h3>
                <div className="density-toggle" role="group" aria-label="Tampilan tabel">
                    {PANEL_MODES.map((mode) => (
                        <button type="button" key={mode} className={density === mode ? 'active' : ''} onClick={() => onDensity(mode)}>
                            {DENSITY_LABELS[mode]}
                        </button>
                    ))}
                </div>
            </header>
            {results.length === 0 ? (
                <p className="results-empty">Belum ada hasil. Hasil akan muncul setelah jadwal pertama dibuat.</p>
            ) : (
                <div className="results-scroll">
                    <table className="results-table">
                        <thead>
                            <tr>
                                <th>Jam</th>
                                {showDay && <><th>Hari</th><th>Tanggal</th></>}
                                <th>Jenis</th>
                                <th>Status</th>
                                {expanded && <><th>Pesan error</th><th>Preview</th></>}
                                {showDay && <th>Opsi</th>}
                            </tr>
                        </thead>
                        <tbody>
                            {results.map((row) => {
                                const when = resultDateParts(row.scheduledAt);
                                const status = resultStatus(row);
                                const key = chipKey(row.brand, row.contentType);
                                const opsi = opsiState(row);
                                return (
                                    <tr key={`${row.source}-${row.id}`}>
                                        <td>{when?.time ?? '-'}</td>
                                        {showDay && <><td>{when?.weekday ?? '-'}</td><td>{when?.date ?? '-'}</td></>}
                                        <td><span className={`type-pill tone-${chipTone(key)}`}>{chipLabel(key)}</span></td>
                                        <td>
                                            <span className={`status-pill ${status.tone}`}>{status.label}</span>
                                            <button type="button" className="results-view" onClick={() => setDetail(row)}>Lihat hasil</button>
                                        </td>
                                        {expanded && <><td className="results-error">{row.error || '-'}</td><td><Preview row={row} /></td></>}
                                        {showDay && (
                                            <td className="results-actions">
                                                <button type="button" disabled={opsi.disabled} title={opsi.reason} onClick={() => onRegenerate(row)}>Opsi</button>
                                                {opsi.disabled && <small className="results-reason">{opsi.reason}</small>}
                                            </td>
                                        )}
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}
            {detail && <ResultDetail row={detail} onClose={() => setDetail(null)} />}
        </section>
    );
}
