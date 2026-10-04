export const PANEL_MODES = ['compact', 'normal', 'expanded'];
export function normalizePanelMode(value) {
    return PANEL_MODES.includes(value) ? value : 'normal';
}
export function readPanelMode(key, storage = globalThis.localStorage) {
    try { return normalizePanelMode(storage?.getItem(key)); } catch { return 'normal'; }
}
export function writePanelMode(key, value, storage = globalThis.localStorage) {
    const mode = normalizePanelMode(value);
    try { storage?.setItem(key, mode); } catch { /* Private browsing may disable storage. */ }
    return mode;
}
