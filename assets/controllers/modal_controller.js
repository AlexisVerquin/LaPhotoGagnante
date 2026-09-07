// assets/controllers/modal_controller.js
import { Controller } from '@hotwired/stimulus';
import { Modal } from 'bootstrap';

export default class extends Controller {
    static targets = ['dialog', 'action'];

    connect() {
        this.modal = new Modal(this.dialogTarget);
    }

    showModal(event) {
        event.preventDefault()
        this.modal.show();
    }

    open() {
        this.modal.show();
    }

    close() {
        this.modal.hide();
    }

    disconnect() {
        this.modal.dispose();
    }
}
