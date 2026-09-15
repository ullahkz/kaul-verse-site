document.addEventListener('click', async (event) => {
    const button = event.target.closest('.kv-card__share');
    if (!button) return;
    const status = button.parentElement.querySelector('[role="status"]');
    try {
        if (navigator.share) {
            await navigator.share({ title: button.dataset.title, url: button.dataset.url });
        } else if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(button.dataset.url);
            status.textContent = 'Link copied.';
        } else {
            window.prompt('Copy this link to share:', button.dataset.url);
        }
    } catch (error) {
        if (error.name !== 'AbortError') window.prompt('Copy this link to share:', button.dataset.url);
    }
});
