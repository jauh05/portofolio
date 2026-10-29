import React from 'react';
import { CHIP_TYPES } from './plannerGridModel';

export function ContentPlannerMenu({ onPick, onClose }) {
    return (
        <div className="planner-menu" role="menu">
            {Object.entries(CHIP_TYPES).map(([key, type]) => (
                <button type="button" role="menuitem" className={`planner-menu-item tone-${type.tone}`} key={key} onClick={() => { onPick(key); onClose(); }}>
                    <i aria-hidden="true" />
                    {type.label}
                </button>
            ))}
        </div>
    );
}
