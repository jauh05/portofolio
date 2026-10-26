import React, { useState } from 'react';
import { DndContext, DragOverlay, PointerSensor, useDraggable, useDroppable, useSensor, useSensors } from '@dnd-kit/core';
import { PLANNER_DAYS, chipLabel, chipTone, sortChips } from './plannerGridModel';

const dayId = (day) => `day-${day}`;

// Menu kecil di bawah kartu: ganti jam atau hapus kartu dari hari ini.
function CardMenu({ chip, onSetTime, onRemove, onClose }) {
    const [value, setValue] = useState(chip.time || '');
    const [saving, setSaving] = useState(false);

    const save = async (event) => {
        event.preventDefault();
        setSaving(true);
        await onSetTime(chip, value || null);
        setSaving(false);
        onClose();
    };

    const remove = async () => {
        setSaving(true);
        await onRemove(chip);
        setSaving(false);
        onClose();
    };

    return (
        <form className="slot-menu" onSubmit={save}>
            <label>
                <span>Jam tayang</span>
                <input type="time" value={value} onChange={(event) => setValue(event.target.value)} />
            </label>
            <div className="slot-menu-actions">
                <button type="submit" disabled={saving}>Simpan jam</button>
                <button type="button" onClick={onClose}>Batal</button>
            </div>
            <button type="button" className="slot-menu-remove" disabled={saving} onClick={remove}>Hapus kartu</button>
        </form>
    );
}

function DraggableChip({ chip, onSetTime, onRemove }) {
    const [open, setOpen] = useState(false);
    const { attributes, listeners, setNodeRef, isDragging } = useDraggable({ id: chip.id, data: { chip }, disabled: open });

    return (
        <>
            <div className={`slot-row ${isDragging ? 'is-dragging' : ''}`}>
                <span className="slot-time">{chip.time || '--:--'}</span>
                <button
                    type="button"
                    ref={setNodeRef}
                    className={`slot-chip-wrap tone-${chipTone(chip.key)}`}
                    title={`${chip.label} · klik untuk ubah jam atau hapus`}
                    aria-expanded={open}
                    onClick={() => setOpen((value) => !value)}
                    {...attributes}
                    {...listeners}
                >
                    <b>{chipLabel(chip.key)}</b>
                </button>
            </div>
            {open && <CardMenu chip={chip} onSetTime={onSetTime} onRemove={onRemove} onClose={() => setOpen(false)} />}
        </>
    );
}

function DayColumn({ day, name, chips, onSetTime, onRemove }) {
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
                    {unscheduled.map((chip) => <DraggableChip key={chip.id} chip={chip} onSetTime={onSetTime} onRemove={onRemove} />)}
                </div>
            )}
            {timed.map((chip) => <DraggableChip key={chip.id} chip={chip} onSetTime={onSetTime} onRemove={onRemove} />)}
            {chips.length === 0 && <p className="slot-empty">Seret kartu ke sini</p>}
        </section>
    );
}

export function ContentSlotGrid({ chips, onMove, onSetTime, onRemove }) {
    const [activeChip, setActiveChip] = useState(null);
    // Jarak 6 px dibutuhkan supaya klik biasa tidak dianggap drag.
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
                    return (
                        <DayColumn
                            key={day}
                            day={day}
                            name={name}
                            chips={chips.filter((chip) => chip.day === day)}
                            onSetTime={onSetTime}
                            onRemove={onRemove}
                        />
                    );
                })}
            </div>
            <DragOverlay>
                {activeChip ? <div className={`slot-overlay tone-${chipTone(activeChip.key)}`}><b>{chipLabel(activeChip.key)}</b></div> : null}
            </DragOverlay>
        </DndContext>
    );
}
