import React, { useEffect, useRef, useState } from 'react';
import { Bot, Send, Sparkles, X } from 'lucide-react';
import { request } from './useOfficeData';
import { SUGGESTIONS, approvalNotice, dismissNotice, formatScheduledAt, historyFrom, platformLabel, selectAll, timingLabel, toggleSelection } from './plannerChat';

let messageSeq = 0;
const newId = () => `chat-${Date.now()}-${messageSeq++}`;

function ProposalCard({ message, onToggle, onApprove, onDismiss, onRevise }) {
    const { proposal } = message;
    const locked = proposal.state !== 'open';
    return <div className="pchat-proposal">
        <ul className="pchat-items">
            {proposal.items.map(item => {
                const checked = proposal.selected.includes(item.plan_id);
                return <li key={item.plan_id} className={checked ? 'is-checked' : ''}>
                    <label>
                        <input type="checkbox" checked={checked} disabled={locked} onChange={() => onToggle(message.id, item.plan_id)} />
                        <span><strong>{item.topic}</strong><small>{platformLabel(item)} · {formatScheduledAt(item.scheduled_at)}</small></span>
                        <em className={`pchat-timing t-${item.generation_timing_label}`}>{timingLabel(item)}</em>
                    </label>
                </li>;
            })}
        </ul>
        {proposal.state === 'open' && <div className="pchat-actions">
            <button type="button" className="pchat-primary" disabled={!proposal.selected.length} onClick={() => onApprove(message)}>Buat jadwal</button>
            <button type="button" onClick={() => onRevise()}>Ubah</button>
            <button type="button" onClick={() => onDismiss(message)} disabled={!proposal.selected.length}>Batal</button>
        </div>}
        {proposal.state === 'saving' && <p className="pchat-muted">Menyimpan…</p>}
        {proposal.state === 'approved' && <p className="pchat-done">Jadwal sudah dibuat.</p>}
        {proposal.state === 'dismissed' && <p className="pchat-muted">Usulan dibatalkan.</p>}
    </div>;
}

export function ContentPlannerChat({ workspaceId, onChanged }) {
    const [open, setOpen] = useState(false);
    const [messages, setMessages] = useState([]);
    const [draft, setDraft] = useState('');
    const [pending, setPending] = useState(false);
    const bodyRef = useRef(null);
    const inputRef = useRef(null);

    useEffect(() => {
        if (bodyRef.current) bodyRef.current.scrollTop = bodyRef.current.scrollHeight;
    }, [messages, pending]);

    const append = message => setMessages(current => [...current, { id: newId(), ...message }]);
    const patch = (id, changes) => setMessages(current => current.map(message => (message.id === id ? { ...message, ...changes } : message)));

    async function send(text) {
        const message = text.trim();
        if (message.length < 3 || pending) return;
        const history = historyFrom(messages);
        append({ role: 'user', text: message });
        setDraft('');
        setPending(true);
        try {
            const result = await request('/office/api/content-planner/chat', {
                method: 'POST',
                body: JSON.stringify({ message, workspace_id: workspaceId || null, history }),
            });
            if (result.status === 'needs_confirmation') {
                const items = result.items || [];
                append({ role: 'assistant', text: result.summary, proposal: { reportId: result.report_id, items, selected: selectAll(items), state: 'open' } });
            } else if (result.status === 'ambiguous') {
                append({ role: 'assistant', text: result.summary || 'Bisa tambahkan detail yang kurang?' });
            } else {
                append({ role: 'system', tone: 'error', text: result.message || result.summary || 'Permintaan belum bisa diproses.' });
            }
        } catch (failure) {
            append({ role: 'system', tone: 'error', text: failure.message || 'Chat belum bisa dijawab. Coba lagi.' });
        } finally {
            setPending(false);
        }
    }

    async function decide(message, action) {
        const { reportId, selected } = message.proposal;
        if (!selected.length) return;
        patch(message.id, { proposal: { ...message.proposal, state: 'saving' } });
        try {
            await request(`/office/api/analyst-reports/${reportId}/${action}`, { method: 'POST', body: JSON.stringify({ plan_ids: selected }) });
            patch(message.id, { proposal: { ...message.proposal, state: action === 'approve' ? 'approved' : 'dismissed' } });
            append(action === 'approve' ? { role: 'system', tone: 'success', text: approvalNotice(selected.length) } : { role: 'system', text: dismissNotice() });
            onChanged?.();
        } catch (failure) {
            patch(message.id, { proposal: { ...message.proposal, state: 'open' } });
            append({ role: 'system', tone: 'error', text: failure.message || 'Aksi belum berhasil. Coba lagi.' });
        }
    }

    function toggle(messageId, planId) {
        setMessages(current => current.map(message => (message.id === messageId
            ? { ...message, proposal: { ...message.proposal, selected: toggleSelection(message.proposal.selected, planId) } }
            : message)));
    }

    function revise() {
        setDraft('Ubah rencana: ');
        inputRef.current?.focus();
    }

    return <>
        <button type="button" className="pchat-fab" aria-expanded={open} onClick={() => setOpen(value => !value)}>
            <Sparkles size={18} /><span>AI Planner</span>
        </button>
        {open && <aside className="pchat-panel" role="dialog" aria-label="AI Content Planner">
            <header className="pchat-header">
                <div><span className="pchat-kicker">AI CONTENT PLANNER</span><strong>Trent · Office AI</strong></div>
                <button type="button" className="pchat-close" onClick={() => setOpen(false)} aria-label="Tutup chat"><X size={16} /></button>
            </header>
            <div className="pchat-body" ref={bodyRef} aria-live="polite">
                {messages.length === 0 && <div className="pchat-empty">
                    <Bot size={22} />
                    <p>Ceritakan rencana kontenmu. Sebutkan platform, tanggal, dan timing generate: sekarang, H-1, H-2, atau manual.</p>
                    <div className="pchat-chips">{SUGGESTIONS.map(text => <button key={text} type="button" onClick={() => send(text)} disabled={pending}>{text}</button>)}</div>
                </div>}
                {messages.map(message => {
                    if (message.role === 'system') return <p key={message.id} className={`pchat-notice ${message.tone || ''}`} role={message.tone === 'error' ? 'alert' : 'status'}>{message.text}</p>;
                    if (message.role === 'user') return <div key={message.id} className="pchat-bubble is-user">{message.text}</div>;
                    return <div key={message.id} className="pchat-bubble is-assistant">
                        <p>{message.text}</p>
                        {message.proposal && <ProposalCard message={message} onToggle={toggle} onApprove={item => decide(item, 'approve')} onDismiss={item => decide(item, 'dismiss')} onRevise={revise} />}
                    </div>;
                })}
                {pending && <div className="pchat-typing" role="status" aria-label="Trent sedang mengetik"><i /><i /><i /></div>}
            </div>
            <form className="pchat-form" onSubmit={event => { event.preventDefault(); send(draft); }}>
                <textarea ref={inputRef} value={draft} rows={2} maxLength={2000} aria-label="Pesan untuk AI Content Planner"
                    placeholder="Contoh: buat konten Threads tentang tips AI besok jam 9"
                    onChange={event => setDraft(event.target.value)}
                    onKeyDown={event => { if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); send(draft); } }} />
                <button type="submit" className="pchat-send" disabled={pending || draft.trim().length < 3} aria-label="Kirim"><Send size={16} /></button>
            </form>
        </aside>}
    </>;
}
