import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = [
        'days', 'hours', 'minutes', 'seconds',
        'voteDays', 'voteHours', 'voteMinutes', 'voteSeconds',
        'voteStrip', 'voteLabel', 'voteDigits',
        'galleryButton'
    ];

    static values = {
        target: String,
        voteTarget: String,
    };

    connect() {
        this.voteButtonAdded = false;
        this.tick();
        this.interval = setInterval(() => this.tick(), 1000);
    }

    disconnect() {
        clearInterval(this.interval);
    }

    pad(n) {
        return String(n).padStart(2, '0');
    }

    setDigits(targets, diff) {
        const d = Math.floor(diff / 86400000);
        const h = Math.floor((diff % 86400000) / 3600000);
        const m = Math.floor((diff % 3600000) / 60000);
        const s = Math.floor((diff % 60000) / 1000);

        if (targets.days) targets.days.textContent = this.pad(d);
        if (targets.hours) targets.hours.textContent = this.pad(h);
        if (targets.minutes) targets.minutes.textContent = this.pad(m);
        if (targets.seconds) targets.seconds.textContent = this.pad(s);
    }
    tick() {
        const target = new Date(this.targetValue);
        const voteTarget = new Date(this.voteTargetValue);

        const diff = target - new Date();
        const diffVote = voteTarget - new Date();

        if (diff > 0) {
            this.setDigits({
                days: this.hasDaysTarget ? this.daysTarget : null,
                hours: this.hasHoursTarget ? this.hoursTarget : null,
                minutes: this.hasMinutesTarget ? this.minutesTarget : null,
                seconds: this.hasSecondsTarget ? this.secondsTarget : null,
            }, diff);
        } else {
            [this.hasDaysTarget && this.daysTarget,
                this.hasHoursTarget && this.hoursTarget,
                this.hasMinutesTarget && this.minutesTarget,
                this.hasSecondsTarget && this.secondsTarget].forEach(el => {
                if (el) el.textContent = '00';
            });
        }

        if (diffVote > 0) {
            if (this.hasVoteLabelTarget) this.voteLabelTarget.textContent = 'Votes ouverts dans';
            if (this.hasVoteStripTarget) this.voteStripTarget.classList.remove('is-open');
            if (this.hasVoteDigitsTarget) {
                this.voteDigitsTarget.classList.remove('d-none');
                this.voteDigitsTarget.classList.add('d-flex');
            }
            if (this.hasGalleryButtonTarget) this.galleryButtonTarget.textContent = 'Voir la galerie';

            this.setDigits({
                days: this.hasVoteDaysTarget ? this.voteDaysTarget : null,
                hours: this.hasVoteHoursTarget ? this.voteHoursTarget : null,
                minutes: this.hasVoteMinutesTarget ? this.voteMinutesTarget : null,
                seconds: this.hasVoteSecondsTarget ? this.voteSecondsTarget : null,
            }, diffVote);
        } else {
            if (this.hasVoteLabelTarget) this.voteLabelTarget.textContent = 'Votes ouverts !';
            if (this.hasVoteStripTarget) this.voteStripTarget.classList.add('is-open');
            if (this.hasVoteDigitsTarget) {
                this.voteDigitsTarget.classList.add('d-none');
                this.voteDigitsTarget.classList.remove('d-flex');
            }
            if (this.hasGalleryButtonTarget) this.galleryButtonTarget.textContent = 'Voir la galerie et voter';

            if (!this.voteButtonAdded && this.hasVoteStripTarget) {
                const btnVotes = document.createElement('a');
                btnVotes.classList.add('btn-vote-unlock');
                btnVotes.href = '#';
                btnVotes.innerHTML = '<i class="bi bi-hand-thumbs-up-fill"></i> Voir la galerie et voter';
                this.voteStripTarget.appendChild(btnVotes);
                this.voteButtonAdded = true;
            }
        }

        if (diff <= 0 && diffVote <= 0) {
            clearInterval(this.interval);
        }
    }
}
