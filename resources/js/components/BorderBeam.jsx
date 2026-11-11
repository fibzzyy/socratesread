import React from 'react';

/**
 * BorderBeam (Inspired by 21st.dev)
 * A luminous light ray traveling smoothly along the outer boundary of an element.
 */
export default function BorderBeam({
    className = '',
    size = 120,
    duration = 6,
    delay = 0,
    colorFrom = '#3b82f6',
    colorTo = '#8b5cf6',
}) {
    return (
        <div
            style={{
                '--size': `${size}px`,
                '--duration': `${duration}s`,
                '--delay': `-${delay}s`,
                '--color-from': colorFrom,
                '--color-to': colorTo,
            }}
            className={`pointer-events-none absolute inset-0 rounded-[inherit] [border:calc(var(--border-width,1.5px))_solid_transparent] ![mask-clip:padding-box,border-box] ![mask-composite:intersect] [mask:linear-gradient(transparent,transparent),linear-gradient(white,white)] after:absolute after:aspect-square after:w-[var(--size)] after:animate-border-beam after:[animation-delay:var(--delay)] after:[background:linear-gradient(to_left,var(--color-from),var(--color-to),transparent)] after:[offset-anchor:calc(var(--size)/2)_50%] after:[offset-path:rect(0_auto_auto_0_round_calc(var(--size)))] ${className}`}
        />
    );
}
