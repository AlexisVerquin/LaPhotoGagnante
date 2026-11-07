// assets/controllers/alert_controller.js
import { Controller } from '@hotwired/stimulus';

// Les seuls types qui ont un style dans app.css : les autres s'afficheraient sans fond.
const STYLED_TYPES = ['success', 'danger', 'warning', 'info'];

/**
 * Trouve un style pour n'importe quel type de message : les erreurs en rouge,
 * les inconnus en « info ».
 */
export function normalizeAlertType(type) {
    const value = String(type ?? '').toLowerCase().replace(/^alert-/, '');

    if (STYLED_TYPES.includes(value)) {
        return value;
    }
    if (value === 'error' || value.endsWith('_error') || value.endsWith('-error')) {
        return 'danger';
    }

    return 'info';
}

export default class extends Controller {
    static targets = ['container'];
    static values = {
        timeout: { type: Number, default: 5000 },
        flashes: Array
    };



    connect() {
        if (!this.flashesValue) {
            return;
        }
        this.flashesValue.forEach((index) => {
            this.show(index.type, index.message)
        })

    }

    success(event) {
        this.show('success', event.detail.message);
    }

    error(event) {
        this.show('danger', event.detail.message);
    }

    info(event) {
        this.show('info', event.detail.message);
    }

    show(type, message) {
        if (!message) return;

        type = normalizeAlertType(type);

        const wrapper = document.createElement('div');
        wrapper.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show shadow-sm d-flex justify-content-between align-items-start" role="alert">
            <div>${message}</div>
            <div class="ms-2"><button type="button" class="btn-close" data-action="click->alert#close" aria-label="Fermer"></button></div>
        </div>
    `;

        const alertEl = wrapper.firstElementChild;
        this.containerTarget.appendChild(alertEl);

        if (this.timeoutValue > 0) {
            setTimeout(() => this.closeAlert(alertEl), this.timeoutValue);
        }
    }

    close(event) {
        this.closeAlert(event.target.closest('.alert'));
    }

    closeAlert(alertEl) {
        if (!alertEl) return;
        alertEl.classList.remove('show');
        alertEl.addEventListener('transitionend', () => alertEl.remove(), { once: true });
    }
}
