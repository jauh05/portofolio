import { hashId } from './layout.js';

const shirts = ['#2878bd', '#5169ad', '#2c8a85', '#7b6da7', '#b77b54', '#3379a0', '#7a8c51', '#905d79'];
const pants = ['#263f62', '#354b68', '#2e4b54', '#4b4664'];
const hair = ['#302b31', '#603b2c', '#8a6341', '#1d3549', '#a57950'];
const skin = ['#9d6547', '#bb8260', '#d39e76', '#e4b78f', '#f2cba8'];

export function workerVisualVariant(id) {
    const seed = hashId(String(id));
    return {
        shirt: shirts[seed % shirts.length],
        pants: pants[(seed >>> 4) % pants.length],
        hair: hair[(seed >>> 9) % hair.length],
        skin: skin[(seed >>> 13) % skin.length],
        hairStyle: (seed >>> 17) % 4,
        glasses: (seed >>> 21) % 3 === 0,
        jacket: (seed >>> 24) % 3 === 0,
    };
}

export function monitorLayoutVariant(id, stationId = '') {
    return hashId(`${String(id)}:${String(stationId)}`) % 4;
}
