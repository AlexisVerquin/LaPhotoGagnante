// assets/controllers/modal_controller.js
import { Controller } from '@hotwired/stimulus';
import { Modal } from 'bootstrap';

export default class extends Controller {
    static targets = ['dialog'];

    connect() {
        this.modal = new Modal(this.element);
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
