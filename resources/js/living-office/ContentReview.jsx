import React, { useState, useEffect } from 'react';
import { X, Edit2, CheckCircle2, RefreshCw, Wand2 } from 'lucide-react';
import './content-review.css';

export function ContentReview({ contentItem, onClose, generateContent, reviseContent, updateContent, approveContent }) {
    if (!contentItem) return null;

    const [isEditing, setIsEditing] = useState(false);
    const [payload, setPayload] = useState({});
    const [revisePrompt, setRevisePrompt] = useState('');
    const [isRevising, setIsRevising] = useState(false);
    const [isGenerating, setIsGenerating] = useState(false);
    const [preview, setPreview] = useState(null);
    const [error, setError] = useState(null);

    useEffect(() => {
        try {
            if (contentItem.text) {
                setPayload(JSON.parse(contentItem.text));
            } else {
                setPayload({});
            }
        } catch (e) {
            setPayload({});
        }
    }, [contentItem.text]);

    const handleGenerate = async () => {
        setIsGenerating(true); setError(null);
        try {
            await generateContent(contentItem.id);
        } catch (e) {
            setError(e.message || 'Gagal generate content');
        } finally {
            setIsGenerating(false);
        }
    };

    const handleRevise = async (e) => {
        e.preventDefault();
        setIsRevising(true); setError(null);
        try {
            const result = await reviseContent(contentItem.id, revisePrompt);
            setPreview(result.preview);
        } catch (e) {
            setError(e.message || 'Gagal revise content');
        } finally {
            setIsRevising(false);
        }
    };

    const handleSaveManual = async () => {
        try {
            await updateContent(contentItem.id, {
                title: payload.title || contentItem.title,
                text: JSON.stringify(payload),
                status: 'ready_for_review',
                revision_instruction: 'Manual edit'
            });
            setIsEditing(false);
        } catch (e) {
            setError(e.message);
        }
    };

    const handleAcceptPreview = async () => {
        try {
            await updateContent(contentItem.id, {
                title: preview.title || contentItem.title,
                text: JSON.stringify(preview),
                status: 'ready_for_review',
                revision_instruction: revisePrompt,
                source: 'ai'
            });
            setPreview(null);
            setRevisePrompt('');
        } catch (e) {
            setError(e.message);
        }
    };

    const renderPayload = (data) => {
        if (!data) return null;
        return (
            <div className="content-payload">
                {data.title && <p><strong>Title:</strong> {data.title}</p>}
                {data.hook && <p><strong>Hook:</strong> {data.hook}</p>}
                {data.content && <p><strong>Content:</strong> {data.content}</p>}
                {data.caption && <p><strong>Caption:</strong> {data.caption}</p>}
                {data.cta && <p><strong>CTA:</strong> {data.cta}</p>}
                {data.hashtags && <p><strong>Hashtags:</strong> {data.hashtags.join(' ')}</p>}
                {data.visual_brief ? <p><strong>Visual Brief:</strong> {data.visual_brief}</p> : <p className="visual-belum">Visual belum dibuat</p>}
                {data.frames && data.frames.map(f => (
                    <div key={f.frame} className="story-frame">
                        <p><strong>Frame {f.frame}:</strong></p>
                        <p>Text: {f.text}</p>
                        <p>Visual: {f.visual_brief || 'Visual belum dibuat'}</p>
                    </div>
                ))}
            </div>
        );
    };

    const displayData = preview || payload;

    return (
        <div className="planner-modal-wrap">
            <button className="planner-modal-scrim" onClick={onClose}></button>
            <section className="planner-modal content-review-modal">
                <header>
                    <div>
                        <span>CONTENT REVIEW</span>
                        <h2>{contentItem.brand?.name} · {contentItem.platform} {contentItem.content_type}</h2>
                    </div>
                    <button onClick={onClose}><X size={18} /></button>
                </header>

                <div className="review-body">
                    <div className="review-meta">
                        <span>Status: <strong>{contentItem.status}</strong></span>
                        <span>Date: {contentItem.metadata?.scheduled_at ? new Date(contentItem.metadata.scheduled_at).toLocaleString() : 'N/A'}</span>
                    </div>

                    {error && <p className="planner-form-error">{error}</p>}

                    {contentItem.status === 'draft' || contentItem.status === 'failed' ? (
                        <div className="generate-prompt">
                            <p>{contentItem.status === 'failed' ? 'Draft belum berhasil dibuat.' : 'Content draft belum digenerate.'}</p>
                            <button className="primary-button" onClick={handleGenerate} disabled={isGenerating}>
                                {isGenerating ? 'Generating...' : (contentItem.status === 'failed' ? 'Try again' : 'Generate Content')}
                            </button>
                        </div>
                    ) : contentItem.status === 'generating' ? (
                        <p>Generating content...</p>
                    ) : (
                        <div className="review-content-area">
                            {isEditing ? (
                                <textarea 
                                    className="edit-textarea"
                                    value={JSON.stringify(payload, null, 2)} 
                                    onChange={e => {
                                        try { setPayload(JSON.parse(e.target.value)); } catch(err){}
                                    }}
                                />
                            ) : (
                                renderPayload(displayData)
                            )}

                            {preview && (
                                <div className="preview-actions">
                                    <button onClick={handleAcceptPreview} className="primary-button">Accept Revision</button>
                                    <button onClick={() => setPreview(null)}>Discard Preview</button>
                                </div>
                            )}

                            {!isEditing && !preview && contentItem.status !== 'approved' && (
                                <form onSubmit={handleRevise} className="revise-form">
                                    <input 
                                        type="text" 
                                        placeholder="e.g. Buat caption lebih santai..." 
                                        value={revisePrompt} 
                                        onChange={e => setRevisePrompt(e.target.value)} 
                                        required 
                                    />
                                    <button type="submit" disabled={isRevising}>
                                        <Wand2 size={14}/> {isRevising ? 'Revising...' : 'Revise with AI'}
                                    </button>
                                </form>
                            )}

                            <div className="review-actions">
                                {isEditing ? (
                                    <>
                                        <button onClick={handleSaveManual}>Save Edit</button>
                                        <button onClick={() => setIsEditing(false)}>Cancel</button>
                                    </>
                                ) : (
                                    <>
                                        {contentItem.status !== 'approved' && <button onClick={() => setIsEditing(true)}><Edit2 size={14}/> Edit</button>}
                                        {contentItem.status !== 'approved' && <button onClick={handleGenerate}><RefreshCw size={14}/> Regenerate</button>}
                                        {contentItem.status !== 'approved' && <button className="primary-button" onClick={() => approveContent(contentItem.id)}><CheckCircle2 size={14}/> Approve</button>}
                                    </>
                                )}
                            </div>
                        </div>
                    )}
                </div>
            </section>
        </div>
    );
}
