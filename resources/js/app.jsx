import './bootstrap';
import { createRoot } from 'react-dom/client';
import React from 'react';
import { mountBackgroundParticles } from './Components/BackgroundParticlesWrapper.jsx';
import SplitText from './Components/SplitText.jsx';
import RotatingText from './Components/RotatingText.jsx';

// Mounting function for Particles
const initParticles = () => {
    mountBackgroundParticles('react-background-particles');
};

// Automatic mounting for SplitText on any element with [data-split-text]
const initTextAnimations = () => {
    const elements = document.querySelectorAll('[data-split-text]');
    elements.forEach(el => {
        const text = el.getAttribute('data-split-text') || el.innerText;
        const delay = parseInt(el.getAttribute('data-split-delay') || '50', 10);
        const type = el.getAttribute('data-split-type') || 'chars';

        // Save the class list before clearing
        const classes = el.className;
        el.innerHTML = ''; // Clear for React

        const root = createRoot(el);
        root.render(
            <SplitText
                text={text}
                className={classes}
                delay={delay}
                splitType={type}
            />
        );
    });
};

// Automatic mounting for RotatingText
const initRotatingText = () => {
    const elements = document.querySelectorAll('[data-rotating-text]');
    elements.forEach(el => {
        const textsAttr = el.getAttribute('data-rotating-texts');
        if (!textsAttr) return;

        const texts = JSON.parse(textsAttr);
        const mainClassName = el.getAttribute('data-rotating-main-class') || "";
        const staggerFrom = el.getAttribute('data-rotating-stagger-from') || "last";
        const interval = parseInt(el.getAttribute('data-rotating-interval') || "2000", 10);

        const root = createRoot(el);
        root.render(
            <RotatingText
                texts={texts}
                mainClassName={mainClassName}
                staggerFrom={staggerFrom}
                rotationInterval={interval}
                animate={{ y: 0, opacity: 1 }}
                initial={{ y: "100%", opacity: 0 }}
                exit={{ y: "-120%", opacity: 0 }}
                staggerDuration={0.025}
                transition={{ type: "spring", damping: 30, stiffness: 400 }}
            />
        );
    });
};

document.addEventListener('DOMContentLoaded', () => {
    console.log('Vite App Initializing...');
    initParticles();

    // Higher delay to ensure Alpine.js x-text is fully rendered by the browser
    setTimeout(() => {
        initTextAnimations();
        initRotatingText();
    }, 800);
});
