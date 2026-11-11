import React from 'react';
import ShinyText from './ShinyText';

/**
 * HeroBadge (Inspired by ReactBits.dev & 21st.dev)
 * Shimmering pedagogical badge with live pulse indicator and shiny text sweep.
 */
export default function HeroBadge() {
    return (
        <div className="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-slate-900/90 border border-blue-500/30 text-blue-300 text-xs font-semibold shadow-md shadow-blue-950/20 backdrop-blur-md">
            <span className="relative flex h-2 w-2">
                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-blue-400 opacity-75"></span>
                <span className="relative inline-flex rounded-full h-2 w-2 bg-blue-500"></span>
            </span>
            <ShinyText
                text="Metode Dialektika Sokratik untuk SMA & SMK"
                speed={3.5}
                className="font-medium tracking-wide text-xs"
            />
        </div>
    );
}
