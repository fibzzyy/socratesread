import React from 'react';
import { createRoot } from 'react-dom/client';
import AuroraBackground from './components/AuroraBackground';
import HeroBadge from './components/HeroBadge';
import BorderBeam from './components/BorderBeam';
import ParadigmaComparison from './components/ParadigmaComparison';

/**
 * SocratesRead React Islands Mounting Point
 * Inspired by 21st.dev and ReactBits.dev
 * Selectively mounts React micro-components to target DOM containers in Blade.
 */
export function initReactIslands() {
    // 1. Opening Intro Overlay: Aurora Background & Celestial Particle Mesh
    const openingAuroraEl = document.getElementById('react-opening-aurora');
    if (openingAuroraEl && !openingAuroraEl.dataset.reactMounted) {
        openingAuroraEl.dataset.reactMounted = 'true';
        const root = createRoot(openingAuroraEl);
        root.render(<AuroraBackground className="w-full h-full" />);
    }

    // 2. Hero Section: Shimmering Badge with ShinyText (ReactBits)
    const heroBadgeEl = document.getElementById('react-hero-badge');
    if (heroBadgeEl && !heroBadgeEl.dataset.reactMounted) {
        heroBadgeEl.dataset.reactMounted = 'true';
        const root = createRoot(heroBadgeEl);
        root.render(<HeroBadge />);
    }

    // 3. Hero CTA: BorderBeam Light Tracer (21st.dev)
    const heroBeamEl = document.getElementById('react-hero-cta-beam');
    if (heroBeamEl && !heroBeamEl.dataset.reactMounted) {
        heroBeamEl.dataset.reactMounted = 'true';
        const root = createRoot(heroBeamEl);
        root.render(
            <BorderBeam
                size={110}
                duration={5}
                colorFrom="#60a5fa"
                colorTo="#a855f7"
            />
        );
    }

    // 4. Paradigma Belajar Section: SpotlightCards Side-by-Side (21st.dev / ReactBits)
    const spotlightCardsEl = document.getElementById('react-spotlight-cards');
    if (spotlightCardsEl && !spotlightCardsEl.dataset.reactMounted) {
        spotlightCardsEl.dataset.reactMounted = 'true';
        const root = createRoot(spotlightCardsEl);
        root.render(<ParadigmaComparison />);
    }
}

// Automatically initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initReactIslands);
} else {
    initReactIslands();
}
