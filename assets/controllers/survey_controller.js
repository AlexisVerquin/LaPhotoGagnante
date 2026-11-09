// assets/controllers/survey_controller.js
// Une instance par carte du sondage. Après un vote, la réponse (scores de tous les choix)
// est diffusée par l'événement « survey:results » pour que chaque carte se mette à jour.
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['card', 'badge', 'button', 'progressbar', 'pourcentVote', 'numberVote'];
    static values = {
        themeid: String,
        total: Number,
        vote: Number,
        route: String,
        csrf: String
    };

    async update() {
        const formData = new FormData();
        formData.append("id", this.themeidValue);

        try {
            const response = await fetch(this.routeValue, {
                method: "POST",
                body: formData,
                headers: {
                    "X-CSRF-Token": this.csrfValue
                }
            });

            const data = await response.json();

            if (!response.ok || data.error) {
                this.dispatch("error", { bubbles: true, detail: { message: data.error || "Une erreur est survenue" } });
                return;
            }

            // Toutes les cartes (y compris celle-ci) écoutent « survey:results » sur window.
            this.dispatch("results", { detail: { choices: data.choices, total: data.total } });
            this.dispatch("success", { bubbles: true, detail: { message: data.message } });

        } catch (error) {
            this.dispatch("error", { bubbles: true, detail: { message: "Erreur réseau" } });
        }
    }

    // Met à jour la carte à partir des scores de tous les choix.
    refresh({ detail: { choices, total } }) {
        const mine = choices.find((choice) => String(choice.id) === this.themeidValue);
        if (!mine) return;

        const maxVote = Math.max(0, ...choices.map((choice) => choice.vote));
        const percent = total > 0 ? (mine.vote / total) * 100 : 0;
        const isLeading = maxVote > 0 && mine.vote === maxVote;

        this.voteValue = mine.vote;
        this.totalValue = total;
        this.progressbarTarget.style.width = percent + "%";
        this.pourcentVoteTarget.textContent = Math.round(percent) + " %";
        this.numberVoteTarget.textContent = mine.vote;
        this.cardTarget.classList.toggle("is-leading", isLeading);
        this.badgeTarget.hidden = !isLeading;

        // Un vote par jour : on désactive tous les boutons.
        this.buttonTarget.disabled = true;
        this.buttonTarget.classList.add("btn-disabled");
    }

    updateVote() {
        if (!this.routeValue || !this.csrfValue) return;
        this.update();
    }
}
