document.querySelector('[data-print]')?.addEventListener('click', async event => {
    const button = event.currentTarget;
    button.disabled = true;
    try {
        // Let the school logo finish loading before opening the print dialog.
        await Promise.all(Array.from(document.images, image => image.decode().catch(() => {})));
        await document.fonts.ready;
        window.print();
    } finally { button.disabled = false; }
});
