<script>
let profileWishlistItems = [];
let profileWishlistLimit = 4;
let profileWishlistLoading = false;
const profileWishlistRemoving = new Set();

async function profileWishlistRequest(path, options = {}) {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 12000);
    try {
        const response = await api(path, {...options, signal: controller.signal});
        if (response.status === 401) {
            window.location.href = '/login';
            throw new Error('Phiên đăng nhập đã hết hạn. Vui lòng đăng nhập lại.');
        }
        const data = response.status === 204 ? {} : await response.json();
        if (!response.ok) throw new Error(accountErrorMessage(data, 'Chưa thể cập nhật danh sách yêu thích.'));
        return data;
    } finally {
        clearTimeout(timeout);
    }
}

function profileWishlistImage(hotel) {
    try {
        const url = new URL(HolidayVietImages.cover(hotel), window.location.origin);
        if (['http:', 'https:'].includes(url.protocol)) return url.href;
    } catch {}
    return HolidayVietImages.placeholder;
}

function renderProfileWishlist() {
    const list = document.getElementById('profileWishlistList');
    const status = document.getElementById('profileWishlistStatus');
    const count = document.getElementById('profileWishlistCount');
    count.textContent = profileWishlistItems.length + ' khách sạn';
    count.hidden = false;
    document.getElementById('profileWishlistBody').setAttribute('aria-busy', 'false');
    status.hidden = profileWishlistItems.length > 0;
    list.hidden = profileWishlistItems.length === 0;

    if (!profileWishlistItems.length) {
        list.replaceChildren();
        status.innerHTML = '<div class="empty-state">' + accountIcon('hotel') + '<h3>Bạn chưa lưu khách sạn nào</h3><p>Nhấn tim tại khách sạn bạn thích để xem lại ở đây.</p><a class="button" href="/">Khám phá khách sạn ' + accountIcon('arrow-right') + '</a></div>';
    } else {
        list.innerHTML = profileWishlistItems.slice(0, profileWishlistLimit).map(item => {
            const hotel = item.hotel;
            const name = accountEscape(hotel.name || 'Khách sạn');
            const location = accountEscape(hotel.location?.name || '');
            return `<article class="profile-wishlist-item" data-wishlist-hotel="${Number(item.hotel_id)}">
                <a class="profile-wishlist-link" href="/hotels/${Number(item.hotel_id)}">
                    <img src="${accountEscape(profileWishlistImage(hotel))}" data-accommodation-image alt="" loading="lazy" width="400" height="225">
                    <div class="profile-wishlist-copy"><h3>${name}</h3>${location ? '<p>' + accountIcon('pin') + location + '</p>' : ''}<span class="text-link">Xem khách sạn ${accountIcon('arrow-right')}</span></div>
                </a>
                <button type="button" class="profile-wishlist-remove" data-remove-wishlist="${Number(item.hotel_id)}" aria-label="Bỏ ${name} khỏi yêu thích" title="Bỏ yêu thích" ${profileWishlistRemoving.has(Number(item.hotel_id)) ? 'disabled aria-busy="true"' : ''}>
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/></svg>
                </button>
            </article>`;
        }).join('');
    }

    const expanded = profileWishlistLimit > 4;
    const more = document.getElementById('profileWishlistMore');
    document.getElementById('profileWishlistFooter').hidden = profileWishlistItems.length <= 4;
    more.textContent = profileWishlistLimit >= profileWishlistItems.length ? 'Thu gọn' : 'Xem thêm';
    more.setAttribute('aria-expanded', String(expanded));
}

async function loadProfileWishlist() {
    if (profileWishlistLoading || !localStorage.getItem('token')) return;
    profileWishlistLoading = true;
    const status = document.getElementById('profileWishlistStatus');
    document.getElementById('profileWishlistBody').setAttribute('aria-busy', 'true');
    document.getElementById('profileWishlistList').hidden = true;
    document.getElementById('profileWishlistCount').hidden = true;
    document.getElementById('profileWishlistFooter').hidden = true;
    status.hidden = false;
    status.innerHTML = accountLoading('Đang tải khách sạn yêu thích…');
    try {
        const data = await profileWishlistRequest('/wishlist');
        const items = Array.isArray(data) ? data : data?.data;
        if (!Array.isArray(items) || items.some(item => !item?.hotel || !Number.isSafeInteger(Number(item.hotel_id)) || Number(item.hotel_id) <= 0)) {
            throw new Error('Dữ liệu yêu thích không hợp lệ.');
        }
        profileWishlistItems = items;
        profileWishlistLimit = 4;
        renderProfileWishlist();
    } catch {
        document.getElementById('profileWishlistBody').setAttribute('aria-busy', 'false');
        status.innerHTML = '<div class="empty-state error-state">' + accountIcon('circle-alert') + '<h3>Chưa thể tải khách sạn yêu thích</h3><p>Vui lòng kiểm tra kết nối và thử lại.</p><button type="button" class="button secondary" id="profileWishlistRetry">Thử lại</button></div>';
    } finally {
        profileWishlistLoading = false;
    }
}

async function removeProfileWishlist(hotelId, button) {
    if (profileWishlistRemoving.has(hotelId)) return;
    profileWishlistRemoving.add(hotelId);
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    const removedIndex = profileWishlistItems.findIndex(item => Number(item.hotel_id) === hotelId);
    try {
        await profileWishlistRequest('/wishlist/' + hotelId, {method: 'DELETE'});
        profileWishlistItems = profileWishlistItems.filter(item => Number(item.hotel_id) !== hotelId);
        renderProfileWishlist();
        showToast('Đã bỏ khách sạn khỏi yêu thích.', 'success');
        if (document.activeElement === button || document.activeElement === document.body) {
            const buttons = document.querySelectorAll('#profileWishlistList [data-remove-wishlist]');
            (buttons[Math.min(removedIndex, buttons.length - 1)] || document.getElementById('profileWishlistTitle')).focus();
        }
    } catch (error) {
        showToast(error.name === 'AbortError' || error instanceof TypeError ? 'Chưa thể bỏ yêu thích. Vui lòng thử lại.' : error.message, 'error');
    } finally {
        profileWishlistRemoving.delete(hotelId);
        const currentButton = document.querySelector('[data-remove-wishlist="' + hotelId + '"]');
        if (currentButton) {
            currentButton.disabled = false;
            currentButton.removeAttribute('aria-busy');
        }
    }
}

document.addEventListener('DOMContentLoaded', () => {
    loadProfileWishlist();
    document.getElementById('profileWishlistList').addEventListener('click', event => {
        const button = event.target.closest('[data-remove-wishlist]');
        if (button) removeProfileWishlist(Number(button.dataset.removeWishlist), button);
    });
    document.getElementById('profileWishlistStatus').addEventListener('click', event => {
        if (event.target.closest('#profileWishlistRetry')) loadProfileWishlist();
    });
    document.getElementById('profileWishlistMore').addEventListener('click', () => {
        profileWishlistLimit = profileWishlistLimit >= profileWishlistItems.length ? 4 : profileWishlistLimit + 4;
        renderProfileWishlist();
    });
});
</script>
