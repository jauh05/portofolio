import './bootstrap';
import { createRoot } from 'react-dom/client';
import React from 'react';
import { mountBackgroundParticles } from './Components/BackgroundParticlesWrapper.jsx';
import SplitText from './Components/SplitText.jsx';
import RotatingText from './Components/RotatingText.jsx';
import Lanyard from './Components/Lanyard.jsx';
import BubbleMenu from './Components/BubbleMenu.jsx';
import ProfileCard from './Components/ProfileCard.jsx';
import LogoLoop from './Components/LogoLoop.jsx';
import ScrollStack, { ScrollStackItem } from './Components/ScrollStack.jsx';
import * as SiIcons from 'react-icons/si';
import Lenis from 'lenis';

// Global Lenis instance
let lenis;

const initGlobalScroll = () => {
    lenis = new Lenis({
        duration: 1.2,
        easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
        orientation: 'vertical',
        gestureOrientation: 'vertical',
        smoothWheel: true,
        wheelMultiplier: 1,
        touchMultiplier: 2,
        infinite: false,
    });

    function raf(time) {
        lenis.raf(time);
        requestAnimationFrame(raf);
    }

    requestAnimationFrame(raf);

    // Sync AOS with Lenis
    lenis.on('scroll', () => {
        if (window.AOS) {
            window.AOS.refresh();
        }
    });
};

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

// Automatic mounting for Lanyard
const initLanyard = () => {
    const elements = document.querySelectorAll('[data-lanyard]');
    elements.forEach(el => {
        const root = createRoot(el);
        root.render(<Lanyard />);
    });
};

const initBubbleMenu = () => {
    const elements = document.querySelectorAll('[data-bubble-menu]');
    elements.forEach(el => {
        const items = JSON.parse(el.getAttribute('data-items') || '[]');
        const logo = el.getAttribute('data-logo') || 'JF';
        const root = createRoot(el);
        root.render(
            <BubbleMenu
                items={items}
                logo={<span className="font-black text-xl tracking-tighter">{logo}</span>}
                menuBg="rgba(24, 24, 27, 0.8)"
                menuContentColor="#ffffff"
                useFixedPosition={true}
            />
        );
    });
};

const initProfileCard = () => {
    const elements = document.querySelectorAll('[data-profile-card]');
    elements.forEach(el => {
        const name = el.getAttribute('data-name');
        const title = el.getAttribute('data-title');
        const root = createRoot(el);
        root.render(<ProfileCard name={name} title={title} avatarUrl="/foto.png" />);
    });
};

const initLogoLoop = () => {
    const elements = document.querySelectorAll('[data-logo-loop]');
    elements.forEach(el => {
        const speed = parseInt(el.getAttribute('data-speed') || "120", 10);
        const direction = el.getAttribute('data-direction') || "left";
        const isMobile = window.innerWidth < 768;
        const logoHeight = isMobile ? 32 : parseInt(el.getAttribute('data-logo-height') || "40", 10);
        const gap = isMobile ? 32 : parseInt(el.getAttribute('data-gap') || "40", 10);
        const fadeOut = el.getAttribute('data-fade-out') === "true";
        const fadeOutColor = el.getAttribute('data-fade-color');

        // Use icons from react-icons/si if the logo has an 'icon' property
        const rawLogos = JSON.parse(el.getAttribute('data-logos') || '[]');
        const processedLogos = rawLogos.map(l => {
            if (l.icon && SiIcons[l.icon]) {
                const Icon = SiIcons[l.icon];
                return { ...l, node: <Icon /> };
            }
            return l;
        });

        const root = createRoot(el);
        root.render(
            <LogoLoop
                logos={processedLogos}
                speed={speed}
                direction={direction}
                logoHeight={logoHeight}
                gap={gap}
                fadeOut={fadeOut}
                fadeOutColor={fadeOutColor}
                scaleOnHover={true}
            />
        );
    });
};

const initScrollStack = () => {
    const elements = document.querySelectorAll('[data-scroll-stack]');
    elements.forEach(el => {
        const rawItems = JSON.parse(el.getAttribute('data-items') || '[]');
        const useWindow = el.getAttribute('data-use-window') === "true";
        const root = createRoot(el);
        root.render(
            <ScrollStack useWindowScroll={useWindow}>
                {rawItems.map((item, i) => (
                    <ScrollStackItem key={i} itemClassName={item.className || "bg-white dark:bg-zinc-900 border border-zinc-200 dark:border-white/5"}>
                        <div dangerouslySetInnerHTML={{ __html: item.content }} />
                    </ScrollStackItem>
                ))}
            </ScrollStack>
        );
    });
};

document.addEventListener('DOMContentLoaded', () => {
    console.log('Vite App Initializing...');
    initGlobalScroll();
    initParticles();

    // Staggered initialization for better performance and smoother entry
    setTimeout(() => {
        initTextAnimations();
        initRotatingText();
        initLanyard();
        initBubbleMenu();
        initProfileCard();
        initLogoLoop();
        initScrollStack();
    }, 1000);
});
