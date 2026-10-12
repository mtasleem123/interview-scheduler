<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Interview Assistant</title>
    <style>
        :root {
            --bg: #eef2f8;
            --bg-accent: #dfe7f7;
            --panel: #ffffff;
            --text: #172033;
            --muted: #667085;
            --border: #e4e8f0;
            --accent: #2457d6;
            --accent-strong: #1b45ad;
            --accent-soft: #eaf0fd;
            --on-accent: #ffffff;
            --bot-bubble: #f2f5fa;
            --success: #12b76a;
            --danger: #d92d20;
            --danger-soft: #fef3f2;
            --shadow: 0 20px 50px -20px rgba(23, 32, 51, 0.25);
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0c1018;
                --bg-accent: #131b2b;
                --panel: #151b26;
                --text: #e7eaf0;
                --muted: #98a2b3;
                --border: #262e3d;
                --accent: #5b8cff;
                --accent-strong: #7aa2ff;
                --accent-soft: #1b2640;
                --on-accent: #0c1018;
                --bot-bubble: #1e2533;
                --success: #32d583;
                --danger: #ff6b5b;
                --danger-soft: #2a1715;
                --shadow: 0 20px 50px -20px rgba(0, 0, 0, 0.6);
            }
        }

        * { box-sizing: border-box; }
        html { min-height: 100%; background: var(--bg); }
        body {
            margin: 0;
            min-height: 100vh;
            background: radial-gradient(circle at top, var(--bg-accent), var(--bg) 60%);
            color: var(--text);
            font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            display: flex;
            justify-content: center;
            padding: 24px 16px;
        }
        button { font: inherit; color: inherit; }
        :focus-visible { outline: 3px solid var(--accent); outline-offset: 2px; }

        .app {
            width: 100%;
            max-width: 760px;
            height: calc(100vh - 48px);
            min-height: 520px;
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 20px;
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* ---------- Header ---------- */
        .header {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px 20px;
            border-bottom: 1px solid var(--border);
        }
        .avatar {
            flex: none;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), var(--accent-strong));
            color: var(--on-accent);
            display: grid;
            place-items: center;
        }
        .avatar.small { width: 32px; height: 32px; }
        .header-text { flex: 1; min-width: 0; }
        .header h1 { font-size: 1.05rem; margin: 0; font-weight: 650; display: flex; align-items: center; gap: 8px; }
        .badge {
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            padding: 2px 8px;
            border-radius: 999px;
            background: #fef0c7;
            color: #93370d;
        }
        .badge[hidden] { display: none; }
        .presence { margin: 2px 0 0; font-size: 0.85rem; color: var(--muted); display: flex; align-items: center; gap: 6px; }
        .presence::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: var(--success); }
        .icon-button {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: transparent;
            color: var(--muted);
            cursor: pointer;
            display: grid;
            place-items: center;
        }
        .icon-button:hover { background: var(--accent-soft); color: var(--accent); }
        .icon-button[aria-pressed="false"] .sound-on,
        .icon-button[aria-pressed="true"] .sound-off { display: none; }

        /* ---------- Messages ---------- */
        .messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px;
            display: flex;
            flex-direction: column;
            gap: 14px;
            scroll-behavior: smooth;
        }
        .row { display: flex; gap: 10px; align-items: flex-end; animation: rise 0.2s ease-out; }
        .row.user { justify-content: flex-end; }
        .stack { display: flex; flex-direction: column; gap: 4px; max-width: min(80%, 520px); }
        .row.user .stack { align-items: flex-end; }
        .bubble {
            padding: 11px 15px;
            border-radius: 18px;
            line-height: 1.5;
            white-space: pre-wrap;
            overflow-wrap: anywhere;
        }
        .row.bot .bubble { background: var(--bot-bubble); border-bottom-left-radius: 6px; }
        .row.user .bubble { background: var(--accent); color: var(--on-accent); border-bottom-right-radius: 6px; }
        .row.error .bubble { background: var(--danger-soft); color: var(--danger); border-bottom-left-radius: 6px; }
        .meta { font-size: 0.75rem; color: var(--muted); padding: 0 4px; }
        .retry {
            align-self: flex-start;
            border: 1px solid var(--border);
            background: var(--panel);
            border-radius: 999px;
            padding: 4px 12px;
            font-size: 0.85rem;
            cursor: pointer;
        }
        .typing { display: flex; gap: 4px; padding: 14px 16px; }
        .typing span {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--muted);
            animation: blink 1.2s infinite ease-in-out;
        }
        .typing span:nth-child(2) { animation-delay: 0.15s; }
        .typing span:nth-child(3) { animation-delay: 0.3s; }

        /* ---------- Suggestions ---------- */
        .suggestions { display: flex; flex-wrap: wrap; gap: 8px; padding: 0 20px 12px; }
        .suggestions[hidden] { display: none; }
        .chip {
            border: 1px solid var(--border);
            background: var(--accent-soft);
            color: var(--accent);
            border-radius: 999px;
            padding: 8px 14px;
            font-size: 0.9rem;
            cursor: pointer;
        }
        .chip:hover { border-color: var(--accent); }

        /* ---------- Composer ---------- */
        .composer { border-top: 1px solid var(--border); padding: 14px 20px 10px; }
        .voice-status {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            min-height: 22px;
            margin-bottom: 10px;
            font-size: 0.9rem;
            color: var(--muted);
        }
        .bars { display: none; gap: 3px; align-items: center; height: 18px; }
        .voice-status.listening .bars { display: flex; }
        .voice-status.listening { color: var(--danger); }
        .bars span { width: 3px; height: 100%; background: currentColor; border-radius: 2px; animation: wave 0.9s infinite ease-in-out; }
        .bars span:nth-child(2) { animation-delay: 0.1s; }
        .bars span:nth-child(3) { animation-delay: 0.2s; }
        .bars span:nth-child(4) { animation-delay: 0.3s; }
        .input-row { display: flex; gap: 10px; align-items: center; }
        .mic {
            flex: none;
            width: 52px;
            height: 52px;
            border-radius: 50%;
            border: none;
            background: var(--accent);
            color: var(--on-accent);
            cursor: pointer;
            display: grid;
            place-items: center;
            transition: transform 0.15s, background 0.15s;
        }
        .mic:hover { background: var(--accent-strong); }
        .mic.listening { background: var(--danger); animation: ring 1.4s infinite; }
        .mic:disabled { opacity: 0.45; cursor: not-allowed; animation: none; }
        .field {
            flex: 1;
            min-width: 0;
            display: flex;
            align-items: center;
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: 4px 4px 4px 18px;
            background: var(--panel);
        }
        .field:focus-within { border-color: var(--accent); }
        .field input {
            flex: 1;
            min-width: 0;
            border: none;
            outline: none;
            background: transparent;
            color: var(--text);
            font-size: 1rem;
            padding: 10px 0;
        }
        .send {
            flex: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: none;
            background: var(--accent);
            color: var(--on-accent);
            cursor: pointer;
            display: grid;
            place-items: center;
        }
        .send:disabled { background: var(--border); color: var(--muted); cursor: not-allowed; }
        .footnote { margin: 10px 0 0; text-align: center; font-size: 0.75rem; color: var(--muted); }

        @keyframes rise { from { opacity: 0; transform: translateY(6px); } }
        @keyframes blink { 0%, 80%, 100% { opacity: 0.3; transform: scale(0.85); } 40% { opacity: 1; transform: scale(1); } }
        @keyframes wave { 0%, 100% { transform: scaleY(0.3); } 50% { transform: scaleY(1); } }
        @keyframes ring { 0% { box-shadow: 0 0 0 0 rgba(217, 45, 32, 0.45); } 100% { box-shadow: 0 0 0 16px rgba(217, 45, 32, 0); } }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation: none !important; transition: none !important; scroll-behavior: auto !important; }
        }

        @media (max-width: 600px) {
            body { padding: 0; }
            .app { height: 100vh; height: 100dvh; min-height: 0; border-radius: 0; border: none; }
            .header, .messages, .composer { padding-left: 16px; padding-right: 16px; }
            .suggestions { padding: 0 16px 12px; }
            .stack { max-width: 88%; }
        }
    </style>
</head>
<body>
<div class="app">
    <header class="header">
        <div class="avatar" aria-hidden="true">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/><path d="m9 16 2 2 4-4"/></svg>
        </div>
        <div class="header-text">
            <h1>Interview Assistant <span id="mode-badge" class="badge" title="Rule-based replies without AI. Set ANTHROPIC_API_KEY to use Claude." hidden>Demo mode</span></h1>
            <p class="presence">Online · Book or check your interview by voice</p>
        </div>
        <button id="sound" class="icon-button" type="button" aria-pressed="true" aria-label="Spoken replies on" title="Spoken replies">
            <svg class="sound-on" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/><path d="M19 5a10 10 0 0 1 0 14"/></svg>
            <svg class="sound-off" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/><line x1="23" y1="9" x2="17" y2="15"/><line x1="17" y1="9" x2="23" y2="15"/></svg>
        </button>
        <button id="restart" class="icon-button" type="button" aria-label="Start a new conversation" title="New conversation">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><polyline points="3 3 3 8 8 8"/></svg>
        </button>
    </header>

    <section id="messages" class="messages" aria-live="polite" aria-label="Conversation"></section>

    <div id="suggestions" class="suggestions">
        <button class="chip" type="button">What slots are available tomorrow?</button>
        <button class="chip" type="button">I want to book an interview</button>
        <button class="chip" type="button">Check my existing booking</button>
    </div>

    <footer class="composer">
        <div id="voice-status" class="voice-status" aria-live="polite">
            <span class="bars" aria-hidden="true"><span></span><span></span><span></span><span></span></span>
            <span id="voice-text">Tap the microphone to speak, or type below.</span>
        </div>
        <div class="input-row">
            <button id="mic" class="mic" type="button" aria-label="Start speaking">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="2" width="6" height="12" rx="3"/><path d="M5 10a7 7 0 0 0 14 0"/><line x1="12" y1="17" x2="12" y2="22"/></svg>
            </button>
            <form id="text-form" class="field">
                <input id="text-input" type="text" placeholder="Type your message…" autocomplete="off" maxlength="2000" aria-label="Your message">
                <button id="send" class="send" type="submit" aria-label="Send message" disabled>
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                </button>
            </form>
        </div>
        <p class="footnote">Please share only your name and email. Do not share other personal details.</p>
    </footer>
</div>

<script>
    const messagesEl = document.getElementById('messages');
    const suggestionsEl = document.getElementById('suggestions');
    const voiceStatus = document.getElementById('voice-status');
    const voiceText = document.getElementById('voice-text');
    const micButton = document.getElementById('mic');
    const soundButton = document.getElementById('sound');
    const restartButton = document.getElementById('restart');
    const textForm = document.getElementById('text-form');
    const textInput = document.getElementById('text-input');
    const sendButton = document.getElementById('send');

    const GREETING = 'Hello! I am your interview assistant. I can show available interview slots, book an interview for you, or check an existing booking. How can I help you today?';
    const IDLE_HINT = 'Tap the microphone to speak, or type below.';

    /** Conversation sent to the backend on every turn (text only, starts with the user). */
    let conversation = [];
    let busy = false;
    let soundOn = readPreference('voiceBotSound', 'on') !== 'off';

    function readPreference(key, fallback) {
        try { return localStorage.getItem(key) ?? fallback; } catch { return fallback; }
    }

    function writePreference(key, value) {
        try { localStorage.setItem(key, value); } catch { /* Storage can be blocked; the default still works. */ }
    }

    function timeNow() {
        return new Date().toLocaleTimeString('en-IN', { hour: 'numeric', minute: '2-digit' });
    }

    function scrollToBottom() {
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    const BOT_ICON = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/></svg>';

    /** Build a message row. Text is set with textContent so replies can never inject HTML. */
    function addMessage(kind, text) {
        const row = document.createElement('div');
        row.className = 'row ' + kind;

        if (kind !== 'user') {
            const avatar = document.createElement('div');
            avatar.className = 'avatar small';
            avatar.setAttribute('aria-hidden', 'true');
            avatar.innerHTML = BOT_ICON; // Static icon only.
            row.appendChild(avatar);
        }

        const stack = document.createElement('div');
        stack.className = 'stack';

        const bubble = document.createElement('div');
        bubble.className = 'bubble';
        bubble.textContent = text;
        stack.appendChild(bubble);

        const meta = document.createElement('span');
        meta.className = 'meta';
        meta.textContent = (kind === 'user' ? 'You' : 'Assistant') + ' · ' + timeNow();
        stack.appendChild(meta);

        row.appendChild(stack);
        messagesEl.appendChild(row);
        scrollToBottom();

        return stack;
    }

    function showTyping() {
        const row = document.createElement('div');
        row.className = 'row bot';
        row.id = 'typing';
        row.innerHTML = '<div class="avatar small" aria-hidden="true">' + BOT_ICON + '</div>'
            + '<div class="bubble typing" style="background: var(--bot-bubble)" aria-label="Assistant is typing"><span></span><span></span><span></span></div>';
        messagesEl.appendChild(row);
        scrollToBottom();
    }

    function hideTyping() {
        document.getElementById('typing')?.remove();
    }

    function setStatus(text, listening = false) {
        voiceText.textContent = text;
        voiceStatus.classList.toggle('listening', listening);
    }

    function setBusy(isBusy) {
        busy = isBusy;
        micButton.disabled = isBusy || !recognition;
        sendButton.disabled = isBusy || textInput.value.trim() === '';
        suggestionsEl.querySelectorAll('.chip').forEach(chip => chip.disabled = isBusy);
    }

    // ---------- Text-to-speech ----------
    function speak(text) {
        if (!soundOn || !('speechSynthesis' in window)) {
            return;
        }
        window.speechSynthesis.cancel();
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.lang = 'en-IN';
        const voice = window.speechSynthesis.getVoices().find(v => v.lang === 'en-IN');
        if (voice) {
            utterance.voice = voice;
        }
        utterance.onstart = () => setStatus('Speaking… tap the microphone to interrupt.');
        utterance.onend = () => setStatus(IDLE_HINT);
        window.speechSynthesis.speak(utterance);
    }

    function stopSpeaking() {
        window.speechSynthesis?.cancel();
    }

    function updateSoundButton() {
        soundButton.setAttribute('aria-pressed', String(soundOn));
        soundButton.setAttribute('aria-label', soundOn ? 'Spoken replies on' : 'Spoken replies off');
    }

    soundButton.addEventListener('click', () => {
        soundOn = !soundOn;
        writePreference('voiceBotSound', soundOn ? 'on' : 'off');
        if (!soundOn) {
            stopSpeaking();
            setStatus(IDLE_HINT);
        }
        updateSoundButton();
    });

    // ---------- Backend call ----------
    function send(text) {
        text = text.trim();
        if (!text || busy) {
            return;
        }
        suggestionsEl.hidden = true;
        addMessage('user', text);
        request(text);
    }

    async function request(text) {
        conversation.push({ role: 'user', content: text });
        setBusy(true);
        setStatus('Assistant is thinking…');
        showTyping();

        try {
            const response = await fetch('/api/voice-agent', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ messages: conversation.slice(-29) }),
            });
            const data = await response.json().catch(() => ({}));

            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Sorry, something went wrong on our side.');
            }

            hideTyping();
            document.getElementById('mode-badge').hidden = data.mode !== 'demo';
            conversation.push({ role: 'assistant', content: data.reply });
            addMessage('bot', data.reply);
            setStatus(IDLE_HINT);
            speak(data.reply);
        } catch (error) {
            hideTyping();
            conversation.pop(); // Drop the unanswered turn so the next request stays valid.
            showError(error.message, text);
            setStatus(IDLE_HINT);
        } finally {
            setBusy(false);
        }
    }

    function showError(message, failedText) {
        const stack = addMessage('error', message);
        const retry = document.createElement('button');
        retry.type = 'button';
        retry.className = 'retry';
        retry.textContent = 'Try again';
        retry.addEventListener('click', () => {
            if (busy) {
                return;
            }
            stack.closest('.row').remove();
            request(failedText);
        });
        stack.insertBefore(retry, stack.lastChild);
    }

    textInput.addEventListener('input', () => {
        sendButton.disabled = busy || textInput.value.trim() === '';
    });

    textForm.addEventListener('submit', event => {
        event.preventDefault();
        send(textInput.value);
        textInput.value = '';
        sendButton.disabled = true;
    });

    suggestionsEl.addEventListener('click', event => {
        const chip = event.target.closest('.chip');
        if (chip) {
            send(chip.textContent);
        }
    });

    restartButton.addEventListener('click', () => {
        if (busy) {
            return;
        }
        stopSpeaking();
        start();
    });

    // ---------- Speech-to-text ----------
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    let recognition = null;
    let listening = false;

    if (SpeechRecognition) {
        recognition = new SpeechRecognition();
        recognition.lang = 'en-IN';
        recognition.interimResults = true;
        recognition.maxAlternatives = 1;

        micButton.addEventListener('click', () => {
            if (listening) {
                recognition.stop();
                return;
            }
            stopSpeaking(); // Do not let the assistant talk over the candidate.
            textInput.value = '';
            try { recognition.start(); } catch { /* Already started. */ }
        });

        recognition.onstart = () => {
            listening = true;
            micButton.classList.add('listening');
            micButton.setAttribute('aria-label', 'Stop listening');
            setStatus('Listening… tap again when you are done.', true);
        };

        recognition.onend = () => {
            listening = false;
            micButton.classList.remove('listening');
            micButton.setAttribute('aria-label', 'Start speaking');
            if (!busy) {
                setStatus(IDLE_HINT);
            }
        };

        recognition.onerror = event => {
            const messages = {
                'not-allowed': 'Microphone access is blocked. Please allow it in your browser, or type instead.',
                'no-speech': 'I did not hear anything. Please tap the microphone and try again.',
                'network': 'Voice input needs an internet connection. You can type instead.',
            };
            listening = false;
            setStatus(messages[event.error] || 'Voice input is not available right now. Please type instead.');
        };

        recognition.onresult = event => {
            const result = event.results[event.results.length - 1];
            const transcript = result[0].transcript;
            textInput.value = transcript; // Show the words live as the candidate speaks.
            if (result.isFinal) {
                textInput.value = '';
                send(transcript);
            }
        };
    } else {
        micButton.disabled = true;
        micButton.title = 'Voice input works in Google Chrome or Microsoft Edge.';
        setStatus('Voice input works in Chrome or Edge. You can type your message below.');
    }

    function start() {
        conversation = []; // The greeting is UI only; the API conversation must start with the user.
        messagesEl.replaceChildren();
        suggestionsEl.hidden = false;
        addMessage('bot', GREETING);
        if (recognition) {
            setStatus(IDLE_HINT);
        }
        setBusy(false);
    }

    updateSoundButton();
    start();
</script>
</body>
</html>
