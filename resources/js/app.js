import './bootstrap';
import './qol';
import './reservation-dates';
import './shell';
import './counter-scanner';
import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('workspaceNavigation', () => ({
    sidebar: false,

    openMenu() {
        this.sidebar = true;
        this.$nextTick(() => this.$refs.closeMenu.focus());
    },

    closeMenu() {
        this.sidebar = false;
        this.$nextTick(() => this.$refs.openMenu.focus());
    },

    resetOnDesktop() {
        if (this.sidebar && this.$refs.openMenu.offsetWidth === 0) {
            this.sidebar = false;
        }
    },
}));

Alpine.data('confirmation', () => ({
    dialog: null,
    form: null,
    submitter: null,
    message: '',

    init() {
        this.dialog = this.$el;
        document.addEventListener('submit', (event) => this.handleSubmit(event));
        window.addEventListener('pageshow', () => {
            document.querySelectorAll('form[data-submitting]').forEach((form) => {
                delete form.dataset.submitting;
                form.removeAttribute('aria-busy');
                Array.from(form.elements).forEach((control) => {
                    if (control.hasAttribute('data-submitting-control')) {
                        control.removeAttribute('data-submitting-control');
                        control.removeAttribute('aria-disabled');
                    }
                });
            });
        });
    },

    handleSubmit(event) {
        const form = event.target;
        if (event.defaultPrevented || !(form instanceof HTMLFormElement) || form.method !== 'post') {
            return;
        }
        if (form.dataset.submitting) {
            event.preventDefault();
            return;
        }
        if (form.dataset.confirm && form.dataset.confirmed !== 'true') {
            event.preventDefault();
            this.form = form;
            this.submitter = event.submitter;
            this.message = form.dataset.confirm;
            this.dialog.showModal();
            return;
        }
        delete form.dataset.confirmed;
        form.dataset.submitting = 'true';
        form.setAttribute('aria-busy', 'true');
        Array.from(form.elements).forEach((control) => {
            if (control.type === 'submit' && !control.disabled) {
                control.setAttribute('data-submitting-control', '');
                control.setAttribute('aria-disabled', 'true');
            }
        });
    },

    confirm() {
        const { form, submitter } = this;
        this.dialog.close();
        form.dataset.confirmed = 'true';
        form.requestSubmit(submitter ?? undefined);
        delete form.dataset.confirmed;
    },

    cancel() {
        this.dialog.close();
    },

    reset() {
        this.form = null;
        this.submitter = null;
        this.message = '';
    },
}));

Alpine.start();
