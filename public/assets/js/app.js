document.querySelectorAll('[data-open]').forEach(button => button.addEventListener('click', () => document.getElementById(button.dataset.open).showModal()));
document.querySelectorAll('[data-close]').forEach(button => button.addEventListener('click', () => button.closest('dialog').close()));
document.querySelectorAll('dialog').forEach(dialog => {
    if (dialog.hasAttribute('data-auto-open')) dialog.showModal();
    dialog.addEventListener('click', event => { if (event.target === dialog) { const rect = dialog.getBoundingClientRect(); if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close(); } });
});
document.querySelectorAll('[data-confirm]').forEach(form => form.addEventListener('submit', event => { if (!confirm(form.dataset.confirm)) event.preventDefault(); }));
document.querySelector('[data-menu]')?.addEventListener('click', () => document.querySelector('.sidebar').classList.toggle('is-open'));
document.querySelectorAll('input[type="number"]').forEach(input => { input.min = '0'; input.step = ['amount','fee'].includes(input.name) ? '0.01' : '1'; });

document.querySelectorAll('[data-enrollment-form]').forEach(form => {
    const foc = form.querySelector('[name="is_foc"]');
    const amount = form.querySelector('[name="amount"]');
    const method = form.querySelector('[name="method"]');
    let paidAmount = amount.value;
    function syncFoc() {
        if (foc.checked) {
            if (!amount.disabled) paidAmount = amount.value;
            amount.value = '0';
        } else if (amount.disabled) amount.value = paidAmount;
        amount.disabled = foc.checked;
        method.disabled = foc.checked;
    }
    foc.addEventListener('change', syncFoc);
    form.addEventListener('reset', () => setTimeout(() => { amount.disabled = false; paidAmount = amount.value; syncFoc(); }, 0));
    syncFoc();
});
document.querySelectorAll('[data-branding-upload]').forEach(input => {
    const preview = document.querySelector(`[data-branding-preview="${input.dataset.brandingUpload}"]`);
    const original = preview.src;
    let objectUrl;
    input.addEventListener('change', () => {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        const file = input.files[0];
        input.setCustomValidity(file && file.size > 2 * 1024 * 1024 ? 'Choose an image up to 2 MB.' : '');
        if (file && ['image/png', 'image/jpeg', 'image/webp'].includes(file.type) && file.size <= 2 * 1024 * 1024) {
            objectUrl = URL.createObjectURL(file);
            preview.src = objectUrl;
        } else preview.src = original;
    });
});
