document.querySelectorAll('[data-login-portal]').forEach((button) => {
    button.addEventListener('click', () => {
        const form = button.closest('form');
        const selectedPortal = button.dataset.loginPortal;
        const portalInput = form?.querySelector('input[name="portal"]');

        if (!form || !portalInput || !selectedPortal) return;

        portalInput.value = selectedPortal;
        form.querySelectorAll('[data-login-portal]').forEach((option) => {
            option.setAttribute('aria-pressed', String(option === button));
        });
    });
});

document.querySelectorAll('[data-password-toggle]').forEach((button) => {
    button.addEventListener('click', () => {
        const form = button.closest('form');
        const password = form?.querySelector('input[name="password"]');
        if (!password) return;

        const showPassword = password.type === 'password';
        password.type = showPassword ? 'text' : 'password';
        button.setAttribute('aria-pressed', String(showPassword));
        button.setAttribute('aria-label', showPassword ? 'Hide password' : 'Show password');
    });
});
