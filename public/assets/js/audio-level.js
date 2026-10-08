export class AudioLevelMeter {
    constructor(canvas) {
        this.canvas = canvas;
        this.ctx = canvas.getContext('2d');
        this.audioContext = null;
        this.analyser = null;
        this.source = null;
        this.raf = 0;
        this.active = false;
    }

    setStream(stream) {
        if (!stream) {
            this.stop();
            return;
        }

        this.stop();
        this.audioContext = new AudioContext();
        this.analyser = this.audioContext.createAnalyser();
        this.analyser.fftSize = 128;
        this.source = this.audioContext.createMediaStreamSource(stream);
        this.source.connect(this.analyser);
        this.active = true;
        this.draw();
    }

    stop() {
        this.active = false;
        if (this.raf) {
            cancelAnimationFrame(this.raf);
            this.raf = 0;
        }
        if (this.source) {
            try { this.source.disconnect(); } catch {}
            this.source = null;
        }
        if (this.analyser) {
            try { this.analyser.disconnect(); } catch {}
            this.analyser = null;
        }
        if (this.audioContext) {
            const context = this.audioContext;
            this.audioContext = null;
            context.close().catch(() => {});
        }
    }

    draw() {
        const drawFrame = () => {
            const ctx = this.ctx;
            const width = this.canvas.width;
            const height = this.canvas.height;
            ctx.clearRect(0, 0, width, height);
            ctx.fillStyle = 'rgba(7, 17, 28, 1)';
            ctx.fillRect(0, 0, width, height);

            const bars = 24;
            const gap = 6;
            const barWidth = (width - (bars - 1) * gap) / bars;
            const data = new Uint8Array(this.analyser ? this.analyser.frequencyBinCount : bars);
            if (this.analyser) {
                this.analyser.getByteFrequencyData(data);
            }

            for (let i = 0; i < bars; i++) {
                const level = this.analyser ? data[i % data.length] / 255 : 0;
                const barHeight = Math.max(8, height * (0.1 + level * 0.82));
                const x = i * (barWidth + gap);
                const y = height - barHeight;
                const gradient = ctx.createLinearGradient(0, y, 0, height);
                gradient.addColorStop(0, '#38bdf8');
                gradient.addColorStop(1, '#22c55e');
                ctx.fillStyle = gradient;
                ctx.fillRect(x, y, barWidth, barHeight);
            }

            ctx.strokeStyle = 'rgba(148, 163, 184, 0.14)';
            ctx.strokeRect(0, 0, width, height);

            if (this.active) {
                this.raf = requestAnimationFrame(drawFrame);
            }
        };

        drawFrame();
    }
}
