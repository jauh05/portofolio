import { useRef, useEffect, useState } from 'react';
import { gsap } from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import { useGSAP } from '@gsap/react';

// Note: Official GSAP SplitText is a paid plugin. 
// If you don't have Club GSAP, this import will fail.
// I'll use a standard implementation if possible, or try the requested import.
// For now, I will use a simple split logic since many users want the "SplitText" effect 
// without the paid plugin requirement if they are using the "Free" version of ReactBits patterns.

gsap.registerPlugin(ScrollTrigger, useGSAP);

const SplitText = ({
    text = '',
    className = '',
    delay = 50,
    duration = 0.05,
    ease = 'power3.out',
    splitType = 'chars', // 'chars', 'words', 'lines'
    from = { opacity: 0, y: 40 },
    to = { opacity: 1, y: 0 },
    threshold = 0.1,
    rootMargin = '-100px',
    textAlign = 'center',
    tag = 'p',
    onLetterAnimationComplete
}) => {
    const ref = useRef(null);
    const [fontsLoaded, setFontsLoaded] = useState(false);

    useEffect(() => {
        if (document.fonts.status === 'loaded') {
            setFontsLoaded(true);
        } else {
            document.fonts.ready.then(() => {
                setFontsLoaded(true);
            });
        }
    }, []);

    useGSAP(
        () => {
            if (!ref.current || !text || !fontsLoaded) return;

            const el = ref.current;
            const elements = el.querySelectorAll('.split-item');

            gsap.fromTo(
                elements,
                { ...from },
                {
                    ...to,
                    duration: duration,
                    ease: ease,
                    stagger: delay / 1000,
                    scrollTrigger: {
                        trigger: el,
                        start: `top ${100 - threshold * 100}%`,
                        once: true,
                    },
                    onComplete: () => {
                        if (onLetterAnimationComplete) onLetterAnimationComplete();
                    },
                }
            );
        },
        { dependencies: [text, fontsLoaded, delay, duration, ease], scope: ref }
    );

    const renderContent = () => {
        if (splitType === 'chars') {
            return text.split('').map((char, i) => (
                <span
                    key={i}
                    className="split-item inline-block"
                    style={{ whiteSpace: char === ' ' ? 'pre' : 'normal' }}
                >
                    {char}
                </span>
            ));
        }
        if (splitType === 'words') {
            return text.split(' ').map((word, i) => (
                <span key={i} className="split-item inline-block mr-[0.2em]">
                    {word}
                </span>
            ));
        }
        return text;
    };

    const Tag = tag;

    return (
        <Tag
            ref={ref}
            className={`inline-block ${className}`}
            style={{ textAlign, willChange: 'transform, opacity' }}
        >
            {renderContent()}
        </Tag>
    );
};

export default SplitText;
