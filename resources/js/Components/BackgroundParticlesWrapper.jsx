import React, { useState, useEffect } from 'react';
import { createRoot } from 'react-dom/client';
import Particles from './Particles';

const BackgroundParticlesWrapper = () => {
    const [color, setColor] = useState(() => {
        return localStorage.getItem('color') || '59, 130, 246';
    });

    useEffect(() => {
        const handleColorChange = (e) => {
            if (e.detail && e.detail.color) {
                setColor(e.detail.color);
            }
        };

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

    const rgbToHex = (rgbString) => {
        const coords = rgbString.split(',').map(s => parseInt(s.trim(), 10));
        if (coords.length !== 3) return '#ffffff';
        return '#' + coords.map(x => {
            const hex = x.toString(16);
            return hex.length === 1 ? '0' + hex : hex;
        }).join('');
    };

    const hexColor = rgbToHex(color);

    return (
        <div style={{ width: '100%', height: '100%', position: 'absolute', top: 0, left: 0, zIndex: 0, pointerEvents: 'none' }}>
            <Particles
                particleColors={['#ffffff', hexColor, '#60a5fa', '#a855f7']}
                particleCount={1000}
                particleSpread={15}
                speed={0.3}
                particleBaseSize={60}
                moveParticlesOnHover={true}
                particleHoverFactor={1.5}
                alphaParticles={true}
                disableRotation={false}
                pixelRatio={window.devicePixelRatio || 1}
            />
        </div>
    );
};

export const mountBackgroundParticles = (elementId) => {
    console.log('Attempting to mount particles on:', elementId);
    const el = document.getElementById(elementId);
    if (el) {
        console.log('Container found, creating root...');
        const root = createRoot(el);
        root.render(<BackgroundParticlesWrapper />);
    } else {
        console.warn('Particle container not found:', elementId);
    }
};
