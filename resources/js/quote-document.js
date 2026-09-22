document.querySelector('[data-quote-print]')?.addEventListener('click', async () => {
    await document.fonts.ready;
    await Promise.all([...document.images].map(image => image.decode().catch(() => {})));
    window.print();
});
