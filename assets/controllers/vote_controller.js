// assets/controllers/vote_controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['button', 'count'];
    static values = {
        user: String,
        photo: String,
        csrf: String,
        url: String,
    };

    connect() {
        // écoute globale : permet à CHAQUE instance (chaque photo) de réagir
        // au vote, qu'elle soit la nouvelle ou l'ancienne photo votée
        this.onVoteChanged = this.onVoteChanged.bind(this);
        window.addEventListener('vote:changed', this.onVoteChanged);
    }

    disconnect() {
        window.removeEventListener('vote:changed', this.onVoteChanged);
    }

    submitVote() {
        this.send();
    }

    async send() {
        try {
            const formData = new FormData();
            formData.append('photoId', this.photoValue);

            const response = await fetch(this.urlValue, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-CSRF-Token': this.csrfValue,
                },
            });

            const data = await response.json();

            if (!data.success) {
                // ex: "vous avez déjà voté pour cette photo"
                console.log(data.message);
                return;
            }

            // on notifie TOUTES les instances (dont soi-même)
            window.dispatchEvent(new CustomEvent('vote:changed', { detail: data }));

        } catch (error) {
            console.log(error);
        }
    }

    onVoteChanged(event) {
        const { newPhotoId, newVoteCount, oldPhotoId, oldVoteCount } = event.detail;

        if (String(newPhotoId) === this.photoValue) {
            this.buttonTarget.classList.add('is-voted');
            this.buttonTarget.setAttribute('aria-pressed', 'true');
            this.countTarget.textContent = newVoteCount;
        }

        if (oldPhotoId !== null && String(oldPhotoId) === this.photoValue) {
            this.buttonTarget.classList.remove('is-voted');
            this.buttonTarget.setAttribute('aria-pressed', 'false');
            this.countTarget.textContent = oldVoteCount;
        }
    }
}
