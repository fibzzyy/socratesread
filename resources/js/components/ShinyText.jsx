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
            className={`inline-block text-transparent bg-clip-text ${disabled ? '' : 'animate-shiny-text'} ${className}`}
            style={{
                backgroundImage:
                    'linear-gradient(120deg, rgba(255, 255, 255, 0.45) 0%, rgba(255, 255, 255, 0.95) 50%, rgba(255, 255, 255, 0.45) 100%)',
                backgroundSize: '200% 100%',
                WebkitBackgroundClip: 'text',
                animationDuration: animationDuration,
            }}
        >
            {text}
        </span>
    );
}
