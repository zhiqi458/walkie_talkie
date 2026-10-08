export class PeerConnectionManager {
    constructor({ signalClient, stunServers, onRemoteStream, onSpeakerState }) {
        this.signalClient = signalClient;
        this.stunServers = stunServers;
        this.onRemoteStream = onRemoteStream;
        this.onSpeakerState = onSpeakerState;
        this.peers = new Map();
        this.localStream = null;
        this.localTrack = null;
        this.myPeerId = null;
    }

    setSelf(peerId) {
        this.myPeerId = peerId;
    }

    syncParticipants(participants) {
        const activeIds = new Set(participants.map((p) => p.id));
        for (const [peerId, peer] of this.peers.entries()) {
            if (!activeIds.has(peerId)) {
                this.closePeer(peerId);
            }
        }

        for (const participant of participants) {
            if (participant.id === this.myPeerId) {
                continue;
            }
            this.ensurePeer(participant.id, participant.nickname);
        }
    }

    setSpeakerState(peerId, speaking, nickname) {
        if (!peerId) {
            this.onSpeakerState?.(null, false);
            return;
        }

        this.onSpeakerState?.({ id: peerId, nickname }, speaking);
    }

    async startTransmitting() {
        if (this.localStream) {
            return this.localStream;
        }

        this.localStream = await navigator.mediaDevices.getUserMedia({
            audio: {
                echoCancellation: true,
                noiseSuppression: true,
                autoGainControl: true,
            },
            video: false,
        });

        this.localTrack = this.localStream.getAudioTracks()[0] || null;

        for (const peer of this.peers.values()) {
            this.attachLocalTrack(peer);
        }

        return this.localStream;
    }

    stopTransmitting() {
        for (const peer of this.peers.values()) {
            this.detachLocalTrack(peer);
        }

        if (this.localStream) {
            for (const track of this.localStream.getTracks()) {
                track.stop();
            }
        }

        this.localTrack = null;
        this.localStream = null;
    }

    applySignal(event) {
        const signalEvents = event.events || [];
        for (const item of signalEvents) {
            if (item.type !== 'signal') {
                continue;
            }

            const { from, to, payload } = item.data || {};
            if (to !== this.myPeerId || !from) {
                continue;
            }

            const peer = this.ensurePeer(from, from);
            this.applyPeerSignal(peer, payload || {});
        }
    }

    async applyPeerSignal(peer, payload) {
        const pc = peer.pc;
        if (!pc) {
            return;
        }

        if (payload.sdp) {
            await pc.setRemoteDescription(payload.sdp);
            if (payload.sdp.type === 'offer') {
                const answer = await pc.createAnswer();
                await pc.setLocalDescription(answer);
                await this.sendSignal(peer.id, { sdp: pc.localDescription });
            }
        } else if (payload.candidate) {
            try {
                await pc.addIceCandidate(payload.candidate);
            } catch {
                // Ignore candidate timing races.
            }
        }
    }

    ensurePeer(peerId, nickname) {
        let peer = this.peers.get(peerId);
        if (peer) {
            peer.nickname = nickname || peer.nickname;
            return peer;
        }

        const pc = new RTCPeerConnection({ iceServers: this.stunServers });
        peer = {
            id: peerId,
            nickname,
            pc,
            remoteStream: new MediaStream(),
            negotiating: false,
        };
        this.peers.set(peerId, peer);

        pc.onicecandidate = (event) => {
            if (event.candidate) {
                this.sendSignal(peerId, { candidate: event.candidate.toJSON() });
            }
        };

        pc.ontrack = (event) => {
            for (const track of event.streams[0].getTracks()) {
                peer.remoteStream.addTrack(track);
            }
            this.onRemoteStream?.(peerId, peer.remoteStream);
        };

        pc.onconnectionstatechange = () => {
            if (pc.connectionState === 'failed' || pc.connectionState === 'disconnected') {
                this.onSpeakerState?.(null, false);
            }
        };

        pc.onnegotiationneeded = async () => {
            if (this.myPeerId && peerId > this.myPeerId) {
                return;
            }
            await this.negotiate(peer);
        };

        pc.addTransceiver('audio', { direction: 'recvonly' });

        queueMicrotask(() => {
            if (this.myPeerId && peerId < this.myPeerId) {
                this.negotiate(peer).catch(() => {});
            }
        });

        return peer;
    }

    async negotiate(peer) {
        if (peer.negotiating) {
            return;
        }

        peer.negotiating = true;
        try {
            const offer = await peer.pc.createOffer();
            await peer.pc.setLocalDescription(offer);
            await this.sendSignal(peer.id, { sdp: peer.pc.localDescription });
        } finally {
            peer.negotiating = false;
        }
    }

    attachLocalTrack(peer) {
        if (!this.localTrack) {
            return;
        }

        const sender = peer.pc.getSenders().find((item) => item.track && item.track.kind === 'audio');
        if (sender) {
            sender.replaceTrack(this.localTrack);
        } else {
            peer.pc.addTrack(this.localTrack, this.localStream);
        }
        if (this.myPeerId && peer.id < this.myPeerId) {
            this.negotiate(peer).catch(() => {});
        }
    }

    detachLocalTrack(peer) {
        const sender = peer.pc.getSenders().find((item) => item.track && item.track.kind === 'audio');
        if (sender) {
            sender.replaceTrack(null);
        }
    }

    async sendSignal(targetPeerId, payload) {
        await this.signalClient.send('signal', {
            target: targetPeerId,
            payload,
        });
    }

    closePeer(peerId) {
        const peer = this.peers.get(peerId);
        if (!peer) {
            return;
        }

        peer.pc.close();
        this.peers.delete(peerId);
    }

    destroy() {
        for (const peerId of this.peers.keys()) {
            this.closePeer(peerId);
        }
        this.stopTransmitting();
    }
}
