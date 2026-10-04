import React, { useState } from 'react';
import { AlertCircle, CheckCircle2, LoaderCircle } from 'lucide-react';
import { articleReady, contentPreview, generationStatus, resolvePlannerOutput, safeGenerationError, STATUS_LABELS } from './plannerGeneration';

export function GeneratedImage({ src, story = false }) {
    const [state, setState] = useState('loading');
    if (!src) return <div className={`planner-image-missing ${story ? 'story' : ''}`}>Preview gambar belum tersedia.</div>;
    return <div className={`planner-generated-image ${story ? 'story' : ''} ${state}`}>
        {state === 'loading' && <span className="planner-image-placeholder" aria-label="Memuat preview gambar" />}
        {state === 'error' && <span className="planner-image-error">Preview gambar tidak dapat dimuat.</span>}
        <img src={src} alt={story ? 'Hasil visual Instagram Story' : 'Hasil visual Instagram Feed'} loading="lazy" onLoad={() => setState('loaded')} onError={() => setState('error')} />
    </div>;
}

function GenerationSkeleton({ output }) {
    if (output.key === 'threads') return <div className="planner-skeleton-text" aria-label="Teks Threads sedang dibuat"><i /><i /><i /></div>;
    return <div className={`planner-skeleton-media ${output.key === 'story' ? 'story' : ''}`} aria-label="Visual sedang dibuat"><span /><i /><i /></div>;
}

export function PlannerGenerationCard({ schedule, content, command, mode = 'normal', agents = [], requestState, optimisticQueued = false, selected = false, onSelect = () => {}, onGenerate = () => {}, onReview = () => {}, onEdit = () => {}, onPause = () => {}, onDelete = () => {}, onSetH1 = () => {} }) {
    const output = resolvePlannerOutput(schedule);
    const status = generationStatus(content, command, optimisticQueued);
    const requesting = requestState?.phase === 'requesting';
    const failedRequest = requestState?.phase === 'error' && status === 'draft';
    const finished = ['preview_ready', 'ready_for_review', 'ready', 'approved', 'published'].includes(status);
    const unavailableArticle = output.key === 'article' && !articleReady(output, agents);
    const effectiveStatus = failedRequest && !finished ? 'failed' : unavailableArticle && status === 'draft' ? 'worker_unavailable' : status;
    const processing = ['queued_for_generation', 'generating'].includes(status) && !failedRequest;
    const canRetry = failedRequest || content?.status === 'failed';
    const automaticPublishing = schedule.publishingMode === 'automatic';
    const preview = contentPreview(content);
    const source = String(schedule.metadata?.source || 'manual');
    const sourceLabel = source.toLowerCase() === 'analyst' ? 'Analyst' : source === 'manual' ? 'Manual' : source;
    const buttonLabel = requesting ? 'Memulai generation...' : finished ? 'Lihat Hasil'
        : output.key === 'unknown' ? 'Tentukan Output'
        : unavailableArticle ? 'Worker Artikel Belum Tersedia'
        : automaticPublishing ? 'Ubah Publishing ke Review'
        : failedRequest || status === 'failed' ? canRetry ? 'Coba Lagi' : 'Periksa Worker'
        : processing ? status === 'generating' ? 'Sedang dibuat' : 'Dalam antrean'
        : !schedule.isActive ? 'Jadwal dijeda' : output.button;
    const buttonDisabled = !finished && output.key !== 'unknown' && (requesting || processing || unavailableArticle || automaticPublishing || !schedule.isActive || (status === 'failed' && !canRetry));
    const buttonTitle = unavailableArticle ? 'Worker artikel belum aktif di production.' : automaticPublishing ? 'Ubah Publishing menjadi Review sebelum menjalankan generation manual.' : status === 'failed' && !canRetry ? 'Command gagal, tetapi konten masih terantre. Retry dapat menghasilkan duplikasi dan perlu ditangani di backend.' : undefined;
    const primaryAction = finished ? onReview : output.key === 'unknown' ? onEdit : onGenerate;
    return <article className={`planner-generation-card planner-card-${mode} ${!schedule.isActive ? 'paused-schedule' : ''} status-${effectiveStatus}`} data-output={output.key} data-status={effectiveStatus}>
        <label className="planner-select"><input type="checkbox" aria-label={`Pilih ${schedule.name}`} checked={selected} onChange={onSelect} /></label>
        <div className="planner-card-content">
            <div className="planner-card-top"><div><span className="planner-output-tag">{output.label}</span><strong>{schedule.name}</strong></div><span className={`planner-status status-${effectiveStatus}`} aria-live="polite">{requesting || processing ? <LoaderCircle size={13} className="planner-spin" aria-hidden="true" /> : finished ? <CheckCircle2 size={13} aria-hidden="true" /> : effectiveStatus === 'failed' ? <AlertCircle size={13} aria-hidden="true" /> : null}{requesting ? 'Memulai...' : STATUS_LABELS[effectiveStatus] || 'Belum dibuat'}</span></div>
            {mode !== 'compact' && <dl className="planner-card-meta"><div><dt>Source</dt><dd>{sourceLabel}</dd></div><div><dt>Output</dt><dd>{output.label}</dd></div><div><dt>Worker</dt><dd>{output.worker}</dd></div><div><dt>Generation</dt><dd>{schedule.generationMode === 'automatic' ? 'Otomatis' : 'Manual'}</dd></div><div><dt>Publishing</dt><dd>{schedule.publishingMode === 'automatic' ? 'Otomatis' : 'Review'}</dd></div><div><dt>Status</dt><dd>{STATUS_LABELS[effectiveStatus] || 'Belum dibuat'}</dd></div></dl>}
            {output.workerMismatch && mode === 'expanded' && <p className="planner-card-note">Worker yang ditugaskan: {output.assignedAgent}. Routing generasi mengikuti jenis output.</p>}
            {unavailableArticle && <p className="planner-card-note">Worker artikel belum aktif di production. Jadwal tetap tersimpan.</p>}
            {automaticPublishing && <p className="planner-card-note">Publishing otomatis aktif pada jadwal ini. Ubah ke Review sebelum generate manual agar hasil berhenti untuk ditinjau.</p>}
            {requesting && <div className="planner-progress-card" role="status"><LoaderCircle size={16} className="planner-spin" /> Mengirim permintaan ke Laravel...</div>}
            {processing && <div className="planner-progress-card" role="status"><div className="planner-progress-copy"><LoaderCircle size={17} className="planner-spin" /><span><strong>{status === 'generating' ? 'Sedang dibuat' : 'Menunggu giliran worker'}</strong><small>{output.worker} · {STATUS_LABELS[status]}</small></span></div>{mode === 'expanded' && <GenerationSkeleton output={output} />}</div>}
            {(!finished && failedRequest || status === 'failed') && <div className="planner-error-card" role="alert"><AlertCircle size={16} /><span><strong>{failedRequest ? 'Generation gagal dimulai' : 'Generation gagal'}</strong><small>{safeGenerationError(failedRequest ? requestState.message : command?.error || content?.metadata?.error)}</small></span></div>}
            {finished && mode !== 'compact' && <div className="planner-result"><span className="planner-success"><CheckCircle2 size={14} /> {output.key === 'feed' ? 'Feed' : output.key === 'story' ? 'Story' : output.key === 'threads' ? 'Threads' : 'Konten'} berhasil dibuat</span>{['feed', 'story'].includes(output.key) && <GeneratedImage key={`${content?.id}-${preview.imageUrl}`} src={preview.imageUrl} story={output.key === 'story'} />}{output.key !== 'story' && preview.text && <p className="planner-result-text">{preview.text}</p>}{output.key === 'threads' && !preview.text && <p className="planner-card-note">Teks hasil belum tersedia pada respons.</p>}</div>}
            <div className="planner-card-footer"><button className="planner-primary-action" type="button" disabled={buttonDisabled} title={buttonTitle} onClick={primaryAction}>{requesting && <LoaderCircle size={14} className="planner-spin" />}{buttonLabel}</button><details className="planner-row-menu"><summary>Opsi</summary><div className="planner-actions"><button type="button" onClick={onSetH1}>Set H-1</button><button type="button" onClick={onEdit}>Edit</button><button type="button" onClick={onPause}>{schedule.isActive ? 'Jeda' : 'Lanjutkan'}</button><button type="button" onClick={onDelete}>Hapus</button></div></details></div>
        </div>
    </article>;
}
