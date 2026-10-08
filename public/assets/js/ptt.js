export class PttController {
    constructor(button) {
        this.button = button;
        this.active = false;
        this.handlers = { start: [], stop: [] };

        this.handlePointerDown = this.handlePointerDown.bind(this);
        this.handlePointerUp = this.handlePointerUp.bind(this);
        this.handleKeyDown = this.handleKeyDown.bind(this);
        this.handleKeyUp = this.handleKeyUp.bind(this);

        button.addEventListener('pointerdown', this.handlePointerDown);
        window.addEventListener('pointerup', this.handlePointerUp);
        window.addEventListener('pointercancel', this.handlePointerUp);
        window.addEventListener('keydown', this.handleKeyDown);
        window.addEventListener('keyup', this.handleKeyUp);
    }

    onStart(handler) {
        this.handlers.start.push(handler);
    }

    onStop(handler) {
        this.handlers.stop.push(handler);
    }

    setState(active) {
        this.active = active;
        this.button.classList.toggle('is-holding', active);
        this.button.classList.toggle('is-transmitting', active);
        this.button.setAttribute('aria-pressed', active ? 'true' : 'false');
        this.button.querySelector('.ptt-label').textContent = active ? 'TRANSMITTING' : 'HOLD TO TALK';
    }

    flashBusy(message) {
        this.button.classList.add('is-holding');
        setTimeout(() => {
            if (!this.active) {
                this.button.classList.remove('is-holding');
                this.button.querySelector('.ptt-label').textContent = 'HOLD TO TALK';
            }
        }, 700);
        if (message) {
            this.button.setAttribute('data-busy-message', message);
        }
    }

    destroy() {
        this.button.removeEventListener('pointerdown', this.handlePointerDown);
        window.removeEventListener('pointerup', this.handlePointerUp);
        window.removeEventListener('pointercancel', this.handlePointerUp);
        window.removeEventListener('keydown', this.handleKeyDown);
        window.removeEventListener('keyup', this.handleKeyUp);
    }

    handlePointerDown(event) {
        event.preventDefault();
        this.emit('start');
    }

    handlePointerUp() {
        this.emit('stop');
    }

    handleKeyDown(event) {
        if (event.code !== 'Space' || event.repeat) {
            return;
        }

        event.preventDefault();
        this.emit('start');
    }

    handleKeyUp(event) {
        if (event.code !== 'Space') {
            return;
        }

        event.preventDefault();
        this.emit('stop');
    }

    emit(type, payload) {
        const list = this.handlers[type];
        for (const handler of list) {
            handler(payload);
        }
    }
}
