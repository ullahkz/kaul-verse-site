document.addEventListener('DOMContentLoaded', () => {
    function setupImagePicker(key, title, width) {
        const select = document.getElementById(`kv-select-${key}`);
        if (!select) return;
        const field = document.getElementById(`kv-${key}`);
        const preview = document.getElementById(`kv-${key}-preview`);
        let frame;
        select.addEventListener('click', () => {
            if (!frame) {
                frame = wp.media({ title, button: { text: 'Use image' }, library: { type: 'image' }, multiple: false });
                frame.on('select', () => {
                    const attachment = frame.state().get('selection').first().toJSON();
                    field.value = attachment.id;
                    const image = document.createElement('img');
                    image.src = attachment.sizes?.medium?.url || attachment.url;
                    image.alt = attachment.alt || '';
                    image.style.maxWidth = '100%';
                    image.style.height = 'auto';
                    image.width = width;
                    preview.replaceChildren(image);
                });
            }
            frame.open();
        });
        document.getElementById(`kv-remove-${key}`).addEventListener('click', () => {
            field.value = '0';
            preview.replaceChildren();
        });
    }
    setupImagePicker('main-image', 'Choose main card image', 300);
});
