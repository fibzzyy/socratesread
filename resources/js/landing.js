/**
 * SocratesRead: Landing Page Interactions
 * 3D Book Opening, Athena Owl Mascot, Audio Synthesis, and Socratic Sandbox
 */

document.addEventListener('DOMContentLoaded', () => {
    const landingContainer = document.getElementById('socratesread-landing');
    if (!landingContainer) return;

    // =========================================================================
    // 1. Web Audio Synthesizer (Zero external dependencies)
    // =========================================================================
    let audioCtx = null;
    let isSoundEnabled = localStorage.getItem('socratesread_sound') === 'true';

    function initAudio() {
        if (!audioCtx) {
            const AudioContextClass = window.AudioContext || window.webkitAudioContext;
            if (AudioContextClass) {
                audioCtx = new AudioContextClass();
            }
        }
        if (audioCtx && audioCtx.state === 'suspended') {
            audioCtx.resume();
        }
    }

    // Synthesize cascading multi-page flutter (Blender style) & gentle wing flap
    function playBookOpenSound() {
        if (!isSoundEnabled) return;
        try {
            initAudio();
            if (!audioCtx) return;

            const now = audioCtx.currentTime;
            
            // Staggered paper rustles for cascading leaves
            for (let burst = 0; burst < 5; burst++) {
                const burstTime = now + (burst * 0.1);
                const bufferSize = audioCtx.sampleRate * 0.14;
                const buffer = audioCtx.createBuffer(1, bufferSize, audioCtx.sampleRate);
                const data = buffer.getChannelData(0);
                for (let i = 0; i < bufferSize; i++) {
                    data[i] = Math.random() * 2 - 1;
                }

                const noise = audioCtx.createBufferSource();
                noise.buffer = buffer;

                const filter = audioCtx.createBiquadFilter();
                filter.type = 'bandpass';
                filter.frequency.setValueAtTime(450 + (burst * 60), burstTime);
                filter.Q.value = 1.2;

                const gain = audioCtx.createGain();
                gain.gain.setValueAtTime(0.01, burstTime);
                gain.gain.linearRampToValueAtTime(0.12, burstTime + 0.04);
                gain.gain.exponentialRampToValueAtTime(0.001, burstTime + 0.13);

                noise.connect(filter);
                filter.connect(gain);
                gain.connect(audioCtx.destination);

                noise.start(burstTime);
                noise.stop(burstTime + 0.14);
            }

            // Gentle low wing flap whoosh during takeoff
            const osc = audioCtx.createOscillator();
            const oscGain = audioCtx.createGain();
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(140, now + 0.35);
            osc.frequency.exponentialRampToValueAtTime(70, now + 0.65);

            oscGain.gain.setValueAtTime(0.001, now + 0.35);
            oscGain.gain.linearRampToValueAtTime(0.08, now + 0.45);
            oscGain.gain.exponentialRampToValueAtTime(0.001, now + 0.65);

            osc.connect(oscGain);
            oscGain.connect(audioCtx.destination);
            osc.start(now + 0.35);
            osc.stop(now + 0.68);
        } catch (e) {
            // Audio context failed gracefully
        }
    }

    // Synthesize gentle celestial chime for Athena wisdom
    function playAthenaChime() {
        if (!isSoundEnabled) return;
        try {
            initAudio();
            if (!audioCtx) return;

            const now = audioCtx.currentTime;
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();

            osc.type = 'sine';
            osc.frequency.setValueAtTime(523.25, now); // C5
            osc.frequency.exponentialRampToValueAtTime(659.25, now + 0.12); // E5
            osc.frequency.exponentialRampToValueAtTime(783.99, now + 0.28); // G5

            gain.gain.setValueAtTime(0.01, now);
            gain.gain.linearRampToValueAtTime(0.08, now + 0.05);
            gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.6);

            osc.connect(gain);
            gain.connect(audioCtx.destination);

            osc.start(now);
            osc.stop(now + 0.65);
        } catch (e) {
            // Audio context failed gracefully
        }
    }

    // Synthesize book closing sound (solid cover thump & paper compression)
    function playBookCloseSound() {
        if (!isSoundEnabled) return;
        try {
            initAudio();
            if (!audioCtx) return;

            const now = audioCtx.currentTime;

            // Low thud of heavy leather book cover closing
            const osc = audioCtx.createOscillator();
            const oscGain = audioCtx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(110, now);
            osc.frequency.exponentialRampToValueAtTime(35, now + 0.18);

            oscGain.gain.setValueAtTime(0.18, now);
            oscGain.gain.exponentialRampToValueAtTime(0.001, now + 0.2);

            osc.connect(oscGain);
            oscGain.connect(audioCtx.destination);
            osc.start(now);
            osc.stop(now + 0.2);

            // Paper compression rustle
            const bufferSize = audioCtx.sampleRate * 0.12;
            const buffer = audioCtx.createBuffer(1, bufferSize, audioCtx.sampleRate);
            const data = buffer.getChannelData(0);
            for (let i = 0; i < bufferSize; i++) {
                data[i] = Math.random() * 2 - 1;
            }
            const noise = audioCtx.createBufferSource();
            noise.buffer = buffer;
            const filter = audioCtx.createBiquadFilter();
            filter.type = 'lowpass';
            filter.frequency.setValueAtTime(600, now);
            filter.frequency.linearRampToValueAtTime(200, now + 0.12);

            const noiseGain = audioCtx.createGain();
            noiseGain.gain.setValueAtTime(0.08, now);
            noiseGain.gain.exponentialRampToValueAtTime(0.001, now + 0.12);

            noise.connect(filter);
            filter.connect(noiseGain);
            noiseGain.connect(audioCtx.destination);
            noise.start(now);
            noise.stop(now + 0.12);
        } catch (e) {
            // gracefully ignored
        }
    }

    // Synthesize crisp paper-turn sound for flipping pages
    function playPageTurnSound() {
        if (!isSoundEnabled) return;
        try {
            initAudio();
            if (!audioCtx) return;

            const now = audioCtx.currentTime;
            const bufferSize = audioCtx.sampleRate * 0.18;
            const buffer = audioCtx.createBuffer(1, bufferSize, audioCtx.sampleRate);
            const data = buffer.getChannelData(0);
            for (let i = 0; i < bufferSize; i++) {
                data[i] = Math.random() * 2 - 1;
            }

            const noise = audioCtx.createBufferSource();
            noise.buffer = buffer;

            const filter = audioCtx.createBiquadFilter();
            filter.type = 'bandpass';
            filter.frequency.setValueAtTime(1100, now);
            filter.frequency.exponentialRampToValueAtTime(550, now + 0.16);
            filter.Q.value = 1.4;

            const gain = audioCtx.createGain();
            gain.gain.setValueAtTime(0.01, now);
            gain.gain.linearRampToValueAtTime(0.08, now + 0.04);
            gain.gain.exponentialRampToValueAtTime(0.001, now + 0.18);

            noise.connect(filter);
            filter.connect(gain);
            gain.connect(audioCtx.destination);

            noise.start(now);
            noise.stop(now + 0.18);
        } catch (e) {
            // gracefully ignored
        }
    }

    // Synthesize authentic Athena Hinggap (landing / perching) sound: wing flutter airbrake + golden chime & soft wood tap
    function playAthenaHinggapSound() {
        if (!isSoundEnabled) return;
        try {
            initAudio();
            if (!audioCtx) return;

            const now = audioCtx.currentTime;

            // 1. Feather flare breeze (filtered white noise rush)
            const bufferSize = Math.floor(audioCtx.sampleRate * 0.28);
            const buffer = audioCtx.createBuffer(1, bufferSize, audioCtx.sampleRate);
            const data = buffer.getChannelData(0);
            for (let i = 0; i < bufferSize; i++) {
                data[i] = Math.random() * 2 - 1;
            }
            const noise = audioCtx.createBufferSource();
            noise.buffer = buffer;

            const filter = audioCtx.createBiquadFilter();
            filter.type = 'bandpass';
            filter.frequency.setValueAtTime(800, now);
            filter.frequency.exponentialRampToValueAtTime(300, now + 0.25);
            filter.Q.value = 1.2;

            const noiseGain = audioCtx.createGain();
            noiseGain.gain.setValueAtTime(0.01, now);
            noiseGain.gain.linearRampToValueAtTime(0.06, now + 0.08);
            noiseGain.gain.exponentialRampToValueAtTime(0.001, now + 0.28);

            noise.connect(filter);
            filter.connect(noiseGain);
            noiseGain.connect(audioCtx.destination);
            noise.start(now);
            noise.stop(now + 0.28);

            // 2. Soft wood perch tap & celestial shimmer at moment of touchdown (+0.25s)
            setTimeout(() => {
                try {
                    if (!audioCtx) return;
                    const touchTime = audioCtx.currentTime;
                    
                    // Soft wood tap
                    const tapOsc = audioCtx.createOscillator();
                    const tapGain = audioCtx.createGain();
                    tapOsc.type = 'triangle';
                    tapOsc.frequency.setValueAtTime(170, touchTime);
                    tapOsc.frequency.exponentialRampToValueAtTime(55, touchTime + 0.09);

                    tapGain.gain.setValueAtTime(0.12, touchTime);
                    tapGain.gain.exponentialRampToValueAtTime(0.001, touchTime + 0.09);

                    tapOsc.connect(tapGain);
                    tapGain.connect(audioCtx.destination);
                    tapOsc.start(touchTime);
                    tapOsc.stop(touchTime + 0.09);

                    // Golden chime
                    playAthenaChime();
                } catch (e) {}
            }, 240);
        } catch (e) {
            // gracefully ignored
        }
    }

    // Audio toggle button handler (ON / OFF toggle, non-circular)
    const audioToggleBtns = document.querySelectorAll('[data-audio-toggle]');
    function updateAudioButtons() {
        audioToggleBtns.forEach(btn => {
            const iconSound = btn.querySelector('.icon-sound-on');
            const iconMute = btn.querySelector('.icon-sound-off');
            const textSpan = btn.querySelector('.sound-label');
            const isNavbarBtn = btn.classList.contains('sound-toggle-nav') || btn.closest('header');

            if (isSoundEnabled) {
                if (iconSound) iconSound.classList.remove('hidden');
                if (iconMute) iconMute.classList.add('hidden');
                if (textSpan) textSpan.innerHTML = '<span class="hidden sm:inline">Suara: </span>ON';
                btn.setAttribute('aria-pressed', 'true');
                if (isNavbarBtn) {
                    btn.classList.add('bg-blue-50/70', 'border-blue-300', 'text-blue-700');
                    btn.classList.remove('bg-white', 'bg-slate-100', 'border-slate-200', 'text-slate-700');
                } else {
                    btn.classList.remove('bg-white', 'bg-blue-50', 'border', 'border-blue-300', 'border-slate-200');
                    btn.classList.add('bg-transparent', 'text-blue-400');
                }
            } else {
                if (iconSound) iconSound.classList.add('hidden');
                if (iconMute) iconMute.classList.remove('hidden');
                if (textSpan) textSpan.innerHTML = '<span class="hidden sm:inline">Suara: </span>OFF';
                btn.setAttribute('aria-pressed', 'false');
                if (isNavbarBtn) {
                    btn.classList.remove('bg-white', 'bg-blue-50', 'bg-blue-50/70', 'border-blue-300', 'text-blue-700');
                    btn.classList.add('bg-slate-100', 'border-slate-200', 'text-slate-700');
                } else {
                    btn.classList.remove('bg-white', 'bg-blue-50', 'border', 'border-blue-300', 'border-slate-200');
                    btn.classList.add('bg-transparent', 'text-slate-400');
                }
            }
        });
    }

    audioToggleBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            isSoundEnabled = !isSoundEnabled;
            localStorage.setItem('socratesread_sound', isSoundEnabled ? 'true' : 'false');
            updateAudioButtons();
            if (isSoundEnabled) {
                playAthenaChime();
            }
        });
    });
    updateAudioButtons();

    // =========================================================================
    // 1B. Dynamic Random Twinkling Stars Generator for Intro Celestial Sky (Optimized)
    // =========================================================================
    function initTwinklingStars() {
        const starsCanvas = document.getElementById('stars-canvas');
        if (!starsCanvas) return;
        starsCanvas.innerHTML = '';

        const isMobile = window.innerWidth < 640;
        const starCount = isMobile ? 12 : 22;
        const colors = ['#ffffff', '#fef08a', '#bae6fd', '#fde047', '#e0e7ff'];
        const fragment = document.createDocumentFragment();

        for (let i = 0; i < starCount; i++) {
            const star = document.createElement('div');
            star.className = 'star-particle';

            const size = (Math.random() * 2 + 1.2).toFixed(1);
            const x = (Math.random() * 94 + 3).toFixed(1);
            const y = (Math.random() * 92 + 4).toFixed(1);
            const dur = (Math.random() * 2.5 + 2).toFixed(1);
            const del = (Math.random() * 3).toFixed(1);
            const minOp = (Math.random() * 0.15 + 0.15).toFixed(2);
            const maxOp = (Math.random() * 0.35 + 0.65).toFixed(2);
            const color = colors[Math.floor(Math.random() * colors.length)];

            star.style.width = `${size}px`;
            star.style.height = `${size}px`;
            star.style.left = `${x}%`;
            star.style.top = `${y}%`;
            star.style.backgroundColor = color;
            star.style.setProperty('--twinkle-dur', `${dur}s`);
            star.style.setProperty('--twinkle-del', `${del}s`);
            star.style.setProperty('--min-opacity', minOp);
            star.style.setProperty('--max-opacity', maxOp);

            fragment.appendChild(star);
        }
        starsCanvas.appendChild(fragment);
    }
    initTwinklingStars();

    // =========================================================================
    // 2. Interactive 3D Book Controller (2-Page Spread, ReactBits Tilt & Athena Mascot)
    // =========================================================================
    const introOverlay = document.getElementById('book-intro-overlay');
    const bookStage = document.getElementById('book-stage');
    const openBookBtn = document.getElementById('btn-open-book');
    const openControlsBar = document.getElementById('intro-controls-open');
    const closeBookBtn = document.getElementById('btn-close-book');
    const coverCloseBtns = document.querySelectorAll('.btn-cover-close');
    const sheetFinishBtn = document.querySelector('.btn-sheet-finish');
    const getStartedBtn = document.getElementById('btn-get-started');
    const skipIntroBtn = document.getElementById('btn-skip-intro');
    const reopenBookBtns = document.querySelectorAll('[data-reopen-book]');
    const introOwlPopup = document.getElementById('intro-owl-popup');
    const introOwlBubble = document.getElementById('intro-owl-bubble');

    // Hero section elements hoisted for reliable cross-function access
    const athenaHeroMascot = document.getElementById('athena-hero-mascot');
    const speechBubble = document.getElementById('athena-speech-bubble');
    const quoteText = document.getElementById('athena-quote-text');
    const quoteAuthor = document.getElementById('athena-quote-author');

    let isBookOpen = false;

    function updateOwlSpeech(text) {
        if (!introOwlBubble) return;
        introOwlBubble.style.opacity = '0';
        introOwlBubble.style.transform = 'translateY(4px)';
        setTimeout(() => {
            introOwlBubble.textContent = text;
            introOwlBubble.style.opacity = '1';
            introOwlBubble.style.transform = 'translateY(0)';
        }, 160);
    }

    function openBookSequence() {
        if (!bookStage || !introOverlay || isBookOpen) return;
        isBookOpen = true;
        playBookOpenSound();

        // Athena resets any previous drag state and takes flight into view
        if (introOwlPopup) {
            introOwlPopup.classList.remove('is-swooping', 'is-perched-on-book', 'athena-intro-hinggap', 'is-custom-placed', 'is-dragging');
            introOwlPopup.style.left = '';
            introOwlPopup.style.right = '';
            introOwlPopup.style.top = '';
            introOwlPopup.style.transform = '';
        }

        bookStage.classList.add('is-open');
        introOverlay.classList.add('is-book-open');

        // Play gentle chime when Athena ascends to flight
        setTimeout(() => {
            playAthenaChime();
        }, 500);

        // Switch bottom controls to interactive open bar
        setTimeout(() => {
            if (openBookBtn) openBookBtn.classList.add('is-hidden');
            if (openControlsBar) openControlsBar.classList.remove('is-hidden');
        }, 400);

        updateOwlSpeech('"Bagus! Simak dialog di sisi kiri dan inisiasi di sisi kanan." ✨');
    }

    function closeBookSequence() {
        if (!bookStage || !introOverlay || !isBookOpen) return;
        isBookOpen = false;
        playBookCloseSound();
        bookStage.classList.remove('is-open');
        introOverlay.classList.remove('is-book-open');
        bookStage.style.transform = '';
        const book3d = document.getElementById('book-3d');
        if (book3d) {
            book3d.style.transform = '';
        }

        // Athena disappears smoothly when cover closes
        if (introOwlPopup) {
            introOwlPopup.classList.remove('is-swooping', 'is-perched-on-book', 'athena-intro-hinggap', 'is-custom-placed', 'is-dragging');
            introOwlPopup.style.left = '';
            introOwlPopup.style.right = '';
            introOwlPopup.style.top = '';
            introOwlPopup.style.transform = '';
        }

        if (openControlsBar) openControlsBar.classList.add('is-hidden');
        if (openBookBtn) openBookBtn.classList.remove('is-hidden');
    }

    function proceedToLanding() {
        if (!introOverlay || !bookStage) return;

        // 1. Play book close audio thump
        playBookCloseSound();

        // 2. Play book closing animation (pages and front cover swing shut)
        bookStage.classList.add('is-closing');
        bookStage.classList.remove('is-open');
        introOverlay.classList.remove('is-book-open');

        // Reset stage transform back to centered closed position
        bookStage.style.transform = '';
        const book3d = document.getElementById('book-3d');
        if (book3d) {
            book3d.style.transform = '';
        }

        // Hide interactive open bar during close
        if (openControlsBar) {
            openControlsBar.classList.add('is-hidden');
        }

        // 3. Athena swoops down towards the landing page
        if (introOwlPopup) {
            introOwlPopup.classList.add('is-swooping');
        }

        // 4. After the book finishes closing shut (750ms), transition out the overlay
        setTimeout(() => {
            introOverlay.classList.add('is-hidden');
            document.body.classList.remove('overflow-hidden');
            try { localStorage.setItem('socratesread_intro_seen', 'true'); } catch (e) {}
            sessionStorage.setItem('socratesread_intro_seen', 'true');

            // Detach overlay from render tree after fade transition to save GPU/CPU cycles
            setTimeout(() => {
                if (introOverlay.classList.contains('is-hidden')) {
                    introOverlay.style.display = 'none';
                }
            }, 600);

            if (introOwlPopup) {
                introOwlPopup.classList.remove('is-swooping', 'is-perched-on-book', 'athena-intro-hinggap');
            }

            bookStage.classList.remove('is-closing');

            // 5. Athena mascot on landing page performs the full hinggap (landing & perching) animation
            if (athenaHeroMascot) {
                athenaHeroMascot.classList.remove('athena-hero-arrival', 'athena-hero-hinggap');
                void athenaHeroMascot.offsetWidth; // trigger reflow
                athenaHeroMascot.classList.add('athena-hero-hinggap');
            }

            if (speechBubble) {
                speechBubble.classList.remove('athena-hero-greet');
                void speechBubble.offsetWidth; // trigger reflow
                speechBubble.classList.add('athena-hero-greet');

                if (quoteText) {
                    quoteText.textContent = '"Selamat datang di SocratesRead! Aku Athena, siap menemanimu menguji nalar lewat dialog kritis."';
                }
                if (quoteAuthor) {
                    quoteAuthor.textContent = 'Athena · Panduan Belajar Sokratik';
                }
            }

            // Play authentic landing sound (feather flutter airbrake + golden chime & wood tap)
            playAthenaHinggapSound();

            const mainHeading = document.getElementById('hero-main-title');
            if (mainHeading) {
                mainHeading.setAttribute('tabindex', '-1');
                mainHeading.focus({ preventScroll: true });
            }
        }, 750);
    }

    function skipIntroImmediately() {
        if (!introOverlay) return;
        if (bookStage) bookStage.classList.add('is-open');
        introOverlay.classList.add('is-hidden');
        document.body.classList.remove('overflow-hidden');
        try { localStorage.setItem('socratesread_intro_seen', 'true'); } catch (e) {}
        sessionStorage.setItem('socratesread_intro_seen', 'true');

        setTimeout(() => {
            if (introOverlay.classList.contains('is-hidden')) {
                introOverlay.style.display = 'none';
            }
        }, 600);
    }

    function reopenBook() {
        if (!introOverlay || !bookStage) return;
        isBookOpen = false;
        if (introOwlPopup) {
            introOwlPopup.classList.remove('is-swooping', 'is-perched-on-book', 'athena-intro-hinggap', 'is-custom-placed', 'is-dragging');
            introOwlPopup.style.left = '';
            introOwlPopup.style.right = '';
            introOwlPopup.style.top = '';
            introOwlPopup.style.transform = '';
        }
        bookStage.classList.remove('is-closing', 'is-open');
        introOverlay.classList.remove('is-book-open');
        bookStage.style.transform = '';
        const book3d = document.getElementById('book-3d');
        if (book3d) {
            book3d.style.transform = '';
        }
        if (openControlsBar) openControlsBar.classList.add('is-hidden');
        if (openBookBtn) openBookBtn.classList.remove('is-hidden');

        // Restore display and trigger smooth fade-in
        introOverlay.style.display = 'flex';
        void introOverlay.offsetWidth;
        introOverlay.classList.remove('is-hidden');
        document.body.classList.add('overflow-hidden');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Book click to open
    if (bookStage) {
        bookStage.addEventListener('click', (e) => {
            // Only open if currently closed and click wasn't on a button
            if (!isBookOpen && !e.target.closest('button')) {
                openBookSequence();
            }
        });
    }

    if (openBookBtn) {
        openBookBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            openBookSequence();
        });
    }

    if (closeBookBtn) {
        closeBookBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            closeBookSequence();
        });
    }

    coverCloseBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.stopPropagation();
            closeBookSequence();
        });
    });

    if (sheetFinishBtn) {
        sheetFinishBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            proceedToLanding();
        });
    }

    if (getStartedBtn) {
        getStartedBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            proceedToLanding();
        });
    }

    if (skipIntroBtn) {
        skipIntroBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            skipIntroImmediately();
        });
    }

    reopenBookBtns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            reopenBook();
        });
    });

    // 3D Card Tilt Parallax (Desktop fine-pointer only, throttled via RAF)
    if (introOverlay && bookStage) {
        const book3d = document.getElementById('book-3d');
        const isFinePointer = window.matchMedia && window.matchMedia('(pointer: fine)').matches;
        let tiltTicking = false;
        let cachedRect = null;

        const updateCachedRect = () => {
            if (bookStage && !introOverlay.classList.contains('is-hidden')) {
                cachedRect = bookStage.getBoundingClientRect();
            }
        };

        if (isFinePointer) {
            introOverlay.addEventListener('mouseenter', updateCachedRect, { passive: true });
            window.addEventListener('resize', updateCachedRect, { passive: true });

            introOverlay.addEventListener('mousemove', (e) => {
                if (!isBookOpen || window.innerWidth < 1024 || !book3d) return;
                if (!cachedRect) updateCachedRect();
                if (!cachedRect) return;

                if (!tiltTicking) {
                    tiltTicking = true;
                    const clientX = e.clientX;
                    const clientY = e.clientY;
                    window.requestAnimationFrame(() => {
                        if (cachedRect) {
                            const x = (clientX - cachedRect.left) / cachedRect.width - 0.5;
                            const y = (clientY - cachedRect.top) / cachedRect.height - 0.5;
                            const tiltY = Math.max(-4, Math.min(4, x * 6));
                            const tiltX = Math.max(-3, Math.min(3, -y * 4));
                            book3d.style.transform = `rotateX(${6 + tiltX}deg) rotateY(${tiltY}deg)`;
                        }
                        tiltTicking = false;
                    });
                }
            }, { passive: true });

            introOverlay.addEventListener('mouseleave', () => {
                if (isBookOpen && book3d) {
                    book3d.style.transform = 'rotateX(6deg) rotateY(0deg)';
                }
            });
        }
    }

    // Keyboard support for book intro
    window.addEventListener('keydown', (e) => {
        if (!introOverlay || introOverlay.classList.contains('is-hidden')) return;

        if (e.key === ' ' || e.key === 'Enter') {
            if (!isBookOpen) {
                e.preventDefault();
                openBookSequence();
            }
        } else if (e.key === 'Escape' || e.key === 'c' || e.key === 'C') {
            e.preventDefault();
            if (isBookOpen) {
                closeBookSequence();
            } else {
                skipIntroImmediately();
            }
        }
    });

    // =========================================================================
    // 2B. Draggable Interactive Athena Mascot Controller (Pointer Events, Zero-Reflow RAF)
    // =========================================================================
    if (introOwlPopup) {
        let isDraggingOwl = false;
        let dragStartX = 0;
        let dragStartY = 0;
        let elemInitialX = 0;
        let elemInitialY = 0;
        let lastDragX = 0;
        let currentTilt = 0;
        let cachedOwlWidth = 0;
        let cachedOwlHeight = 0;
        let dragRafId = null;
        let pendingClientX = 0;
        let pendingClientY = 0;
        let hasDraggedFar = false;
        let activePointerId = null;

        function getOwlBaseScale() {
            if (window.innerWidth < 640) return 0.58;
            if (window.innerWidth < 1024) return 0.70;
            return 0.88;
        }

        function applyOwlDragCoordinates(clientX, clientY) {
            const baseScale = getOwlBaseScale();
            const deltaX = clientX - dragStartX;
            const deltaY = clientY - dragStartY;

            if (Math.hypot(deltaX, deltaY) > 5) {
                hasDraggedFar = true;
            }

            let newX = elemInitialX + deltaX;
            let newY = elemInitialY + deltaY;

            // Clamping inside viewport bounds using cached dimensions (zero reflow!)
            const padX = Math.max(50, (cachedOwlWidth * baseScale) / 3);
            const padY = Math.max(40, (cachedOwlHeight * baseScale) / 3);
            newX = Math.max(padX, Math.min(window.innerWidth - padX, newX));
            newY = Math.max(padY, Math.min(window.innerHeight - padY, newY));

            // Dynamic banking tilt based on motion velocity
            const moveDeltaX = clientX - lastDragX;
            lastDragX = clientX;
            currentTilt = Math.max(-10, Math.min(10, moveDeltaX * 1.2));

            introOwlPopup.style.left = `${newX}px`;
            introOwlPopup.style.top = `${newY}px`;
            introOwlPopup.style.transform = `translate(-50%, -50%) scale(${baseScale * 1.04}) rotate(${currentTilt}deg)`;
        }

        function startOwlDrag(clientX, clientY) {
            if (!introOwlPopup) return;

            // In case a previous drag or frame was still pending:
            if (dragRafId) {
                window.cancelAnimationFrame(dragRafId);
                dragRafId = null;
            }

            isDraggingOwl = true;
            hasDraggedFar = false;
            const baseScale = getOwlBaseScale();
            introOwlPopup.style.setProperty('--owl-scale', baseScale);

            // Calculate current visual center and cache dimensions once at drag start
            const rect = introOwlPopup.getBoundingClientRect();
            elemInitialX = rect.left + rect.width / 2;
            elemInitialY = rect.top + rect.height / 2;
            cachedOwlWidth = rect.width;
            cachedOwlHeight = rect.height;

            dragStartX = clientX;
            dragStartY = clientY;
            lastDragX = clientX;
            pendingClientX = clientX;
            pendingClientY = clientY;
            currentTilt = 0;

            // Anchor element cleanly before first move to prevent any jumping
            introOwlPopup.style.right = 'auto';
            introOwlPopup.style.bottom = 'auto';
            introOwlPopup.style.left = `${elemInitialX}px`;
            introOwlPopup.style.top = `${elemInitialY}px`;
            introOwlPopup.style.transform = `translate(-50%, -50%) scale(${baseScale * 1.04}) rotate(0deg)`;

            introOwlPopup.classList.add('is-dragging');
            introOwlPopup.classList.remove('is-custom-placed');

            updateOwlSpeech('"Wuuush! Aku terbang bersamamu! ✨"');
            playAthenaChime();
        }

        function renderOwlDrag() {
            dragRafId = null;
            if (!isDraggingOwl || !introOwlPopup) return;
            applyOwlDragCoordinates(pendingClientX, pendingClientY);
        }

        function moveOwlDrag(clientX, clientY) {
            if (!isDraggingOwl || !introOwlPopup) return;
            pendingClientX = clientX;
            pendingClientY = clientY;

            if (!dragRafId) {
                dragRafId = window.requestAnimationFrame(renderOwlDrag);
            }
        }

        function endOwlDrag() {
            // Cancel any queued animation frame
            if (dragRafId) {
                window.cancelAnimationFrame(dragRafId);
                dragRafId = null;
            }

            // Immediately unbind global window listeners
            window.removeEventListener('pointermove', onGlobalPointerMove);
            window.removeEventListener('pointerup', onGlobalPointerUp);
            window.removeEventListener('pointercancel', onGlobalPointerCancel);

            // Safely release pointer capture
            if (activePointerId !== null && introOwlPopup) {
                try {
                    if (introOwlPopup.hasPointerCapture && introOwlPopup.hasPointerCapture(activePointerId)) {
                        introOwlPopup.releasePointerCapture(activePointerId);
                    }
                } catch (err) {}
                activePointerId = null;
            }

            if (!isDraggingOwl || !introOwlPopup) {
                isDraggingOwl = false;
                return;
            }

            // If a fast drag had pending coordinates not yet painted by RAF, flush them immediately
            if (pendingClientX && pendingClientY) {
                applyOwlDragCoordinates(pendingClientX, pendingClientY);
            }

            isDraggingOwl = false;
            const baseScale = getOwlBaseScale();
            introOwlPopup.classList.remove('is-dragging');
            introOwlPopup.classList.add('is-custom-placed');
            introOwlPopup.style.setProperty('--owl-scale', baseScale);
            introOwlPopup.style.transform = `translate(-50%, -50%) scale(${baseScale}) rotate(0deg)`;

            if (hasDraggedFar) {
                updateOwlSpeech('"Tempat yang nyaman! Ayo lanjutkan membaca 📖"');
            }
        }

        function onGlobalPointerMove(e) {
            if (isDraggingOwl) {
                if (e.cancelable) {
                    e.preventDefault();
                }
                moveOwlDrag(e.clientX, e.clientY);
            }
        }

        function onGlobalPointerUp(e) {
            if (isDraggingOwl) {
                endOwlDrag();
            }
        }

        function onGlobalPointerCancel(e) {
            if (isDraggingOwl) {
                endOwlDrag();
            }
        }

        // Pointer Events (supports mouse, stylus, touch unified)
        introOwlPopup.addEventListener('pointerdown', (e) => {
            // Ignore drag if clicking links/buttons or if book is closed
            if (!isBookOpen || e.target.closest('button, a')) return;
            e.preventDefault();
            e.stopPropagation();

            if (isDraggingOwl) {
                endOwlDrag();
            }

            activePointerId = e.pointerId;
            try {
                introOwlPopup.setPointerCapture(e.pointerId);
            } catch (err) {}

            startOwlDrag(e.clientX, e.clientY);

            // Bind global move, up, and cancel listeners with passive: false to prevent touch gesture conflicts
            window.addEventListener('pointermove', onGlobalPointerMove, { passive: false });
            window.addEventListener('pointerup', onGlobalPointerUp, { passive: false });
            window.addEventListener('pointercancel', onGlobalPointerCancel, { passive: false });
        });

        introOwlPopup.addEventListener('pointerup', () => {
            if (isDraggingOwl) {
                endOwlDrag();
            }
        });

        introOwlPopup.addEventListener('pointercancel', () => {
            if (isDraggingOwl) {
                endOwlDrag();
            }
        });

        introOwlPopup.addEventListener('lostpointercapture', () => {
            if (isDraggingOwl) {
                endOwlDrag();
            }
        });

        // Suppress click event if user was dragging
        introOwlPopup.addEventListener('click', (e) => {
            if (hasDraggedFar) {
                e.preventDefault();
                e.stopPropagation();
            }
        }, true);
    }

    // Check if user already saw the intro (persisted in localStorage & sessionStorage)
    const hasSeenIntro = (() => {
        try {
            return localStorage.getItem('socratesread_intro_seen') === 'true' || sessionStorage.getItem('socratesread_intro_seen') === 'true';
        } catch (e) {
            return false;
        }
    })();

    if (hasSeenIntro) {
        if (introOverlay) {
            introOverlay.classList.add('is-hidden');
            introOverlay.style.display = 'none';
        }
    } else {
        if (introOverlay) {
            document.body.classList.add('overflow-hidden');
        }
    }

    // =========================================================================
    // 3. Athena Owl Mascot Controller (Pupils cursor tracking & Socratic quotes)
    // =========================================================================
    const athenaIntroPopup = document.getElementById('intro-owl-popup');
    const nextWisdomBtn = document.getElementById('btn-next-wisdom');

    // Hero Owl eye elements
    const heroPupilLeft = document.getElementById('hero-owl-pupil-left');
    const heroPupilRight = document.getElementById('hero-owl-pupil-right');
    const heroOwlHead = document.getElementById('hero-owl-head-group');

    // Intro Owl eye elements
    const introPupilLeft = document.getElementById('intro-owl-pupil-left');
    const introPupilRight = document.getElementById('intro-owl-pupil-right');
    const introOwlHead = document.getElementById('intro-owl-head-group');

    // Socratic quotes collection
    const socraticWisdomList = [
        {
            quote: 'Pikiran yang terlatih mampu menimbang suatu gagasan tanpa harus langsung menelannya mentah-mentah.',
            author: 'Aristoteles, Etika Nikomakea'
        },
        {
            quote: 'Hidup yang tidak pernah diuji dan dipertanyakan adalah hidup yang tidak bernilai untuk dijalani.',
            author: 'Socrates, Apologia'
        },
        {
            quote: 'Saat menjumpai paragraf yang sulit, tanyakan: Premis apa yang sebenarnya sedang dibangun penulis?',
            author: 'Athena, Panduan Membaca Kritis'
        },
        {
            quote: 'Dalam tradisi Sokratik, aporia (kebingungan) bukanlah kegagalan, melainkan pintu gerbang kebijaksanaan.',
            author: 'Dialog Plato, Meno'
        },
        {
            quote: 'Jangan terburu-buru membantah sebelum kamu mampu merumuskan kembali argumen lawan dengan adil.',
            author: 'Athena, Etika Penalaran'
        },
        {
            quote: 'Membaca kritis bukan mencari kutipan untuk membenarkan prasangka, melainkan menguji kebenaran isi teks.',
            author: 'SocratesRead Pedagogy'
        }
    ];

    let currentWisdomIndex = 0;

    function displayNextWisdom() {
        currentWisdomIndex = (currentWisdomIndex + 1) % socraticWisdomList.length;
        const wisdom = socraticWisdomList[currentWisdomIndex];

        if (speechBubble) {
            speechBubble.style.opacity = '0';
            speechBubble.style.transform = 'translateY(6px)';

            setTimeout(() => {
                if (quoteText) quoteText.textContent = `"${wisdom.quote}"`;
                if (quoteAuthor) quoteAuthor.textContent = wisdom.author;
                speechBubble.style.opacity = '1';
                speechBubble.style.transform = 'translateY(0)';
                playAthenaChime();
            }, 200);
        }
    }

    if (athenaHeroMascot) {
        athenaHeroMascot.addEventListener('click', () => {
            displayNextWisdom();
        });
    }

    if (athenaIntroPopup) {
        athenaIntroPopup.addEventListener('click', () => {
            playAthenaChime();
        });
    }

    if (nextWisdomBtn) {
        nextWisdomBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            displayNextWisdom();
        });
    }

    // Smooth Pupil and Head Tracking
    let mouseX = window.innerWidth / 2;
    let mouseY = window.innerHeight / 2;
    let isTrackingTicking = false;

    window.addEventListener('mousemove', (e) => {
        mouseX = e.clientX;
        mouseY = e.clientY;

        if (!isTrackingTicking) {
            window.requestAnimationFrame(() => {
                updateOwlEyes(mouseX, mouseY);
                isTrackingTicking = false;
            });
            isTrackingTicking = true;
        }
    }, { passive: true });

    function trackPupilsForMascot(mascotEl, pupilLeftEl, pupilRightEl, headGroupEl, x, y) {
        if (!mascotEl || !pupilLeftEl || !pupilRightEl) return;
        const rect = mascotEl.getBoundingClientRect();
        if (rect.bottom < 0 || rect.top > window.innerHeight) return;

        const centerX = rect.left + rect.width / 2;
        const centerY = rect.top + rect.height * 0.38;

        const deltaX = x - centerX;
        const deltaY = y - centerY;
        const distance = Math.hypot(deltaX, deltaY);

        const maxOffset = 6.5;
        const clampedDist = Math.min(distance / 25, maxOffset);
        const angle = Math.atan2(deltaY, deltaX);

        const offsetX = Math.cos(angle) * clampedDist;
        const offsetY = Math.sin(angle) * clampedDist;

        pupilLeftEl.style.transform = `translate(${offsetX}px, ${offsetY}px)`;
        pupilRightEl.style.transform = `translate(${offsetX}px, ${offsetY}px)`;

        if (headGroupEl) {
            const tilt = Math.max(Math.min((deltaX / window.innerWidth) * 7, 5), -5);
            headGroupEl.style.transform = `rotate(${tilt}deg)`;
            headGroupEl.style.transformOrigin = '150px 140px';
        }
    }

    function updateOwlEyes(x, y) {
        const isIntroActive = introOverlay && !introOverlay.classList.contains('is-hidden') && introOverlay.style.display !== 'none';
        if (isIntroActive) {
            trackPupilsForMascot(athenaIntroPopup, introPupilLeft, introPupilRight, introOwlHead, x, y);
        } else {
            trackPupilsForMascot(athenaHeroMascot, heroPupilLeft, heroPupilRight, heroOwlHead, x, y);
        }
    }

    // =========================================================================
    // 4. Interactive Spotlight Cards (ReactBits pattern)
    // =========================================================================
    const spotlightCards = document.querySelectorAll('.spotlight-card');
    spotlightCards.forEach(card => {
        card.addEventListener('mousemove', (e) => {
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            card.style.setProperty('--mouse-x', `${x}px`);
            card.style.setProperty('--mouse-y', `${y}px`);
        });
    });

    // =========================================================================
    // 5. Interactive Socratic Sandbox
    // =========================================================================
    const sandboxOptions = document.querySelectorAll('[data-socratic-option]');
    const sandboxFeedback = document.getElementById('sandbox-feedback-box');
    const feedbackTitle = document.getElementById('sandbox-feedback-title');
    const feedbackText = document.getElementById('sandbox-feedback-text');
    const feedbackQuestion = document.getElementById('sandbox-feedback-question');

    const feedbackScenarios = {
        '1': {
            type: 'dogmatic',
            title: 'Tinjauan Kritis: Pendekatan Hafalan Pasif',
            text: 'Kamu memilih menyimpulkan teks berdasarkan apa yang lazim dikatakan tanpa menguji bukti dalam teks. Dalam berpikir kritis, ini disebut argumentum ad verecundiam (menerima otoritas tanpa audit bukti).',
            question: 'Pertanyaan Athena: "Jika premis awal yang kamu percaya ternyata bersumber dari kutipan yang keliru, bagaimana kamu mempertahankan kesimpulanmu?"'
        },
        '2': {
            type: 'skeptic',
            title: 'Tinjauan Kritis: Skeptisisme Tanpa Konstruksi',
            text: 'Kamu meragukan seluruh klaim teks secara total. Keraguan memang titik mula berpikir, namun skeptisisme yang menolak semua data tanpa pembanding rasional justru menghentikan proses penyelidikan ilmiah.',
            question: 'Pertanyaan Athena: "Bila setiap bukti kamu anggap bias, standar bukti seperti apa yang secara adil bersedia kamu terima sebagai kebenaran?"'
        },
        '3': {
            type: 'socratic',
            title: 'Luar Biasa: Pendekatan Dialektika Sokratik!',
            text: 'Tepat sekali! Kamu mengidentifikasi hubungan logis antara premis penulis, menguji konsistensi asumsi yang tidak tertulis, dan mencari bukti pembantah sebelum menarik inferensi objektif.',
            question: 'Pertanyaan Athena: "Bagaimana jika argumen tandingan ini diajukan dalam konteks abad ke-21? Nilai etis apa yang paling diuji di sini?"'
        }
    };

    sandboxOptions.forEach(btn => {
        btn.addEventListener('click', () => {
            sandboxOptions.forEach(b => b.classList.remove('is-active'));
            btn.classList.add('is-active');

            const optionId = btn.getAttribute('data-socratic-option');
            const data = feedbackScenarios[optionId];

            if (data && sandboxFeedback) {
                sandboxFeedback.classList.remove('hidden');
                sandboxFeedback.style.opacity = '0';
                sandboxFeedback.style.transform = 'translateY(8px)';

                setTimeout(() => {
                    if (feedbackTitle) feedbackTitle.textContent = data.title;
                    if (feedbackText) feedbackText.textContent = data.text;
                    if (feedbackQuestion) feedbackQuestion.textContent = data.question;

                    sandboxFeedback.style.transition = 'all 0.35s ease-out';
                    sandboxFeedback.style.opacity = '1';
                    sandboxFeedback.style.transform = 'translateY(0)';
                    playAthenaChime();
                }, 100);
            }
        });
    });

    // =========================================================================
    // 6. Mobile Navigation Menu Toggle
    // =========================================================================
    const mobileMenuBtn = document.getElementById('landing-mobile-menu-btn');
    const mobileNavDrawer = document.getElementById('landing-mobile-nav');

    if (mobileMenuBtn && mobileNavDrawer) {
        mobileMenuBtn.addEventListener('click', () => {
            const isExpanded = mobileMenuBtn.getAttribute('aria-expanded') === 'true';
            mobileMenuBtn.setAttribute('aria-expanded', !isExpanded);
            mobileNavDrawer.classList.toggle('hidden');
        });

        // Close when clicking nav link inside
        mobileNavDrawer.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', () => {
                mobileNavDrawer.classList.add('hidden');
                mobileMenuBtn.setAttribute('aria-expanded', 'false');
            });
        });
    }
});
