import React, { useState, useEffect } from 'react';
import { createRoot } from 'react-dom/client';
import Particles from './Particles';

const BackgroundParticlesWrapper = () => {
    const [color, setColor] = useState(() => {
        return localStorage.getItem('color') || '59, 130, 246';
    });

    useEffect(() => {
        // We observe the localStorage changing color if possible,
        // Or we observe a custom event 'color-changed' that we can dispatch.

        // To handle custom event:
        const handleColorChange = (e) => {
            if (e.detail && e.detail.color) {
                setColor(e.detail.color);
            }
        };

        // Fallback: check localStorage periodically or handle storage events
        const handleStorage = () => {
            const stored = localStorage.getItem('color');
            if (stored) setColor(stored);
        };

        window.addEventListener('color-change', handleColorChange);
        window.addEventListener('storage', handleStorage);

        return () => {
            window.removeEventListener('color-change', handleColorChange);
            window.removeEventListener('storage', handleStorage);
        };
    }, []);

    // Convert "r, g, b" string to "#rrggbb" for the Particles component
    const rgbToHex = (rgbString) => {
        const coords = rgbString.split(',').map(s => parseInt(s.trim(), 10));
        if (coords.length !== 3) return '#ffffff'; // Fallback
        return '#' + coords.map(x => {
            const hex = x.toString(16);
            return hex.length === 1 ? '0' + hex : hex;
        }).join('');
    };

    const hexColor = rgbToHex(color);

    return (
        <div style={{ width: '100%', height: '100%', position: 'absolute', top: 0, left: 0, zIndex: 0, pointerEvents: 'none' }}>
            <Particles
                particleColors={[hexColor, '#ffffff']}
                particleCount={300}
                particleSpread={10}
                speed={0.1}
                particleBaseSize={100}
                moveParticlesOnHover={true}
                particleHoverFactor={2}
                alphaParticles={true}
                disableRotation={false}
                pixelRatio={window.devicePixelRatio || 1}
            />
        </div>
    );
};

// Mount utility
export const mountBackgroundParticles = (elementId) => {
    const el = document.getElementById(elementId);
    if (el) {
        const root = createRoot(el);
        root.render(<BackgroundParticlesWrapper />);
    }
};
