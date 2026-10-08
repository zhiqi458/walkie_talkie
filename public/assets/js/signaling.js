export class SignalClient {
    constructor({ url, token, onState, onEvent }) {
        this.url = url;
        this.token = token;
        this.onState = onState;
        this.onEvent = onEvent;
        this.running = false;
        this.sequence = 0;
        this.retryDelay = 1000;
    }

    async start() {
        this.running = true;
        const hello = await this.send('hello', { since: this.sequence });
        this.sequence = hello.sequence || this.sequence;
        this.onEvent?.(hello);
        this.loop();
    }

    stop() {
        this.running = false;
    }

    async send(type, payload = {}) {
        const response = await fetch(this.url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ type, token: this.token, ...payload }),
            credentials: 'same-origin',
        });

        const data = await response.json();
        if (!response.ok) {
            const error = new Error(data.error || 'signal_error');
            error.data = data;
            throw error;
        }

        return data;
    }

    async loop() {
        while (this.running) {
            try {
                this.onState?.('connected');
                const payload = await this.send('poll', { since: this.sequence });
                this.retryDelay = 1000;
                this.sequence = payload.sequence || this.sequence;
                this.onEvent?.(payload);
                await this.sleep(900);
            } catch (error) {
                this.onState?.('reconnecting');
                await this.sleep(this.retryDelay);
                this.retryDelay = Math.min(this.retryDelay * 2, 5000);
            }
        }
    }

    sleep(ms) {
        return new Promise((resolve) => window.setTimeout(resolve, ms));
    }
}
