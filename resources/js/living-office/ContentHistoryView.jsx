import React, { useMemo, useState } from 'react';
import { ArrowLeft, Search } from 'lucide-react';
import { chipLabel, chipKey, chipTone, resultDateParts, resultStatus } from './plannerGridModel';
import { ResultDetail } from './ContentResultsTable';
import './content-history.css';

const PAGE_SIZE = 24;
const rowKey = (row) => `${row.source}:${row.id}`;

function Thumb({ row }) {
    if (row.videoUrl) return <video className="hist-thumb" src={row.videoUrl} muted preload="metadata" />;
    if (row.imageUrl) return <img className="hist-thumb" src={row.imageUrl} alt="" />;
    return <span className="hist-thumb is-empty">{chipLabel(chipKey(row.brand, row.contentType))}</span>;
}

// Halaman riwayat semua hasil generate: kartu berjejer, cari, paging, dan sembunyikan.
export function ContentHistoryView({ results, onBack, onDelete, onClear }) {
    const [query, setQuery] = useState('');
    const [page, setPage] = useState(1);
    const [selected, setSelected] = useState(() => new Set());
    const [detail, setDetail] = useState(null);
    const [busy, setBusy] = useState(false);

    const matching = useMemo(() => {
        const text = query.trim().toLowerCase();
        if (!text) return results;
        return results.filter((row) => [row.title, row.text, chipLabel(chipKey(row.brand, row.contentType)), resultStatus(row).label, resultDateParts(row.scheduledAt)?.date]
            .filter(Boolean).join(' ').toLowerCase().includes(text));
    }, [results, query]);
    const pageCount = Math.max(1, Math.ceil(matching.length / PAGE_SIZE));
    const current = Math.min(page, pageCount);
    const pageRows = matching.slice((current - 1) * PAGE_SIZE, current * PAGE_SIZE);
    const selectedRows = results.filter((row) => row.id && selected.has(rowKey(row)));

    const toggle = (row) => setSelected((state) => {
        const next = new Set(state);
        const key = rowKey(row);
        if (next.has(key)) next.delete(key);
        else next.add(key);
        return next;
    });

    const hide = async (rows) => {
        setBusy(true);
        try {
            await onDelete(rows);
            setSelected(new Set());
        } finally {
            setBusy(false);
        }
    };

    const hideSelected = () => {
        if (selectedRows.length && window.confirm(`Sembunyikan ${selectedRows.length} hasil dari dashboard? Datanya tetap tersimpan.`)) hide(selectedRows);
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
        <div className="planner-grid-page content-history">
            <header className="planner-grid-toolbar">
                <div className="planner-grid-title">
                    <h2>Riwayat generate</h2>
                    <p>{results.length} hasil. Cari, pilih, atau sembunyikan dari dashboard.</p>
                </div>
                <div className="planner-grid-actions">
                    <button type="button" className="planner-toggle" onClick={onBack}><ArrowLeft size={14} /> Kembali ke grid</button>
                </div>
            </header>

            <div className="hist-toolbar">
                <label className="hist-search">
                    <Search size={14} />
                    <input type="search" placeholder="Cari judul, teks, jenis, status, atau tanggal" value={query} onChange={(event) => { setQuery(event.target.value); setPage(1); }} />
                </label>
                <div className="hist-toolbar-actions">
                    {selectedRows.length > 0 && <button type="button" className="is-danger" disabled={busy} onClick={hideSelected}>Sembunyikan terpilih ({selectedRows.length})</button>}
                    <button type="button" className="is-danger" disabled={busy || results.length === 0} onClick={clearAll}>Hapus semua</button>
                </div>
            </div>

            {pageRows.length === 0 ? <p className="hist-empty">Tidak ada hasil yang cocok.</p> : (
                <div className="hist-grid">
                    {pageRows.map((row) => {
                        const key = chipKey(row.brand, row.contentType);
                        const status = resultStatus(row);
                        const when = resultDateParts(row.scheduledAt);
                        const isSelected = selected.has(rowKey(row));
                        return (
                            <article key={rowKey(row)} className={`hist-card ${isSelected ? 'is-selected' : ''}`}>
                                <div className="hist-card-top">
                                    <input type="checkbox" aria-label={`Pilih ${row.title || 'hasil'}`} disabled={!row.id} checked={isSelected} onChange={() => toggle(row)} />
                                    <span className={`type-pill tone-${chipTone(key)}`}>{chipLabel(key)}</span>
                                    <span className={`status-pill ${status.tone}`}>{status.label}</span>
                                </div>
                                <button type="button" className="hist-thumb-button" onClick={() => setDetail(row)} aria-label={`Lihat ${row.title || 'hasil'}`}><Thumb row={row} /></button>
                                <strong>{row.title || 'Hasil generate'}</strong>
                                <small>{when ? `${when.weekday}, ${when.date} · ${when.time}` : 'Tanpa jadwal'}</small>
                                <div className="hist-card-actions">
                                    <button type="button" onClick={() => setDetail(row)}>Lihat hasil</button>
                                    <button type="button" className="is-danger" disabled={busy} onClick={() => hide([row])}>Sembunyikan</button>
                                </div>
                            </article>
                        );
                    })}
                </div>
            )}

            {pageCount > 1 && (
                <nav className="hist-pager" aria-label="Halaman riwayat">
                    <button type="button" disabled={current <= 1} onClick={() => setPage(current - 1)}>Sebelumnya</button>
                    <span>Halaman {current} dari {pageCount}</span>
                    <button type="button" disabled={current >= pageCount} onClick={() => setPage(current + 1)}>Berikutnya</button>
                </nav>
            )}

            {detail && <ResultDetail row={detail} onClose={() => setDetail(null)} />}
        </div>
    );
}
