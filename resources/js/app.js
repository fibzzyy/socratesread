import './bootstrap';
import './reading-coach';
import './landing';
import './react-mount';
import { drawQrCode } from './qr-code';

document.querySelectorAll('[data-class-qr-code]').forEach((canvas) => {
    const details = canvas.closest('details');
    const status = details?.querySelector('[data-qr-status]');

    const render = () => {
        if (!details?.open || canvas.dataset.rendered === 'true') return;

        try {
            const target = new URL(canvas.dataset.joinUrl, window.location.origin);
            drawQrCode(canvas, target.href);
            canvas.dataset.rendered = 'true';
        } catch {
            if (status) status.textContent = 'Tautan kelas terlalu panjang untuk dibuat menjadi QR.';
        }
    };

    details?.addEventListener('toggle', render);
});

document.querySelectorAll('[data-open-qr-presentation]').forEach((button) => {
    const presentation = document.getElementById(button.getAttribute('aria-controls'));
    const canvas = presentation?.querySelector('[data-presentation-qr]');
    const closeButton = presentation?.querySelector('[data-close-qr-presentation]');
    const fullscreenButton = presentation?.querySelector('[data-qr-fullscreen]');

    button.addEventListener('click', () => {
        if (!presentation || !canvas) return;

        try {
            const target = new URL(button.dataset.joinUrl, window.location.origin);
            drawQrCode(canvas, target.href, 680);
            presentation.querySelector('[data-presentation-title]').textContent = button.dataset.className;
            presentation.querySelector('[data-presentation-grade]').textContent = button.dataset.grade;
            presentation.querySelector('[data-presentation-code]').textContent = `Kode kelas ${button.dataset.joinCode}`;
            presentation.showModal();
        } catch {
            const status = button.closest('details')?.querySelector('[data-qr-status]');
            if (status) status.textContent = 'QR kelas tidak dapat ditampilkan.';
        }
    });

    closeButton?.addEventListener('click', () => presentation.close());
    fullscreenButton?.addEventListener('click', async () => {
        try {
            if (!document.fullscreenElement && presentation.requestFullscreen) {
                await presentation.requestFullscreen();
            }
        } catch {
            // The large dialog remains visible if browser fullscreen is unavailable.
        }
    });
    presentation?.addEventListener('close', () => {
        if (document.fullscreenElement === presentation) document.exitFullscreen?.();
    });
});
