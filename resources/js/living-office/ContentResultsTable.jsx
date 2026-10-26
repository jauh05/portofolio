import React, { useState } from 'react';
import { chipLabel, chipKey, chipTone, resultDateParts, resultStatus } from './plannerGridModel';
import { PANEL_MODES } from './panelPreferences';

const DENSITY_LABELS = { compact: 'Ringkas', normal: 'Normal', expanded: 'Luas' };

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

export function ContentResultsTable({ results, density, onDensity, onRegenerate }) {
    const [detail, setDetail] = useState(null);

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
                            <tr><th>Jam</th><th>Hari</th><th>Tanggal</th><th>Jenis</th><th>Status</th><th>Opsi</th></tr>
                        </thead>
                        <tbody>
                            {results.map((row) => {
                                const when = resultDateParts(row.scheduledAt);
                                const status = resultStatus(row);
                                return (
                                    <tr key={`${row.source}-${row.id}`}>
                                        <td>{when?.time ?? '-'}</td>
                                        <td>{when?.weekday ?? '-'}</td>
                                        <td>{when?.date ?? '-'}</td>
                                        <td><span className={`type-pill tone-${chipTone(chipKey(row.brand, row.contentType))}`}>{chipLabel(chipKey(row.brand, row.contentType))}</span></td>
                                        <td><span className={`status-pill ${status.tone}`}>{status.label}</span></td>
                                        <td className="results-actions">
                                            <button type="button" onClick={() => setDetail(row)}>Lihat hasil</button>
                                            <button
                                                type="button"
                                                disabled={row.source !== 'db'}
                                                title={row.source === 'db' ? 'Generate ulang' : 'Reels dibuat ulang lewat bot'}
                                                onClick={() => onRegenerate(row)}
                                            >
                                                Opsi
                                            </button>
                                        </td>
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
