import { AudioLevelMeter } from './audio-level.js';
import { PttController } from './ptt.js';
import { SignalClient } from './signaling.js';
import { PeerConnectionManager } from './webrtc.js';
import { registerPwa } from './pwa.js';

const state = window.__WALKIE__ || {};

registerPwa();

const joinPage = document.querySelector('.auth-screen');
if (joinPage) {
    renderQrCode();
}

const pttButton = document.getElementById('pttButton');
const participantList = document.getElementById('participantList');

if (pttButton && participantList) {
    initializeChannelUi();
}

function initializeChannelUi() {
    const connectionStatus = document.getElementById('connectionStatus');
    const speakerLabel = document.getElementById('speakerLabel');
    const userCountBadge = document.getElementById('userCountBadge');
    const microphoneStatus = document.getElementById('microphoneStatus');
    const statusMessage = document.getElementById('statusMessage');
    const audioCanvas = document.getElementById('audioMeter');
    const copyChannelBtn = document.getElementById('copyChannelBtn');
    const ptt = new PttController(pttButton);
    const audioMeter = new AudioLevelMeter(audioCanvas);
    const signalClient = new SignalClient({
        url: `${state.baseUrl}/signal`,
        token: state.session?.signal_token || '',
        onState: (mode) => setConnectionState(connectionStatus, mode),
        onEvent: (payload) => handleSignalEvent(payload),
    });
    const webrtc = new PeerConnectionManager({
        signalClient,
        stunServers: state.stunServers || [],
        onRemoteStream: (peerId, stream) => {
            attachRemoteAudio(peerId, stream);
            audioMeter.setStream(stream);
        },
        onSpeakerState: updateSpeakerLabel,
    });

    webrtc.setSelf(state.session?.peer_id || '');
    signalClient.start().then(() => setConnectionState(connectionStatus, 'connected')).catch(() => setConnectionState(connectionStatus, 'disconnected'));

    let currentParticipants = Array.isArray(state.state?.participants) ? state.state.participants : [];
    renderParticipants(currentParticipants);
    updateSpeakerFromState(state.state || {});
    if (currentParticipants.length) {
        userCountBadge.textContent = `${currentParticipants.length} USER${currentParticipants.length === 1 ? '' : 'S'}`;
    }

    ptt.onStart(async () => {
        try {
            setStatusMessage('');
            const result = await signalClient.send('ptt_request');
            if (!result.ok) {
                const speaker = result.speaker;
                const busyName = getParticipantName(currentParticipants, speaker) || 'another user';
                setStatusMessage(`${busyName} is currently speaking.`);
                ptt.flashBusy('busy');
                return;
            }

            await webrtc.startTransmitting();
            ptt.setState(true);
            microphoneStatus.textContent = 'Microphone is on.';
            audioMeter.setStream(webrtc.localStream);
            setStatusMessage('You are transmitting.');
        } catch (error) {
            if (error.name === 'NotAllowedError' || error.name === 'NotFoundError') {
                setStatusMessage('Microphone permission is required to talk.');
            } else {
                setStatusMessage('Unable to start transmitting.');
            }
        }
    });

    ptt.onStop(async () => {
        if (!ptt.active && !webrtc.localStream) {
            return;
        }

        try {
            await signalClient.send('ptt_release');
        } catch {
            // Ignore release errors during cleanup.
        }

        webrtc.stopTransmitting();
        ptt.setState(false);
        microphoneStatus.textContent = 'Microphone is off.';
        audioMeter.stop();
        setStatusMessage('');
    });

    if (state.state?.participants) {
        webrtc.syncParticipants(state.state.participants);
        userCountBadge.textContent = `${state.state.participants.length} USER${state.state.participants.length === 1 ? '' : 'S'}`;
    }

    copyChannelBtn?.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(state.publicUrl || window.location.href);
            setStatusMessage('Channel URL copied.');
        } catch {
            setStatusMessage('Unable to copy the channel URL.');
        }
    });

    window.addEventListener('beforeunload', () => {
        try {
            const payload = JSON.stringify({ type: 'leave', token: state.session?.signal_token || '' });
            navigator.sendBeacon(`${state.baseUrl}/signal`, new Blob([payload], { type: 'application/json' }));
        } catch {
            // Best effort cleanup only.
        }
        webrtc.destroy();
    });

    function handleSignalEvent(payload) {
        if (Array.isArray(payload.participants)) {
            currentParticipants = payload.participants;
            renderParticipants(currentParticipants);
            webrtc.syncParticipants(currentParticipants);
            userCountBadge.textContent = `${currentParticipants.length} USER${currentParticipants.length === 1 ? '' : 'S'}`;
        }

        if (payload.speaker !== undefined) {
            window.__WALKIE__ = window.__WALKIE__ || {};
            window.__WALKIE__.state = {
                ...(window.__WALKIE__.state || {}),
                speaker: payload.speaker || null,
                participants: Array.isArray(payload.participants) ? payload.participants : currentParticipants,
            };
            updateSpeakerFromState(payload);
            renderParticipants(currentParticipants);
        }

        if (Array.isArray(payload.events)) {
            for (const event of payload.events) {
                if (event.type === 'peer_joined' || event.type === 'peer_left') {
                    setConnectionState(connectionStatus, 'connected');
                }
            }
        }

        webrtc.applySignal(payload);
    }
}

function updateSpeakerLabel(speaker, speaking) {
    const label = document.getElementById('speakerLabel');
    if (!label) {
        return;
    }

    if (!speaker) {
        label.textContent = 'Waiting for a speaker';
        return;
    }

    if (speaking) {
        label.textContent = 'You are transmitting';
        return;
    }

    label.textContent = `${speaker.nickname || 'Someone'} is talking`;
}

function updateSpeakerFromState(payload) {
    const label = document.getElementById('speakerLabel');
    if (!label) {
        return;
    }

    const speakerId = payload.speaker || null;
    if (!speakerId) {
        label.textContent = 'Waiting for a speaker';
        return;
    }

    if (speakerId === state.session?.peer_id) {
        label.textContent = 'You are transmitting';
        return;
    }

    const name = getParticipantName(payload.participants || state.state?.participants || [], speakerId);
    label.textContent = name ? `${name} is talking` : 'Another user is talking';
}

function renderParticipants(participants) {
    const list = document.getElementById('participantList');
    if (!list) {
        return;
    }

    list.innerHTML = '';
    if (!participants.length) {
        list.innerHTML = '<div class="text-secondary">No participants yet.</div>';
        return;
    }

    for (const participant of participants) {
        const participantState = participant.id === window.__WALKIE__?.state?.speaker
            ? 'speaking'
            : (participant.online ? 'listening' : 'offline');

        const el = document.createElement('div');
        el.className = 'participant-item';
        el.innerHTML = `
            <div class="participant-left">
                <div class="participant-avatar">${initials(participant.nickname)}</div>
                <div class="participant-meta">
                    <div class="fw-semibold">${escapeHtml(participant.nickname)}</div>
                    <div class="participant-status ${participantState}">${participantState.toUpperCase()}</div>
                </div>
            </div>
            <div class="small text-secondary">${participant.id === window.__WALKIE__?.session?.peer_id ? 'You' : ''}</div>
        `;
        list.appendChild(el);
    }
}

function setConnectionState(element, state) {
    if (!element) {
        return;
    }

    element.dataset.state = state;
    element.querySelector('.status-text').textContent = state.toUpperCase();
}

function setStatusMessage(message) {
    const node = document.getElementById('statusMessage');
    if (node) {
        node.textContent = message || '';
    }
}

function getParticipantName(participants, id) {
    return participants.find((item) => item.id === id)?.nickname || '';
}

function initials(name) {
    return (name || 'G')
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0].toUpperCase())
        .join('');
}

function escapeHtml(value) {
    return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

function attachRemoteAudio(peerId, stream) {
    let audio = document.getElementById(`remote-${peerId}`);
    if (!audio) {
        audio = document.createElement('audio');
        audio.id = `remote-${peerId}`;
        audio.autoplay = true;
        audio.playsInline = true;
        document.body.appendChild(audio);
    }
    audio.srcObject = stream;
}

function renderQrCode() {
    const target = document.querySelector('.qr-code');
    if (!target) {
        return;
    }

    const url = target.dataset.qr || state.publicUrl || window.location.href;
    if (typeof window.QRCode !== 'function') {
        return;
    }

    target.innerHTML = '';
    new window.QRCode(target, {
        text: url,
        width: 196,
        height: 196,
        colorDark: '#08131f',
        colorLight: '#ffffff',
        correctLevel: window.QRCode.CorrectLevel.H,
    });
}
