import React, { useEffect, useRef, useState } from 'react';

/**
 * AuroraBackground (Inspired by 21st.dev & ReactBits.dev)
 * Fluid celestial aurora borealis effect with interactive pointer parallax
 * and randomized twinkling starfield.
 */
export default function AuroraBackground({ children, className = '' }) {
    const containerRef = useRef(null);
    const [mousePos, setMousePos] = useState({ x: 0.5, y: 0.5 });
    const [stars, setStars] = useState([]);

    // Generate responsive randomized starfield on mount
    useEffect(() => {
        const starCount = window.innerWidth < 640 ? 35 : 70;
        const generatedStars = Array.from({ length: starCount }).map((_, i) => ({
            id: i,
            x: Math.random() * 100,
            y: Math.random() * 100,
            size: Math.random() * 2 + 1,
            opacity: Math.random() * 0.7 + 0.3,
            duration: Math.random() * 3 + 2,
            delay: Math.random() * 4,
        }));
        setStars(generatedStars);
    }, []);

    const handlePointerMove = (e) => {
        if (!containerRef.current) return;
        const rect = containerRef.current.getBoundingClientRect();
        const x = (e.clientX - rect.left) / rect.width;
        const y = (e.clientY - rect.top) / rect.height;
        setMousePos({
            x: Math.max(0, Math.min(1, x)),
            y: Math.max(0, Math.min(1, y)),
        });
    };

    return (
        <div
            ref={containerRef}
            onPointerMove={handlePointerMove}
            className={`relative w-full h-full overflow-hidden pointer-events-none ${className}`}
            style={{
                '--mouse-x': `${mousePos.x * 100}%`,
                '--mouse-y': `${mousePos.y * 100}%`,
            }}
        >
            {/* Ambient Dark Deep Space Base */}
            <div className="absolute inset-0 bg-[#060A17] opacity-90 transition-opacity" />

            {/* Dynamic Aurora Ribbons (Layer 1: Deep Sapphire / Indigo Glow) */}
            <div
                className="absolute inset-0 transition-transform duration-700 ease-out will-change-transform"
                style={{
                    transform: `translate3d(${(mousePos.x - 0.5) * 28}px, ${(mousePos.y - 0.5) * 28}px, 0)`,
                    background: `
                        radial-gradient(circle at var(--mouse-x, 50%) var(--mouse-y, 40%), rgba(59, 130, 246, 0.28) 0%, transparent 60%),
                        radial-gradient(ellipse 80% 50% at 50% -10%, rgba(99, 102, 241, 0.35) 0%, transparent 70%),
                        radial-gradient(ellipse 60% 40% at 85% 60%, rgba(139, 92, 246, 0.22) 0%, transparent 60%),
                        radial-gradient(ellipse 50% 50% at 15% 75%, rgba(14, 165, 233, 0.20) 0%, transparent 60%)
                    `,
                    filter: 'blur(45px)',
                }}
            />

            {/* Dynamic Aurora Ribbons (Layer 2: Luminous Cyan & Violet Ethereal Swirls) */}
            <div
                className="absolute inset-0 mix-blend-screen opacity-70 animate-pulse pointer-events-none"
                style={{
                    animationDuration: '6s',
                    background: `
                        radial-gradient(circle at 30% 25%, rgba(6, 182, 212, 0.18) 0%, transparent 50%),
                        radial-gradient(circle at 70% 70%, rgba(168, 85, 247, 0.22) 0%, transparent 50%)
                    `,
                    filter: 'blur(55px)',
                }}
            />

            {/* Twinkling Starfield */}
            <div className="absolute inset-0 pointer-events-none" aria-hidden="true">
                {stars.map((star) => (
                    <div
                        key={star.id}
                        className="absolute rounded-full bg-white will-change-opacity"
                        style={{
                            left: `${star.x}%`,
                            top: `${star.y}%`,
                            width: `${star.size}px`,
                            height: `${star.size}px`,
                            opacity: star.opacity,
                            boxShadow: star.size > 1.8 ? '0 0 6px rgba(255, 255, 255, 0.9)' : 'none',
                            animation: `auroraStarTwinkle ${star.duration}s ease-in-out ${star.delay}s infinite alternate`,
                        }}
                    />
                ))}
            </div>

            {/* Inner Content Slot */}
            {children}
        </div>
    );
}
