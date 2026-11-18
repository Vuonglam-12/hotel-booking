<script>
    window.HolidayVietImages = {
        placeholder: @json(\App\Support\AccommodationImage::placeholder()),
        cover(hotel) { return hotel?.cover_image_url || this.placeholder; },
        escapeAttribute(value) {
            return String(value ?? '').replace(/[&<>"']/g, character => ({
                '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
            })[character]);
        }
    };
    document.addEventListener('error', function (event) {
        const image = event.target;
        if (image?.tagName !== 'IMG' || !image.hasAttribute('data-accommodation-image')) return;
        const fallback = new URL(window.HolidayVietImages.placeholder, document.baseURI).href;
        if (image.src !== fallback) image.src = fallback;
    }, true);
</script>
