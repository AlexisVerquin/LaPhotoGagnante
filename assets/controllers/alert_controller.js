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
        this.show('danger', event.detail.message, event.detail.link);
    }

    info(event) {
        this.show('info', event.detail.message);
    }

    /**
     * Le message est toujours inséré comme du texte, jamais comme du HTML : pas de faille XSS possible,
     * même si un jour il contient une saisie d'utilisateur. Un lien éventuel est passé à part.
     */
    show(type, message, link = null) {
        if (!message) return;

        const alertEl = document.createElement('div');
        alertEl.className = `alert alert-${normalizeAlertType(type)} alert-dismissible fade show shadow-sm d-flex justify-content-between align-items-start`;
        alertEl.setAttribute('role', 'alert');

        const text = document.createElement('div');
        text.textContent = message;
        // Seulement des liens vers le site lui-même (pas de « javascript: » ni de site externe)
        if (link && typeof link.url === 'string' && link.url.startsWith('/') && !link.url.startsWith('//')) {
            const anchor = document.createElement('a');
            anchor.href = link.url;
            anchor.className = 'alert-link ms-1';
            anchor.textContent = link.label || link.url;
            text.append(' ', anchor);
        }

        const closeWrapper = document.createElement('div');
        closeWrapper.className = 'ms-2';
        const closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'btn-close';
        closeButton.dataset.action = 'click->alert#close';
        closeButton.setAttribute('aria-label', 'Fermer');
        closeWrapper.append(closeButton);

        alertEl.append(text, closeWrapper);
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
