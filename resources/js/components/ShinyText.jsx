import React from 'react';

/**
 * ShinyText (Inspired by ReactBits.dev)
 * Shimmering dynamic light sweep effect across typographic characters.
 */
export default function ShinyText({
    text,
    disabled = false,
    speed = 3,
    className = '',
}) {
    const animationDuration = `${speed}s`;

    return (
        <span
            className={`inline-block bg-clip-text text-transparent ${disabled ? '' : 'animate-shiny-text'} ${className}`}
            style={{
                backgroundImage:
                    'linear-gradient(120deg, #1d4ed8 0%, #3b82f6 50%, #1d4ed8 100%)',
                backgroundSize: '200% 100%',
                WebkitBackgroundClip: 'text',
                WebkitTextFillColor: 'transparent',
                animationDuration: animationDuration,
            }}
        >
            {text}
        </span>
    );
}
