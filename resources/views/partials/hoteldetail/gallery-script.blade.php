<script>
(() => {
    const gallery = HolidayVietDetailDialogs.photos('hotel', HOTEL_PHOTOS, HOTEL_NAME);
    document.querySelectorAll('[data-hotel-photo]').forEach(button => button.addEventListener('click', () => {
        gallery.show(Number(button.dataset.hotelPhoto));
        HolidayVietDetailDialogs.open('hotel-photos-modal', button);
    }));
})();
</script>
