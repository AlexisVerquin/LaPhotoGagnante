import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'preview', 'submit', 'alert', 'alertMessage'];
    static values = {
        maxSize: { type: Number, default: 5 * 1024 * 1024 }, // 5 Mo
        acceptedTypes: Array,
    };

    connect() {
    }

    validationFile() {
        const file = this.inputTarget.files[0];

        this.hideAlert();

        if (!file) {
            return {};
        }

        if (file.size > this.maxSizeValue) {
            this.showError('Fichier trop volumineux (max 5 Mo)');
            this.inputTarget.value = '';
            return {};
        }

        if (this.hasAcceptedTypesValue && !this.acceptedTypesValue.includes(file.type)) {
            this.showError('Type de fichier non autorisé');
            this.inputTarget.value = '';
            return {};
        }

        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                this.previewTarget.src = `${e.target.result}`;
            };
            reader.readAsDataURL(file);

            const route = this.inputTarget.getAttribute('data-url');
            const csrf = this.inputTarget.getAttribute('data-token');
            const submitType = this.inputTarget.getAttribute('data-submitType') ?? null;

            return { file, route, csrf, submitType };
        }

        return {};
    }

    onFileChange() {
        const fileInfo = this.validationFile();

        if (fileInfo.submitType !== "button" || !fileInfo.submitType) {
            this.upload(fileInfo.file, fileInfo.route, fileInfo.csrf);
        }
    }

    sendFile() {
        const fileInfo = this.validationFile();
        this.upload(fileInfo.file, fileInfo.route, fileInfo.csrf);
    }

    async upload(file, route, csrf) {
        if (!file || !route || !csrf) return;
        console.log("test");
        const formData = new FormData();
        formData.append("file", file);

        try {
            const response = await fetch(route, {
                method: "POST",
                body: formData,
                headers: {
                    "X-CSRF-Token": csrf
                }
            });

            const data = await response.json();

            if (data.error) {
                this.showError(data.error);
                this.dispatch("error", { bubbles: true, detail: { message: data.error } });
                return;
            }

            this.showSuccess(data.message ?? "Photo envoyée avec succès !");
            this.dispatch("success", { bubbles: true, detail: { message: data.message } });

        } catch (error) {
            this.showError("Une erreur est survenue, merci de réessayer");
            this.dispatch("error", { bubbles: true, detail: { message: "Erreur réseau" } });
        }
    }

    showError(error) {
        this.renderAlert(error, "alert-danger");
    }

    showSuccess(message) {
        this.renderAlert(message, "alert-success");
    }

    renderAlert(error, className) {
        const isString = typeof error === "string";
        const message = isString ? error : error.message;
        const linkUrl = isString ? null : error.linkUrl;
        const linkText = isString ? null : error.linkText;

        // Reset contenu texte
        this.alertMessageTarget.textContent = "";
        this.alertMessageTarget.appendChild(document.createTextNode(message + " "));

        if (linkUrl) {
            const link = document.createElement("a");
            link.href = linkUrl;
            link.textContent = linkText ?? "En savoir plus";
            this.alertMessageTarget.appendChild(link);
        }

        // Reset classes de couleur puis applique la bonne
        this.alertTarget.classList.remove("alert-danger", "alert-success");
        this.alertTarget.classList.add(className);

        this.alertTarget.hidden = false;
        this.alertTarget.classList.add("show");
        this.alertTarget.scrollIntoView({ behavior: "smooth", block: "center" });
    }

    hideAlert() {
        this.alertTarget.hidden = true;
        this.alertTarget.classList.remove("show");
    }
}
