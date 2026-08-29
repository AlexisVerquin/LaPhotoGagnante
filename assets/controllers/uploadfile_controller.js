import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['input', 'preview', 'error', 'route'];
    static values = {
        maxSize: { type: Number, default: 5 * 1024 * 1024 }, // 5 Mo
        acceptedTypes: Array,
    };

    connect() {
        console.log("Connection de Uploadfile")
    }

    onFileChange(event) {

        const file = event.target.files[0];

        this.errorTarget.hidden = true;

        if (!file) {
            return;
        }

        // Validation taille
        if (file.size > this.maxSizeValue) {
            this.showError('Fichier trop volumineux (max 5 Mo)');
            this.inputTarget.value = ''; // reset l'input
            return;
        }

        // Validation type (optionnel)
        if (this.hasAcceptedTypesValue && !this.acceptedTypesValue.includes(file.type)) {
            this.showError('Type de fichier non autorisé');
            this.inputTarget.value = '';
            return;
        }

        // Preview si c'est une image
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = (e) => {
                this.previewTarget.src = `${e.target.result}`;
            };
            reader.readAsDataURL(file);

            const route = event.target.getAttribute('data-url');
            const csrf = event.target.getAttribute('data-token');
            this.upload(file, route, csrf);

        }
    }

    showError(message) {
        this.errorTarget.textContent = message;
        this.errorTarget.hidden = false;
    }

    async upload(file, route, csrf) {
        if (!file || !route || !csrf) return;
        console.log("UPLOAD ...");
        const formData = new FormData();
        formData.append("file", file);

        try {
            const response = await fetch(route, {
                method: "POST",
                body: formData,
                headers : {
                    "X-CSRF-Token": csrf
                }
            })

            if (!response.ok) throw new Error(`Erreur ${response.status}`)
            const data = await response.json()
            console.log(data);
        } catch (error) {
            console.error("Échec de l'upload", error);
            this.showError("Une erreur est survenu, merci de réesayer");
        }
    }
}
