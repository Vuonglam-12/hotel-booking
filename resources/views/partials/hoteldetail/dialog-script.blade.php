<script>
(() => {
    const dialogs = Array.from(document.querySelectorAll('.hv-detail-dialog'));
    const remembered = new Map();
    let photoController = null;

    function open(id, opener = document.activeElement) {
        const dialog = document.getElementById(id);
        if (!dialog || dialog.open) return;
        remembered.set(id, {
            focus: opener,
            overflow: document.body.style.overflow,
            gutter: document.documentElement.style.scrollbarGutter,
        });
        document.documentElement.style.scrollbarGutter = 'stable';
        document.body.style.overflow = 'hidden';
        dialog.showModal();
        dialog.querySelector('[data-detail-dialog-close]').focus({ preventScroll: true });
    }

    function close(id) { document.getElementById(id)?.close(); }
    dialogs.forEach(dialog => {
        dialog.querySelector('[data-detail-dialog-close]').addEventListener('click', () => close(dialog.id));
        dialog.addEventListener('cancel', event => { event.preventDefault(); close(dialog.id); });
        dialog.addEventListener('close', () => {
            const state = remembered.get(dialog.id);
            if (!state) return;
            document.body.style.overflow = state.overflow;
            document.documentElement.style.scrollbarGutter = state.gutter;
            if (state.focus?.isConnected) state.focus.focus({ preventScroll: true });
            remembered.delete(dialog.id);
        });
        dialog.addEventListener('click', event => {
            if (event.target !== dialog) return;
            const bounds = dialog.getBoundingClientRect();
            if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) close(dialog.id);
        });
    });
    document.addEventListener('keydown', event => {
        const dialog = dialogs.find(candidate => candidate.open);
        if (!dialog) return;
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopImmediatePropagation();
            close(dialog.id);
        } else if (!event.ctrlKey && !event.altKey && ['ArrowLeft', 'ArrowRight'].includes(event.key) && photoController) {
            event.preventDefault();
            photoController.move(event.key === 'ArrowRight' ? 1 : -1);
        }
    }, true);

    function photos(prefix, source, title) {
        const images = Array.from(new Set(source));
        const photo = document.getElementById(prefix + '-modal-photo');
        const previous = document.getElementById(prefix + '-photo-prev');
        const next = document.getElementById(prefix + '-photo-next');
        const counter = document.getElementById(prefix + '-photo-counter');
        const thumbnails = document.getElementById(prefix + '-photo-thumbnails');
        let index = 0;
        function show(nextIndex) {
            index = Math.max(0, Math.min(images.length - 1, nextIndex));
            photo.setAttribute('data-accommodation-image', '');
            photo.src = images[index] || HolidayVietImages.placeholder;
            photo.alt = images.length ? title + ' – ảnh ' + (index + 1) : 'Chưa có ảnh ' + title;
            previous.hidden = next.hidden = images.length < 2;
            previous.disabled = index === 0;
            next.disabled = index >= images.length - 1;
            counter.textContent = images.length ? 'Ảnh ' + (index + 1) + ' / ' + images.length : 'Chưa có ảnh cho loại phòng này';
            thumbnails.querySelectorAll('button').forEach((button, position) => button.setAttribute('aria-pressed', String(position === index)));
            photoController = { move: delta => show(index + delta) };
        }
        thumbnails.replaceChildren();
        thumbnails.hidden = images.length < 2;
        if (images.length > 1) images.forEach((url, position) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.setAttribute('aria-label', 'Xem ảnh ' + (position + 1));
            const image = document.createElement('img');
            image.setAttribute('data-accommodation-image', '');
            image.src = url;
            image.alt = '';
            button.append(image);
            button.addEventListener('click', () => show(position));
            thumbnails.append(button);
        });
        previous.onclick = () => show(index - 1);
        next.onclick = () => show(index + 1);
        return { show };
    }
    window.HolidayVietDetailDialogs = { open, close, photos };
})();
</script>
