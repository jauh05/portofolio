import { useEffect, useRef, useState } from 'react';
import { gsap } from 'gsap';

const DEFAULT_ITEMS = [
    {
        label: 'home',
        href: '#',
        ariaLabel: 'Home',
        rotation: -8,
        hoverStyles: { bgColor: 'rgba(var(--primary-rgb), 1)', textColor: '#ffffff' }
    },
    {
        label: 'about',
        href: '#about',
        ariaLabel: 'About',
        rotation: 8,
        hoverStyles: { bgColor: '#10b981', textColor: '#ffffff' }
    },
    {
        label: 'projects',
        href: '#projects',
        ariaLabel: 'Projects',
        rotation: 8,
        hoverStyles: { bgColor: '#f59e0b', textColor: '#ffffff' }
    },
    {
        label: 'skills',
        href: '#skills',
        ariaLabel: 'Skills',
        rotation: 8,
        hoverStyles: { bgColor: '#ef4444', textColor: '#ffffff' }
    },
    {
        label: 'contact',
        href: '#contact',
        ariaLabel: 'Contact',
        rotation: -8,
        hoverStyles: { bgColor: '#8b5cf6', textColor: '#ffffff' }
    }
];

export default function BubbleMenu({
    logo,
    onMenuClick,
    className,
    style,
    menuAriaLabel = 'Toggle menu',
    menuBg = '#fff',
    menuContentColor = '#111',
    useFixedPosition = false,
    items,
    animationEase = 'back.out(1.5)',
    animationDuration = 0.5,
    staggerDelay = 0.12
}) {
    const [isMenuOpen, setIsMenuOpen] = useState(false);
    const [showOverlay, setShowOverlay] = useState(false);

    const overlayRef = useRef(null);
    const bubblesRef = useRef([]);
    const labelRefs = useRef([]);

    const menuItems = items?.length ? items : DEFAULT_ITEMS;

    const containerClassName = [
        'bubble-menu',
        useFixedPosition ? 'fixed' : 'absolute',
        'left-0 right-0 top-6',
        'flex items-center justify-between',
        'gap-4 px-6 md:px-12',
        'pointer-events-none',
        'z-[1001]',
        className
    ]
        .filter(Boolean)
        .join(' ');

    const handleToggle = () => {
        const nextState = !isMenuOpen;
        if (nextState) setShowOverlay(true);
        setIsMenuOpen(nextState);
        onMenuClick?.(nextState);
    };

    useEffect(() => {
        const overlay = overlayRef.current;
        const bubbles = bubblesRef.current.filter(Boolean);
        const labels = labelRefs.current.filter(Boolean);
        if (!overlay || !bubbles.length) return;

        if (isMenuOpen) {
            gsap.set(overlay, { display: 'flex' });
            gsap.killTweensOf([...bubbles, ...labels]);
            gsap.set(bubbles, { scale: 0, transformOrigin: '50% 50%' });
            gsap.set(labels, { y: 24, autoAlpha: 0 });

            bubbles.forEach((bubble, i) => {
                const delay = i * staggerDelay + gsap.utils.random(-0.05, 0.05);
                const tl = gsap.timeline({ delay });
                tl.to(bubble, {
                    scale: 1,
                    duration: animationDuration,
                    ease: animationEase
                });
                if (labels[i]) {
                    tl.to(
                        labels[i],
                        {
                            y: 0,
                            autoAlpha: 1,
                            duration: animationDuration,
                            ease: 'power3.out'
                        },
                        '-=' + animationDuration * 0.9
                    );
                }
            });
        } else if (showOverlay) {
            gsap.killTweensOf([...bubbles, ...labels]);
            gsap.to(labels, {
                y: 24,
                autoAlpha: 0,
                duration: 0.2,
                ease: 'power3.in'
            });
            gsap.to(bubbles, {
                scale: 0,
                duration: 0.2,
                ease: 'power3.in',
                onComplete: () => {
                    gsap.set(overlay, { display: 'none' });
                    setShowOverlay(false);
                }
            });
        }
    }, [isMenuOpen, showOverlay, animationEase, animationDuration, staggerDelay]);

    useEffect(() => {
        const handleResize = () => {
            if (isMenuOpen) {
                const bubbles = bubblesRef.current.filter(Boolean);
                const isDesktop = window.innerWidth >= 768;
                bubbles.forEach((bubble, i) => {
                    const item = menuItems[i];
                    if (bubble && item) {
                        const rotation = isDesktop ? (item.rotation ?? 0) : (item.rotation ? item.rotation / 2 : 0);
                        gsap.set(bubble, { rotation });
                    }
                });
            }
        };
        window.addEventListener('resize', handleResize);
        return () => window.removeEventListener('resize', handleResize);
    }, [isMenuOpen, menuItems]);

    return (
        <>
            <style>{`
        .bubble-menu .menu-line {
          transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
          transform-origin: center;
        }
        .bubble-menu-items .pill-list .pill-col:nth-child(4):nth-last-child(2) {
          margin-left: calc(100% / 6);
        }
        .bubble-menu-items .pill-list .pill-col:nth-child(4):last-child {
          margin-left: calc(100% / 3);
        }
        
        .bubble-menu-items .pill-link {
          transform: rotate(var(--item-rot));
        }
        .bubble-menu-items .pill-link:hover {
          transform: rotate(var(--item-rot)) scale(1.06);
          background: var(--hover-bg) !important;
          color: var(--hover-color) !important;
          box-shadow: 0 10px 30px -5px var(--hover-bg);
          z-index: 10;
        }
        .bubble-menu-items .pill-link:active {
          transform: rotate(var(--item-rot)) scale(.94);
        }

        @media (max-width: 767px) {
          .bubble-menu-items {
            padding-top: 80px;
          }
          .bubble-menu-items .pill-list {
            row-gap: 8px;
            justify-content: center;
          }
          .bubble-menu-items .pill-col {
            flex: 0 0 50% !important;
            margin-left: 0 !important;
            padding: 4px;
          }
          .bubble-menu-items .pill-link {
            min-height: 80px !important;
            padding: 1.5rem 0 !important;
            font-size: 1.25rem !important;
            border-radius: 20px !important;
          }
        }
      `}</style>

            <nav className={containerClassName} style={style} aria-label="Main navigation">
                {/* Logo Bubble */}
                <div
                    className={[
                        'bubble logo-bubble',
                        'inline-flex items-center justify-center',
                        'rounded-full',
                        'shadow-[0_8px_30px_rgb(0,0,0,0.12)]',
                        'pointer-events-auto border border-white/10',
                        'h-10 md:h-12',
                        'px-4 md:px-6',
                        'backdrop-blur-xl',
                        'will-change-transform'
                    ].join(' ')}
                    style={{
                        background: isMenuOpen ? 'transparent' : menuBg,
                        borderColor: isMenuOpen ? 'transparent' : 'rgba(255,255,255,0.1)'
                    }}
                >
                    <span className="logo-content inline-flex items-center justify-center">
                        {logo}
                    </span>
                </div>

                {/* Toggle Button Bubble */}
                <button
                    type="button"
                    className={[
                        'bubble toggle-bubble menu-btn group',
                        isMenuOpen ? 'open' : '',
                        'inline-flex flex-col items-center justify-center',
                        'rounded-full',
                        'shadow-[0_10px_40px_rgba(0,0,0,0.2)]',
                        'pointer-events-auto border-2',
                        'w-12 h-12 md:w-16 md:h-16',
                        'cursor-pointer p-0',
                        'backdrop-blur-xl transition-all duration-500 ease-[cubic-bezier(0.175,0.885,0.32,1.275)]',
                        isMenuOpen
                            ? 'bg-red-500 border-red-400 scale-110'
                            : 'hover:scale-110 active:scale-90 border-white/20'
                    ].join(' ')}
                    onClick={handleToggle}
                    aria-label={menuAriaLabel}
                    aria-pressed={isMenuOpen}
                    style={{ background: isMenuOpen ? '#ef4444' : menuBg }}
                >
                    <div className="relative w-8 h-8 flex flex-col items-center justify-center">
                        <span
                            className="menu-line block absolute rounded-full"
                            style={{
                                width: isMenuOpen ? 28 : 24,
                                height: 3,
                                background: isMenuOpen ? '#fff' : menuContentColor,
                                transform: isMenuOpen ? 'rotate(45deg)' : 'translateY(-6px)',
                                boxShadow: isMenuOpen ? '0 0 10px rgba(255,255,255,0.5)' : 'none'
                            }}
                        />
                        <span
                            className="menu-line block absolute rounded-full"
                            style={{
                                width: 24,
                                height: 3,
                                background: isMenuOpen ? '#fff' : menuContentColor,
                                transform: isMenuOpen ? 'scaleX(0)' : 'none',
                                opacity: isMenuOpen ? 0 : 1
                            }}
                        />
                        <span
                            className="menu-line block absolute rounded-full"
                            style={{
                                width: isMenuOpen ? 28 : 24,
                                height: 3,
                                background: isMenuOpen ? '#fff' : menuContentColor,
                                transform: isMenuOpen ? 'rotate(-45deg)' : 'translateY(6px)',
                                boxShadow: isMenuOpen ? '0 0 10px rgba(255,255,255,0.5)' : 'none'
                            }}
                        />
                    </div>
                </button>
            </nav>

            {showOverlay && (
                <div
                    ref={overlayRef}
                    className={[
                        'bubble-menu-items',
                        useFixedPosition ? 'fixed' : 'absolute',
                        'inset-0',
                        'flex items-center justify-center',
                        'pointer-events-none',
                        'z-[1000] backdrop-blur-md bg-black/20'
                    ].join(' ')}
                    aria-hidden={!isMenuOpen}
                >
                    <ul
                        className={[
                            'pill-list',
                            'list-none m-0 px-6',
                            'w-full max-w-[1200px] mx-auto',
                            'flex flex-wrap',
                            'pointer-events-auto'
                        ].join(' ')}
                        role="menu"
                        aria-label="Menu links"
                    >
                        {menuItems.map((item, idx) => (
                            <li
                                key={idx}
                                role="none"
                                className={[
                                    'pill-col',
                                    'flex justify-center items-stretch',
                                    'md:[flex:0_0_calc(100%/3)] [flex:0_0_50%]',
                                    'box-border'
                                ].join(' ')}
                            >
                                <a
                                    role="menuitem"
                                    href={item.href}
                                    onClick={() => setIsMenuOpen(false)}
                                    aria-label={item.ariaLabel || item.label}
                                    className={[
                                        'pill-link',
                                        'w-full',
                                        'rounded-[40px]',
                                        'no-underline',
                                        'shadow-[0_4px_14px_rgba(0,0,0,0.10)]',
                                        'flex items-center justify-center',
                                        'relative',
                                        'transition-all duration-300 ease-in-out',
                                        'box-border',
                                        'whitespace-nowrap overflow-hidden'
                                    ].join(' ')}
                                    style={{
                                        ['--item-rot']: `${item.rotation ?? 0}deg`,
                                        ['--pill-bg']: menuBg,
                                        ['--pill-color']: menuContentColor,
                                        ['--hover-bg']: item.hoverStyles?.bgColor || 'rgba(var(--primary-rgb), 1)',
                                        ['--hover-color']: item.hoverStyles?.textColor || '#fff',
                                        background: 'var(--pill-bg)',
                                        color: 'var(--pill-color)',
                                        minHeight: '120px',
                                        padding: '2rem 0',
                                        fontSize: '1.5rem',
                                        fontWeight: 900,
                                        textTransform: 'uppercase',
                                        letterSpacing: '-0.02em',
                                        willChange: 'transform'
                                    }}
                                    ref={el => {
                                        if (el) bubblesRef.current[idx] = el;
                                    }}
                                >
                                    <span
                                        className="pill-label inline-block"
                                        style={{
                                            willChange: 'transform, opacity',
                                            lineHeight: 1
                                        }}
                                        ref={el => {
                                            if (el) labelRefs.current[idx] = el;
                                        }}
                                    >
                                        {item.label}
                                    </span>
                                </a>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </>
    );
}
