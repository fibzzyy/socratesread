<style>
    @keyframes authFadeUp {
        from { opacity: 0; transform: translateY(12px); }
        to { opacity: 1; transform: translateY(0); }
    }

    .animate-auth-enter {
        animation: authFadeUp 0.35s cubic-bezier(0.16, 1, 0.3, 1) both;
    }

    /* Prevent outline issues */
    [data-slot="auth"] button:focus-visible,
    [data-slot="auth"] a:focus-visible,
    [data-slot="otp-dialog"] button:focus-visible,
    [data-slot="otp-dialog"] a:focus-visible {
        outline: none;
    }

    /* Split Container: Left Hero Visual (55%) + Right Form (45%) */
    .sr-auth-layout {
        position: relative;
        width: 100vw;
        min-height: 100vh;
        background: #ffffff;
        display: flex;
        flex-direction: column;
    }

    @media (min-width: 1024px) {
        .sr-auth-layout {
            flex-direction: row;
            height: 100vh;
            max-height: 100vh;
            overflow: hidden;
        }
    }

    /* HERO PANEL (KIRI) */
    .sr-hero-panel {
        display: none;
    }

    @media (min-width: 1024px) {
        .sr-hero-panel {
            display: flex;
            position: relative;
            width: 54%;
            height: 100vh;
            overflow: hidden;
            flex-direction: column;
            justify-content: space-between;
            padding: 56px 64px;
            background: #090d16;
        }
    }

    .sr-hero-image {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        object-position: center 30%;
        filter: grayscale(100%) contrast(110%) brightness(0.68);
        z-index: 1;
    }

    .sr-hero-overlay {
        position: absolute;
        inset: 0;
        z-index: 2;
        background:
            linear-gradient(180deg, rgba(10, 15, 29, 0.72) 0%, rgba(10, 15, 29, 0.45) 45%, rgba(10, 15, 29, 0.88) 100%),
            linear-gradient(90deg, rgba(10, 15, 29, 0.75) 0%, rgba(10, 15, 29, 0.3) 100%);
    }

    .sr-hero-content {
        position: relative;
        z-index: 10;
        height: 100%;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .sr-hero-badge {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 6px 14px;
        border-radius: 9999px;
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid rgba(255, 255, 255, 0.18);
        color: #e2e8f0;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: 0.04em;
        text-transform: uppercase;
        width: fit-content;
    }

    .sr-badge-dot {
        width: 7px;
        height: 7px;
        border-radius: 9999px;
        background-color: #60a5fa;
        box-shadow: 0 0 10px #60a5fa;
    }

    .sr-hero-title {
        font-family: 'Manrope', sans-serif;
        font-size: clamp(2.2rem, 3.4vw, 3.6rem);
        font-weight: 800;
        line-height: 1.1;
        letter-spacing: -0.03em;
        color: #ffffff;
        margin-bottom: 20px;
    }

    .sr-hero-title span {
        background: linear-gradient(135deg, #93c5fd 0%, #ffffff 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }

    .sr-hero-desc {
        color: #cbd5e1;
        font-size: clamp(0.95rem, 1.1vw, 1.05rem);
        line-height: 1.7;
        max-width: 480px;
    }

    .sr-quote-card {
        padding: 20px 24px;
        border-radius: 16px;
        background: rgba(255, 255, 255, 0.07);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(255, 255, 255, 0.12);
        max-width: 520px;
    }

    .sr-quote-text {
        color: #f1f5f9;
        font-size: 13.5px;
        font-style: italic;
        line-height: 1.6;
        margin: 0 0 6px 0;
    }

    .sr-quote-author {
        color: #94a3b8;
        font-size: 12px;
        font-weight: 600;
    }

    /* FORM PANEL (KANAN) */
    .sr-form-panel {
        position: relative;
        z-index: 20;
        width: 100%;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        justify-content: center;
        background: #ffffff;
        padding: 40px 24px;
    }

    @media (min-width: 640px) {
        .sr-form-panel {
            padding: 48px 56px;
        }
    }

    @media (min-width: 1024px) {
        .sr-form-panel {
            width: 46%;
            height: 100vh;
            max-height: 100vh;
            overflow-y: auto;
            padding: 48px 60px 48px 60px;
            border-left: 1px solid #f1f5f9;
        }
    }

    @media (min-width: 1280px) {
        .sr-form-panel {
            padding: 48px 80px 48px 80px;
        }
    }

    /* Interactive Inputs & Button Polish */
    .sr-input-field {
        transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .sr-input-field:hover {
        background-color: #ffffff;
        border-color: #cbd5e1;
    }
    .sr-input-field:focus {
        background-color: #ffffff;
        border-color: #2563eb;
        box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.12);
    }

    .sr-btn-primary {
        background: linear-gradient(180deg, #2563eb 0%, #1d4ed8 100%);
        box-shadow: 0 10px 24px -6px rgba(37, 99, 235, 0.38), 0 2px 6px -1px rgba(37, 99, 235, 0.2);
        transition: all 0.2s ease;
    }
    .sr-btn-primary:hover {
        background: linear-gradient(180deg, #1d4ed8 0%, #1e40af 100%);
        box-shadow: 0 14px 28px -6px rgba(37, 99, 235, 0.48), 0 4px 10px -2px rgba(37, 99, 235, 0.25);
        transform: translateY(-1px);
    }
    .sr-btn-primary:active {
        transform: translateY(0);
        box-shadow: 0 6px 14px -4px rgba(37, 99, 235, 0.3);
    }

    .sr-auth-wave .material-symbols-outlined {
        font-family: 'Material Symbols Outlined';
        font-size: inherit;
        font-weight: 400;
        font-style: normal;
        line-height: 1;
        letter-spacing: normal;
        text-transform: none;
        white-space: nowrap;
        direction: ltr;
        -webkit-font-feature-settings: 'liga';
        font-feature-settings: 'liga';
        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 20;
        -webkit-font-smoothing: antialiased;
    }

    .sr-auth-wave .sr-btn-primary {
        height: 44px;
        border-radius: 10px;
        background: #2457c5;
        box-shadow: none;
    }

    .sr-auth-wave .sr-btn-primary:hover {
        background: #1e4daf;
        box-shadow: 0 5px 14px rgba(30, 77, 175, .18);
        transform: none;
    }

    .sr-auth-wave .sr-btn-primary:active {
        background: #193f91;
        box-shadow: none;
    }

    /* Shared organic divider between the hero photo and auth form. */
        @media (min-width: 1024px) {
            .sr-auth-wave .sr-hero-panel {
                z-index: 1;
            }

            .sr-auth-wave .sr-form-panel {
                z-index: 1;
                border-left: 0;
            }

            .sr-auth-wave::before {
                content: '';
                position: absolute;
                z-index: 5;
                top: 0;
                left: calc(54% - 175px);
                width: 250px;
                height: 100%;
                background: #fff;
                pointer-events: none;
                                                clip-path: polygon(
                    40% 0%, 40.5% 0.25%, 40.9% 0.5%, 41.4% 0.75%, 41.9% 1%, 42.4% 1.25%, 42.8% 1.5%, 43.3% 1.75%, 43.8% 2%, 44.2% 2.25%, 44.7% 2.5%, 45.2% 2.75%,
                    45.6% 3%, 46.1% 3.25%, 46.5% 3.5%, 47% 3.75%, 47.5% 4%, 47.9% 4.25%, 48.4% 4.5%, 48.8% 4.75%, 49.3% 5%, 49.7% 5.25%, 50.2% 5.5%, 50.6% 5.75%,
                    51% 6%, 51.5% 6.25%, 51.9% 6.5%, 52.3% 6.75%, 52.8% 7%, 53.2% 7.25%, 53.6% 7.5%, 54% 7.75%, 54.5% 8%, 54.9% 8.25%, 55.3% 8.5%, 55.7% 8.75%,
                    56.1% 9%, 56.5% 9.25%, 56.9% 9.5%, 57.3% 9.75%, 57.6% 10%, 58% 10.25%, 58.4% 10.5%, 58.8% 10.75%, 59.1% 11%, 59.5% 11.25%, 59.8% 11.5%, 60.2% 11.75%,
                    60.5% 12%, 60.9% 12.25%, 61.2% 12.5%, 61.5% 12.75%, 61.9% 13%, 62.2% 13.25%, 62.5% 13.5%, 62.8% 13.75%, 63.1% 14%, 63.4% 14.25%, 63.7% 14.5%, 64% 14.75%,
                    64.3% 15%, 64.5% 15.25%, 64.8% 15.5%, 65.1% 15.75%, 65.3% 16%, 65.6% 16.25%, 65.8% 16.5%, 66.1% 16.75%, 66.3% 17%, 66.5% 17.25%, 66.7% 17.5%, 66.9% 17.75%,
                    67.1% 18%, 67.3% 18.25%, 67.5% 18.5%, 67.7% 18.75%, 67.9% 19%, 68.1% 19.25%, 68.2% 19.5%, 68.4% 19.75%, 68.5% 20%, 68.7% 20.25%, 68.8% 20.5%, 68.9% 20.75%,
                    69.1% 21%, 69.2% 21.25%, 69.3% 21.5%, 69.4% 21.75%, 69.5% 22%, 69.6% 22.25%, 69.6% 22.5%, 69.7% 22.75%, 69.8% 23%, 69.8% 23.25%, 69.9% 23.5%, 69.9% 23.75%,
                    69.9% 24%, 70% 24.25%, 70% 24.5%, 70% 24.75%, 70% 25%, 70% 25.25%, 70% 25.5%, 70% 25.75%, 69.9% 26%, 69.9% 26.25%, 69.9% 26.5%, 69.8% 26.75%,
                    69.8% 27%, 69.7% 27.25%, 69.6% 27.5%, 69.6% 27.75%, 69.5% 28%, 69.4% 28.25%, 69.3% 28.5%, 69.2% 28.75%, 69.1% 29%, 68.9% 29.25%, 68.8% 29.5%, 68.7% 29.75%,
                    68.5% 30%, 68.4% 30.25%, 68.2% 30.5%, 68.1% 30.75%, 67.9% 31%, 67.7% 31.25%, 67.5% 31.5%, 67.3% 31.75%, 67.1% 32%, 66.9% 32.25%, 66.7% 32.5%, 66.5% 32.75%,
                    66.3% 33%, 66.1% 33.25%, 65.8% 33.5%, 65.6% 33.75%, 65.3% 34%, 65.1% 34.25%, 64.8% 34.5%, 64.5% 34.75%, 64.3% 35%, 64% 35.25%, 63.7% 35.5%, 63.4% 35.75%,
                    63.1% 36%, 62.8% 36.25%, 62.5% 36.5%, 62.2% 36.75%, 61.9% 37%, 61.5% 37.25%, 61.2% 37.5%, 60.9% 37.75%, 60.5% 38%, 60.2% 38.25%, 59.8% 38.5%, 59.5% 38.75%,
                    59.1% 39%, 58.8% 39.25%, 58.4% 39.5%, 58% 39.75%, 57.6% 40%, 57.3% 40.25%, 56.9% 40.5%, 56.5% 40.75%, 56.1% 41%, 55.7% 41.25%, 55.3% 41.5%, 54.9% 41.75%,
                    54.5% 42%, 54% 42.25%, 53.6% 42.5%, 53.2% 42.75%, 52.8% 43%, 52.3% 43.25%, 51.9% 43.5%, 51.5% 43.75%, 51% 44%, 50.6% 44.25%, 50.2% 44.5%, 49.7% 44.75%,
                    49.3% 45%, 48.8% 45.25%, 48.4% 45.5%, 47.9% 45.75%, 47.5% 46%, 47% 46.25%, 46.5% 46.5%, 46.1% 46.75%, 45.6% 47%, 45.2% 47.25%, 44.7% 47.5%, 44.2% 47.75%,
                    43.8% 48%, 43.3% 48.25%, 42.8% 48.5%, 42.4% 48.75%, 41.9% 49%, 41.4% 49.25%, 40.9% 49.5%, 40.5% 49.75%, 40% 50%, 39.5% 50.25%, 39.1% 50.5%, 38.6% 50.75%,
                    38.1% 51%, 37.6% 51.25%, 37.2% 51.5%, 36.7% 51.75%, 36.2% 52%, 35.8% 52.25%, 35.3% 52.5%, 34.8% 52.75%, 34.4% 53%, 33.9% 53.25%, 33.5% 53.5%, 33% 53.75%,
                    32.5% 54%, 32.1% 54.25%, 31.6% 54.5%, 31.2% 54.75%, 30.7% 55%, 30.3% 55.25%, 29.8% 55.5%, 29.4% 55.75%, 29% 56%, 28.5% 56.25%, 28.1% 56.5%, 27.7% 56.75%,
                    27.2% 57%, 26.8% 57.25%, 26.4% 57.5%, 26% 57.75%, 25.5% 58%, 25.1% 58.25%, 24.7% 58.5%, 24.3% 58.75%, 23.9% 59%, 23.5% 59.25%, 23.1% 59.5%, 22.7% 59.75%,
                    22.4% 60%, 22% 60.25%, 21.6% 60.5%, 21.2% 60.75%, 20.9% 61%, 20.5% 61.25%, 20.2% 61.5%, 19.8% 61.75%, 19.5% 62%, 19.1% 62.25%, 18.8% 62.5%, 18.5% 62.75%,
                    18.1% 63%, 17.8% 63.25%, 17.5% 63.5%, 17.2% 63.75%, 16.9% 64%, 16.6% 64.25%, 16.3% 64.5%, 16% 64.75%, 15.7% 65%, 15.5% 65.25%, 15.2% 65.5%, 14.9% 65.75%,
                    14.7% 66%, 14.4% 66.25%, 14.2% 66.5%, 13.9% 66.75%, 13.7% 67%, 13.5% 67.25%, 13.3% 67.5%, 13.1% 67.75%, 12.9% 68%, 12.7% 68.25%, 12.5% 68.5%, 12.3% 68.75%,
                    12.1% 69%, 11.9% 69.25%, 11.8% 69.5%, 11.6% 69.75%, 11.5% 70%, 11.3% 70.25%, 11.2% 70.5%, 11.1% 70.75%, 10.9% 71%, 10.8% 71.25%, 10.7% 71.5%, 10.6% 71.75%,
                    10.5% 72%, 10.4% 72.25%, 10.4% 72.5%, 10.3% 72.75%, 10.2% 73%, 10.2% 73.25%, 10.1% 73.5%, 10.1% 73.75%, 10.1% 74%, 10% 74.25%, 10% 74.5%, 10% 74.75%,
                    10% 75%, 10% 75.25%, 10% 75.5%, 10% 75.75%, 10.1% 76%, 10.1% 76.25%, 10.1% 76.5%, 10.2% 76.75%, 10.2% 77%, 10.3% 77.25%, 10.4% 77.5%, 10.4% 77.75%,
                    10.5% 78%, 10.6% 78.25%, 10.7% 78.5%, 10.8% 78.75%, 10.9% 79%, 11.1% 79.25%, 11.2% 79.5%, 11.3% 79.75%, 11.5% 80%, 11.6% 80.25%, 11.8% 80.5%, 11.9% 80.75%,
                    12.1% 81%, 12.3% 81.25%, 12.5% 81.5%, 12.7% 81.75%, 12.9% 82%, 13.1% 82.25%, 13.3% 82.5%, 13.5% 82.75%, 13.7% 83%, 13.9% 83.25%, 14.2% 83.5%, 14.4% 83.75%,
                    14.7% 84%, 14.9% 84.25%, 15.2% 84.5%, 15.5% 84.75%, 15.7% 85%, 16% 85.25%, 16.3% 85.5%, 16.6% 85.75%, 16.9% 86%, 17.2% 86.25%, 17.5% 86.5%, 17.8% 86.75%,
                    18.1% 87%, 18.5% 87.25%, 18.8% 87.5%, 19.1% 87.75%, 19.5% 88%, 19.8% 88.25%, 20.2% 88.5%, 20.5% 88.75%, 20.9% 89%, 21.2% 89.25%, 21.6% 89.5%, 22% 89.75%,
                    22.4% 90%, 22.7% 90.25%, 23.1% 90.5%, 23.5% 90.75%, 23.9% 91%, 24.3% 91.25%, 24.7% 91.5%, 25.1% 91.75%, 25.5% 92%, 26% 92.25%, 26.4% 92.5%, 26.8% 92.75%,
                    27.2% 93%, 27.7% 93.25%, 28.1% 93.5%, 28.5% 93.75%, 29% 94%, 29.4% 94.25%, 29.8% 94.5%, 30.3% 94.75%, 30.7% 95%, 31.2% 95.25%, 31.6% 95.5%, 32.1% 95.75%,
                    32.5% 96%, 33% 96.25%, 33.5% 96.5%, 33.9% 96.75%, 34.4% 97%, 34.8% 97.25%, 35.3% 97.5%, 35.8% 97.75%, 36.2% 98%, 36.7% 98.25%, 37.2% 98.5%, 37.6% 98.75%,
                    38.1% 99%, 38.6% 99.25%, 39.1% 99.5%, 39.5% 99.75%, 40% 100%, 100% 100%, 100% 0%
                );
            }
        }

    @media (min-width: 1024px) {
        .sr-auth-wave .sr-hero-content {
            justify-content: center;
        }

        .sr-auth-wave .sr-hero-image {
            filter: grayscale(35%) contrast(102%) brightness(0.58);
        }

        .sr-auth-wave .sr-hero-overlay {
            background: linear-gradient(180deg, rgba(15, 23, 42, .48), rgba(15, 23, 42, .46) 48%, rgba(15, 23, 42, .82));
        }

        .sr-auth-wave .sr-hero-title {
            max-width: 620px;
            font-size: clamp(2rem, 3.1vw, 3.25rem);
            line-height: 1.12;
            letter-spacing: -.045em;
            margin-bottom: 16px;
        }

        .sr-auth-wave .sr-hero-title span {
            background: none;
            color: #bfdbfe;
            -webkit-text-fill-color: #bfdbfe;
        }

        .sr-auth-wave .sr-hero-desc {
            max-width: 440px;
            font-size: 1rem;
            line-height: 1.75;
        }
    }

</style>
