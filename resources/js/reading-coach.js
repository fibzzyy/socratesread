const panel = document.querySelector('[data-reading-coach]');

if (panel) {
    const form = panel.querySelector('[data-coach-form]');
    const input = panel.querySelector('[data-coach-input]');
    const send = panel.querySelector('[data-coach-send]');
    const sendLabel = panel.querySelector('[data-coach-send-label]');
    const transcript = panel.querySelector('[data-coach-transcript]');
    const error = panel.querySelector('[data-coach-error]');
    const count = panel.querySelector('[data-coach-count]');
    const prompts = [...panel.querySelectorAll('[data-coach-prompt]')];
    const maxLength = Number(panel.dataset.maxLength);
    let history = [];
    try {
        const initial = JSON.parse(panel.dataset.initialHistory || '[]');
        if (Array.isArray(initial)) {
            history = initial
                .filter((m) => m && (m.role === 'user' || m.role === 'assistant') && typeof m.content === 'string')
                .slice(-6);
        }
    } catch (_) {}

    const formatParagraphPills = (text) => {
        return text.replace(/(?:\[?¶(\d+)\]?|\bparagraf\s+\[?¶?(\d+)\]?)/gi, (_, p1, p2) => {
            const pNum = p1 || p2;
            return `<button type="button" class="coach-para-pill" data-jump-para="${pNum}" title="Sorot & lompat ke Paragraf ${pNum}"><span>¶${pNum}</span> <svg style="width:10px;height:10px;display:inline" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M5 12h14M12 5l7 7-7 7"/></svg></button>`;
        });
    };

    const highlightAndScrollToParagraph = (pIndex) => {
        const pNum = parseInt(pIndex, 10);
        if (isNaN(pNum)) return;

        const readingControl = window.SocratesReading;
        const currentMode = readingControl ? readingControl.getMode() : (localStorage.getItem('SR_READING_MODE') || 'scroll');

        // A. Mode Halaman (Kertas Buku)
        if (currentMode === 'book' && readingControl) {
            const targetPageIdx = readingControl.findPageForParagraph(pNum);
            readingControl.setPage(targetPageIdx, true);

            setTimeout(() => {
                const bookSheet = document.querySelector('[data-book-sheet]');
                if (!bookSheet) return;

                document.querySelectorAll('.passage-para-block.is-targeted').forEach((el) => {
                    el.classList.remove('is-targeted');
                });

                const target = bookSheet.querySelector(`#para-${pNum}`) || bookSheet.querySelector(`[data-paragraph-index="${pNum}"]`);
                if (target) {
                    target.classList.add('is-targeted');

                    const panel = document.querySelector('[data-passage-panel]');
                    if (panel) {
                        const panelRect = panel.getBoundingClientRect();
                        if (panelRect.top < 60 || panelRect.top > window.innerHeight * 0.4) {
                            const windowTargetY = window.scrollY + panelRect.top - 70;
                            window.scrollTo({ top: Math.max(0, windowTargetY), behavior: 'smooth' });
                        }
                    }
                }
            }, 70);
            return;
        }

        // B. Mode Gulir Vertikal
        const scrollReader = document.querySelector('[data-scroll-reader]');
        const target = scrollReader
            ? (scrollReader.querySelector(`#para-${pNum}`) || scrollReader.querySelector(`[data-paragraph-index="${pNum}"]`))
            : document.getElementById(`para-${pNum}`);

        if (!target) return;

        document.querySelectorAll('.passage-para-block.is-targeted').forEach((el) => {
            el.classList.remove('is-targeted');
        });

        target.classList.add('is-targeted');

        // Jika ada heading H3 tepat di atas paragraf, ikut sertakan agar konteks materi utuh
        const elementToScroll = (target.previousElementSibling && target.previousElementSibling.tagName === 'H3')
            ? target.previousElementSibling
            : target;

        const scrollContainer = document.querySelector('[data-reading-scroll-container]');
        if (scrollContainer && scrollContainer.classList.contains('is-mode-scroll')) {
            const containerRect = scrollContainer.getBoundingClientRect();
            const elemRect = elementToScroll.getBoundingClientRect();
            const targetScrollTop = elemRect.top - containerRect.top + scrollContainer.scrollTop - 24;
            scrollContainer.scrollTo({ top: Math.max(0, targetScrollTop), behavior: 'smooth' });
        }

        // Pastikan kartu materi di layar browser tidak terpotong di bagian atas
        const panel = document.querySelector('[data-passage-panel]');
        if (panel) {
            const panelRect = panel.getBoundingClientRect();
            if (panelRect.top < 60 || panelRect.top > window.innerHeight * 0.4) {
                const windowTargetY = window.scrollY + panelRect.top - 70;
                window.scrollTo({ top: Math.max(0, windowTargetY), behavior: 'smooth' });
            }
        }
    };

    document.addEventListener('click', (e) => {
        const pill = e.target.closest('[data-jump-para]');
        if (!pill) return;
        highlightAndScrollToParagraph(pill.dataset.jumpPara);
    });

    const addMessage = (role, content, extraClass = '') => {
        const message = document.createElement('p');
        message.className = `coach-message coach-message-${role}${extraClass ? ` ${extraClass}` : ''}`;
        if (role === 'assistant') {
            const escaped = document.createElement('div');
            escaped.textContent = content;
            message.innerHTML = formatParagraphPills(escaped.innerHTML);
        } else {
            message.textContent = content;
        }
        transcript.append(message);
        transcript.scrollTop = transcript.scrollHeight;
        return message;
    };

    // Format badge interaktif pada pesan awal/riwayat yang sudah ada
    transcript.querySelectorAll('.coach-message-assistant').forEach((el) => {
        if (!el.querySelector('.coach-para-pill')) {
            el.innerHTML = formatParagraphPills(el.innerHTML);
        }
    });

    const updateCount = () => {
        count.textContent = `${input.value.length} / ${maxLength}`;
    };

    input.addEventListener('input', updateCount);

    prompts.forEach((button) => button.addEventListener('click', () => {
        input.value = button.dataset.coachPrompt;
        updateCount();
        input.focus();
    }));

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const message = input.value.trim();
        if (!message || send.disabled) return;

        error.hidden = true;
        error.textContent = '';
        addMessage('user', message);
        input.value = '';
        updateCount();
        send.disabled = true;
        prompts.forEach((button) => { button.disabled = true; });
        sendLabel.textContent = 'Berpikir…';
        transcript.setAttribute('aria-busy', 'true');
        const loading = addMessage('assistant', 'Sedang menyiapkan pertanyaan untukmu…', 'coach-message-loading');

        try {
            const response = await fetch(panel.dataset.endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({
                    session_id: Number(panel.dataset.sessionId),
                    message,
                    history: history.slice(-6),
                }),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'Tutor AI belum dapat menjawab. Coba lagi.');

            loading.remove();
            addMessage('assistant', data.reply);
            const paraMatch = data.reply.match(/(?:\[?¶(\d+)\]?|\bparagraf\s+\[?¶?(\d+)\]?)/i);
            if (paraMatch) {
                highlightAndScrollToParagraph(paraMatch[1] || paraMatch[2]);
            }
            history.push({ role: 'user', content: message }, { role: 'assistant', content: data.reply });
            history.splice(0, Math.max(0, history.length - 6));
        } catch (requestError) {
            loading.remove();
            error.textContent = requestError.message || 'Koneksi bermasalah. Periksa koneksi lalu coba lagi.';
            error.hidden = false;
            input.value = message;
            updateCount();
        } finally {
            send.disabled = false;
            prompts.forEach((button) => { button.disabled = false; });
            sendLabel.textContent = 'Kirim';
            transcript.setAttribute('aria-busy', 'false');
            input.focus();
        }
    });
}
