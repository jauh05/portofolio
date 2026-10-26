import React from 'react';
import { PLANNER_DAYS, PLANNER_HOURS, chipLabel, chipTone, hourOf } from './plannerGridModel';

export function ContentSlotGrid({ chips, onMove }) {
    const cellChips = (day, hour) => chips.filter((chip) => chip.day === day && hourOf(chip.time) === hour);

    const handleDrop = (event, day, hour) => {
        event.preventDefault();
        const raw = event.dataTransfer.getData('text/plain');
        if (!raw) return;
        onMove(JSON.parse(raw), day, hour);
    };

    return (
        <div className="slot-grid" role="grid" aria-label="Grid jadwal mingguan">
            <div className="slot-corner" />
            {PLANNER_DAYS.map((name) => <div className="slot-day" role="columnheader" key={name}>{name}</div>)}
            {PLANNER_HOURS.map((hour) => (
                <React.Fragment key={hour}>
                    <div className="slot-hour" role="rowheader">{String(hour).padStart(2, '0')}.00</div>
                    {PLANNER_DAYS.map((_, index) => {
                        const day = index + 1;
                        return (
                            <div
                                className="slot-cell"
                                role="gridcell"
                                key={`${day}-${hour}`}
                                onDragOver={(event) => event.preventDefault()}
                                onDrop={(event) => handleDrop(event, day, hour)}
                            >
                                {cellChips(day, hour).map((chip) => (
                                    <button
                                        type="button"
                                        className={`slot-chip tone-${chipTone(chip.key)}`}
                                        key={chip.id}
                                        draggable
                                        title={`${chipLabel(chip.key)} · ${chip.time}`}
                                        onDragStart={(event) => event.dataTransfer.setData('text/plain', JSON.stringify(chip))}
                                    >
                                        <b>{chipLabel(chip.key)}</b>
                                        <span>{chip.time}</span>
                                    </button>
                                ))}
                            </div>
                        );
                    })}
                </React.Fragment>
            ))}
        </div>
    );
}
