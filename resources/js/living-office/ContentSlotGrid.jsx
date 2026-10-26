import React, { useState } from 'react';
import { DndContext, DragOverlay, PointerSensor, useDraggable, useDroppable, useSensor, useSensors } from '@dnd-kit/core';
import { Pencil } from 'lucide-react';
import { PLANNER_DAYS, chipLabel, chipTone, sortChips } from './plannerGridModel';

const dayId = (day) => `day-${day}`;

function ChipFace({ chip }) {
    return <b>{chipLabel(chip.key)}</b>;
}

function TimeEditor({ chip, onSave, onCancel }) {
    const [value, setValue] = useState(chip.time || '');
    const [saving, setSaving] = useState(false);

    const submit = async (event) => {
        event.preventDefault();
        setSaving(true);
        try {
            await onSave(value || null);
        } finally {
            setSaving(false);
        }
    };

    return (
        <form className="slot-time-editor" onSubmit={submit}>
            <input type="time" value={value} onChange={(event) => setValue(event.target.value)} aria-label="Jam tayang" required />
            <button type="submit" disabled={saving}>Simpan</button>
            <button type="button" onClick={onCancel}>Batal</button>
        </form>
    );
}

function DraggableChip({ chip, onSetTime }) {
    const [editing, setEditing] = useState(false);
    const { attributes, listeners, setNodeRef, isDragging } = useDraggable({ id: chip.id, data: { chip }, disabled: editing });

    return (
        <div className={`slot-row ${isDragging ? 'is-dragging' : ''}`}>
            <span className="slot-time">{chip.time || '--:--'}</span>
            <div className="slot-stack">
                <div
                    ref={setNodeRef}
                    className={`slot-chip-wrap tone-${chipTone(chip.key)}`}
                    title={chip.label}
                    onDoubleClick={() => setEditing(true)}
                    {...attributes}
                    {...listeners}
                >
                    <ChipFace chip={chip} />
                    <button
                        type="button"
                        className="slot-edit"
                        aria-label="Ubah jam"
                        onPointerDown={(event) => event.stopPropagation()}
                        onClick={() => setEditing(true)}
                    >
                        <Pencil size={12} />
                    </button>
                </div>
                {editing && (
                    <TimeEditor
                        chip={chip}
                        onCancel={() => setEditing(false)}
                        onSave={async (time) => {
                            await onSetTime(chip, time);
                            setEditing(false);
                        }}
                    />
                )}
            </div>
        </div>
    );
}

function DayColumn({ day, name, chips, onSetTime }) {
    const { setNodeRef, isOver } = useDroppable({ id: dayId(day) });
    const unscheduled = chips.filter((chip) => !chip.time);
    const timed = sortChips(chips.filter((chip) => chip.time));

    return (
        <section ref={setNodeRef} className={`slot-col ${isOver ? 'is-over' : ''}`} aria-label={name}>
            <header className="slot-col-head">
                <b>{name}</b>
                <span>{chips.length}</span>
            </header>
            {unscheduled.length > 0 && (
                <div className="slot-group">
                    <p className="slot-group-label">Belum ada jam</p>
                    {unscheduled.map((chip) => <DraggableChip key={chip.id} chip={chip} onSetTime={onSetTime} />)}
                </div>
            )}
            {timed.map((chip) => <DraggableChip key={chip.id} chip={chip} onSetTime={onSetTime} />)}
            {chips.length === 0 && <p className="slot-empty">Seret kartu ke sini</p>}
        </section>
    );
}

export function ContentSlotGrid({ chips, onMove, onSetTime }) {
    const [activeChip, setActiveChip] = useState(null);
    // Jarak 6 px dibutuhkan supaya klik dan double-click tidak dianggap drag.
    const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }));

    const handleDragEnd = ({ active, over }) => {
        setActiveChip(null);
        const chip = active.data.current?.chip;
        if (!chip || !over) return;
        const day = Number(String(over.id).replace('day-', ''));
        if (day !== chip.day) onMove(chip, day);
    };

    return (
        <DndContext
            sensors={sensors}
            onDragStart={({ active }) => setActiveChip(active.data.current?.chip ?? null)}
            onDragEnd={handleDragEnd}
            onDragCancel={() => setActiveChip(null)}
        >
            <div className="slot-board" aria-label="Grid jadwal mingguan">
                {PLANNER_DAYS.map((name, index) => {
                    const day = index + 1;
                    return <DayColumn key={day} day={day} name={name} chips={chips.filter((chip) => chip.day === day)} onSetTime={onSetTime} />;
                })}
            </div>
            <DragOverlay>
                {activeChip ? <div className={`slot-overlay tone-${chipTone(activeChip.key)}`}><ChipFace chip={activeChip} /></div> : null}
            </DragOverlay>
        </DndContext>
    );
}
