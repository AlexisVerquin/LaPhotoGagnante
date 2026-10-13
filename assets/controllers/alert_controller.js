// assets/controllers/alert_controller.js
import { Controller } from '@hotwired/stimulus';


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
