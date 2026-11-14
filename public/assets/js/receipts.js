(() => {
    const form = document.querySelector('[data-receipt-filters]');
    if (!form) return;
    const course = form.querySelector('[data-receipt-class]');
    const batch = form.querySelector('[data-receipt-batch]');
    course.addEventListener('change', () => {
        batch.value = '';
        for (const option of batch.options) {
            const excluded = Boolean(course.value && option.value && option.dataset.class !== course.value);
            option.disabled = excluded;
            option.hidden = excluded;
        }
        batch.dispatchEvent(new Event('change', { bubbles: true }));
    });
})();
