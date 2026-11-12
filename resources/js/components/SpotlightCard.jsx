import React, { useRef, useState } from 'react';

/**
 * SpotlightCard (Inspired by 21st.dev & ReactBits.dev)
 * Interactive card with mouse-driven radial spotlight and illuminated borders.
 */
export default function SpotlightCard({
    children,
    className = '',
    spotlightColor = 'rgba(59, 130, 246, 0.22)',
    borderColor = 'rgba(59, 130, 246, 0.45)',
    size = 350,
}) {
    const cardRef = useRef(null);
    const [position, setPosition] = useState({ x: 0, y: 0 });
    const [opacity, setOpacity] = useState(0);

    const handleMouseMove = (e) => {
        if (!cardRef.current) return;
        const rect = cardRef.current.getBoundingClientRect();
        setPosition({
            x: e.clientX - rect.left,
            y: e.clientY - rect.top,
        });
        setOpacity(1);
    };

    const handleMouseLeave = () => {
        setOpacity(0);
    };

    return (
        <div
            ref={cardRef}
            onMouseMove={handleMouseMove}
            onMouseLeave={handleMouseLeave}
            className={`relative overflow-hidden rounded-2xl transition-all duration-300 ${className}`}
        >
            {/* Spotlight Border Illumination */}
            <div
                className="pointer-events-none absolute -inset-px rounded-[inherit] transition-opacity duration-300"
                style={{
                    opacity,
                    background: `radial-gradient(${size}px circle at ${position.x}px ${position.y}px, ${borderColor}, transparent 70%)`,
                }}
            />

            {/* Spotlight Radial Glow on Card Surface */}
            <div
                className="pointer-events-none absolute inset-0 rounded-[inherit] transition-opacity duration-300"
                style={{
                    opacity,
                    background: `radial-gradient(${size}px circle at ${position.x}px ${position.y}px, ${spotlightColor}, transparent 80%)`,
                }}
            />

            {/* Inner Content Slot */}
            <div className="relative z-10 h-full flex flex-col justify-between">
                {children}
            </div>
        </div>
    );
}
