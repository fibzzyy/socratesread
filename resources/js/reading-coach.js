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

    const addMessage = (role, content, extraClass = '') => {
        const message = document.createElement('p');
        message.className = `coach-message coach-message-${role}${extraClass ? ` ${extraClass}` : ''}`;
        message.textContent = content;
        transcript.append(message);
        transcript.scrollTop = transcript.scrollHeight;
        return message;
    };

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
