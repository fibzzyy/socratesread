import React, { useEffect, useRef } from 'react';

/**
 * AuroraBackground (Optimized Lightweight Edition - Luminous Twilight Sky)
 * Hardware-accelerated celestial background using smooth multi-stop CSS gradients.
 * Brighter atmospheric twilight palette so Athena the owl stands out clearly.
 * Zero heavy blur filters, zero per-frame React state re-renders.
 */
export default function AuroraBackground({ children, className = '' }) {
    const containerRef = useRef(null);
    const ribbonRef = useRef(null);
    const tickingRef = useRef(false);

    useEffect(() => {
        // Only attach pointer tracking for fine-pointer desktop devices
        const isFinePointer = window.matchMedia && window.matchMedia('(pointer: fine)').matches;
        if (!isFinePointer) return;

        const handlePointerMove = (e) => {
            if (!containerRef.current || !ribbonRef.current) return;
            const clientX = e.clientX;
            const clientY = e.clientY;

            if (!tickingRef.current) {
                tickingRef.current = true;
                window.requestAnimationFrame(() => {
                    if (containerRef.current && ribbonRef.current) {
                        const rect = containerRef.current.getBoundingClientRect();
                        const x = (clientX - rect.left) / (rect.width || 1);
                        const y = (clientY - rect.top) / (rect.height || 1);
                        const moveX = ((x - 0.5) * 16).toFixed(1);
                        const moveY = ((y - 0.5) * 16).toFixed(1);
                        ribbonRef.current.style.transform = `translate3d(${moveX}px, ${moveY}px, 0)`;
                    }
                    tickingRef.current = false;
                });
            }
        };

        window.addEventListener('pointermove', handlePointerMove, { passive: true });
        return () => {
            window.removeEventListener('pointermove', handlePointerMove);
        };
    }, []);

    return (
        <div
            ref={containerRef}
            className={`relative w-full h-full overflow-hidden pointer-events-none ${className}`}
        >
            {/* Luminous Deep Twilight Atmosphere (Not pitch black, high contrast for mascot) */}
            <div
                className="absolute inset-0"
                style={{
                    background: 'radial-gradient(ellipse 110% 85% at 50% 18%, #1e40af 0%, #1e3a8a 35%, #172554 70%, #0f172a 100%)',
                }}
            />

            {/* Dynamic Aurora Ribbons (Smooth multi-stop gradients with soft cyan, sky blue, and violet glow) */}
            <div
                ref={ribbonRef}
                className="absolute inset-0 transition-transform duration-500 ease-out will-change-transform pointer-events-none"
                style={{
                    background: `
                        radial-gradient(ellipse 85% 65% at 50% 20%, rgba(147, 197, 253, 0.32) 0%, rgba(59, 130, 246, 0.18) 35%, transparent 70%),
                        radial-gradient(ellipse 75% 55% at 85% 65%, rgba(192, 132, 252, 0.24) 0%, rgba(139, 92, 246, 0.10) 40%, transparent 65%),
                        radial-gradient(ellipse 70% 55% at 15% 70%, rgba(56, 189, 248, 0.22) 0%, rgba(14, 165, 233, 0.08) 40%, transparent 65%)
                    `,
                }}
            />

            {/* Subtle Ethereal Highlight Swirl with gentle warm starlight accents */}
            <div
                className="absolute inset-0 opacity-70 pointer-events-none"
                style={{
                    background: `
                        radial-gradient(circle at 50% 12%, rgba(254, 240, 138, 0.20) 0%, rgba(251, 191, 36, 0.08) 35%, transparent 55%),
                        radial-gradient(circle at 30% 30%, rgba(125, 211, 252, 0.16) 0%, transparent 50%),
                        radial-gradient(circle at 70% 45%, rgba(216, 180, 254, 0.16) 0%, transparent 50%)
                    `,
                }}
            />

            {/* Inner Content Slot */}
            {children}
        </div>
    );
}
