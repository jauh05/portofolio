import './bootstrap';
import { mountBackgroundParticles } from './Components/BackgroundParticlesWrapper.jsx';

console.log('App.js loaded, initializing background particles...');
document.addEventListener('DOMContentLoaded', () => {
    console.log('DOM Content Loaded, mounting particles...');
    mountBackgroundParticles('react-background-particles');
});
