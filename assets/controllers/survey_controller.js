// assets/controllers/survey_controller.js
import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['progressbar', 'pourcentVote', 'numberVote'];
    static values = {
        themeid: String,
        total: Number,
        vote: Number,
        route: String,
        csrf: String
    };

    connect() {
    }

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

            const newVoteCount = data.vote;
            const newTotal = data.total;
            const newPercent = (newVoteCount / newTotal) * 100;

            this.progressbarTarget.style['width'] = newPercent + "%";
            this.pourcentVoteTarget.textContent = Math.round(newPercent) + " %";
            this.numberVoteTarget.textContent = newVoteCount;

            this.dispatch("success", { bubbles: true, detail: { message: data.message } });

        } catch (error) {
            this.dispatch("error", { bubbles: true, detail: { message: "Erreur réseau" } });
        }
    }

    updateVote() {
        if (!this.routeValue || !this.csrfValue) return;
        this.update();
    }

    disconnect() {
    }
}
