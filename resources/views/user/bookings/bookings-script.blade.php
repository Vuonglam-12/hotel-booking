<script>
/* ============================================================
   bookings-script.blade.php
   Booking History 2.0 - Logic hoàn chỉnh
   ============================================================ */
(function() {
    'use strict';

    // ===========================
    // STATE
    // ===========================
    let currentTab = 'all';
    let bookings = [];
    let pagination = {};
    let selectedBookingId = null;
    let reviewedBookingIds = new Set(); // track reviewed

    // ===========================
    // API HELPER
    // ===========================
    async function api(path, options = {}) {
        const token = localStorage.getItem('token');
        return fetch('/api' + path, {
            ...options,
            headers: {
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                ...(options.headers || {})
            }
        });
    }

    // ===========================
    // DOM refs
    // ===========================
    const tabsContainer = document.getElementById('tabsContainer');
    const bookingList = document.getElementById('bookingList');
    const paginationContainer = document.getElementById('paginationContainer');
    const detailPanel = document.getElementById('detailPanel');
    const detailContent = document.getElementById('detailContent');
    const btnCloseDetail = document.getElementById('btnCloseDetail');
    const reviewModalOverlay = document.getElementById('reviewModal');

    // ===========================
    // FORMAT HELPERS
    // ===========================
    function formatDate(dateStr) {
        if (!dateStr) return '';
        return new Date(dateStr).toLocaleDateString('vi-VN', {
            day: '2-digit', month: '2-digit', year: 'numeric'
        });
    }

    function formatPrice(amount) {
        if (!amount) return '0 VND';
        return Number(amount).toLocaleString('vi-VN') + ' VND';
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    // ===========================
    // FETCH BOOKINGS
    // ===========================
    async function fetchBookings(page = 1) {
        showLoading();
        try {
            const params = new URLSearchParams({ per_page: 10, page });
            if (currentTab !== 'all') params.append('status', currentTab);

            const res = await api(`/bookings/my?${params}`);
            if (!res.ok) throw new Error('Lỗi tải dữ liệu');
            const data = await res.json();
            bookings = data.data || [];
            pagination = {
                currentPage: data.current_page,
                lastPage: data.last_page,
                total: data.total,
                from: data.from,
                to: data.to,
            };
            await fetchStatusCounts();
            renderBookingList(bookings);
            renderPagination();
            // re-select detail if any
            if (selectedBookingId) {
                highlightCard(selectedBookingId);
            }
        } catch (err) {
            console.error(err);
            bookingList.innerHTML = `<div class="empty-state">
                <i class="fa-solid fa-circle-exclamation"></i>
                <p>Không thể tải danh sách đặt phòng</p>
                <p style="color:var(--danger);">${escapeHtml(err.message)}</p>
            </div>`;
        }
    }

    async function fetchStatusCounts() {
        try {
            const statuses = ['pending', 'confirmed', 'completed', 'cancelled'];
            const counts = { all: 0 };
            const promises = statuses.map(async status => {
                const res = await api(`/bookings/my?status=${status}&per_page=1`);
                const data = await res.json();
                counts[status] = data.total || 0;
                counts.all += (data.total || 0);
            });
            await Promise.all(promises);
            window.__tabCounts = counts;
            renderTabs();
        } catch (e) {
            console.warn('Could not fetch tab counts');
            window.__tabCounts = null;
            renderTabs();
        }
    }

    // ===========================
    // RENDER TABS
    // ===========================
    function renderTabs() {
        const counts = window.__tabCounts || {};
        const tabs = [
            { key: 'all', label: 'Tất cả', count: counts.all ?? '...' },
            { key: 'pending', label: 'Chờ xác nhận', count: counts.pending ?? '...' },
            { key: 'confirmed', label: 'Đã xác nhận', count: counts.confirmed ?? '...' },
            { key: 'completed', label: 'Hoàn thành', count: counts.completed ?? '...' },
            { key: 'cancelled', label: 'Đã huỷ', count: counts.cancelled ?? '...' },
        ];

        tabsContainer.innerHTML = tabs.map(tab => `
            <button class="tab-btn ${currentTab === tab.key ? 'active' : ''}" data-tab="${tab.key}">
                ${tab.label}
                <span class="badge">${tab.count}</span>
            </button>
        `).join('');

        // Attach events
        tabsContainer.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const tab = this.dataset.tab;
                if (tab === currentTab) return;
                currentTab = tab;
                selectedBookingId = null;
                closeDetail();
                fetchBookings(1);
            });
        });
    }

    // ===========================
    // RENDER BOOKING LIST
    // ===========================
    function renderBookingList(list) {
        if (!list.length) {
            bookingList.innerHTML = `<div class="empty-state">
                <i class="fa-solid fa-calendar-xmark"></i>
                <p>Không có đặt phòng nào</p>
                <a href="{{ route('home') }}" class="btn-main">Đặt phòng ngay</a>
            </div>`;
            return;
        }

        bookingList.innerHTML = list.map(booking => {
            const hotel = booking.hotel || {};
            const img = hotel.images?.[0]?.image_path || `https://picsum.photos/seed/${hotel.id || 1}/72/72`;
            const status = booking.status; // pending/confirmed/completed/cancelled
            const isReviewed = reviewedBookingIds.has(booking.id);
            let statusClass = '';
            let statusText = '';
            switch(status) {
                case 'pending': statusClass = 'status-pending'; statusText = 'Chờ xác nhận'; break;
                case 'confirmed': statusClass = 'status-confirmed'; statusText = 'Đã xác nhận'; break;
                case 'completed': statusClass = 'status-completed'; statusText = isReviewed ? 'Đã đánh giá' : 'Hoàn thành'; break;
                case 'cancelled': statusClass = 'status-cancelled'; statusText = 'Đã huỷ'; break;
            }
            if (isReviewed && status === 'completed') statusClass = 'status-reviewed';

            const nights = booking.booking_rooms?.[0]?.nights || 1;
            const rooms = booking.booking_rooms?.length || 1;
            const guests = booking.num_guests || 1;
            const totalPrice = formatPrice(booking.total_price);
            const checkIn = formatDate(booking.check_in);
            const checkOut = formatDate(booking.check_out);

            // Actions - FIXED LOGIC with invoice and proper buttons
            let actionButtons = '';
            if (status === 'pending') {
                actionButtons = `
                    <button class="btn-action" data-action="detail" data-id="${booking.id}">Xem chi tiết</button>
                    <button class="btn-action danger" data-action="cancel" data-id="${booking.id}">Huỷ</button>
                `;
            } else if (status === 'confirmed') {
                actionButtons = `
                    <button class="btn-action" data-action="detail" data-id="${booking.id}">Xem chi tiết</button>
                    <button class="btn-action invoice" data-action="invoice" data-id="${booking.id}">Hóa đơn PDF</button>
                    <button class="btn-action danger" data-action="cancel" data-id="${booking.id}">Huỷ</button>
                `;
            } else if (status === 'completed') {
                actionButtons = `
                    <button class="btn-action" data-action="detail" data-id="${booking.id}">Xem chi tiết</button>
                    <button class="btn-action primary" data-action="rebook" data-id="${booking.id}">Đặt lại</button>
                    <button class="btn-action invoice" data-action="invoice" data-id="${booking.id}">Hóa đơn PDF</button>
                    ${!isReviewed ? `<button class="btn-action review" data-action="review" data-id="${booking.id}">Viết đánh giá</button>` : ''}
                `;
            } else if (status === 'cancelled') {
                actionButtons = `
                    <button class="btn-action" data-action="detail" data-id="${booking.id}">Xem chi tiết</button>
                    <button class="btn-action primary" data-action="rebook" data-id="${booking.id}">Đặt lại</button>
                `;
            }

            return `
            <div class="booking-card ${selectedBookingId === booking.id ? 'selected' : ''}" data-booking-id="${booking.id}">
                <img class="booking-card-img" src="${img}" alt="${escapeHtml(hotel.name || 'Hotel')}" onerror="this.src='/images/hotel-placeholder.jpg'">
                <div class="booking-card-content">
                    <div class="booking-card-top">
                        <span class="booking-card-code">BK-${booking.id}</span>
                        <span class="status-badge ${statusClass}">${statusText}</span>
                    </div>
                    <div class="booking-card-name">${escapeHtml(hotel.name || 'Khách sạn')}</div>
                    <div class="booking-card-location">
                        <i class="fa-solid fa-location-dot"></i> ${escapeHtml(booking.hotel?.city || '')} ${booking.hotel?.district ? ', ' + escapeHtml(booking.hotel.district) : ''}
                    </div>
                    <div class="booking-card-meta">
                        <span><i class="fa-regular fa-calendar"></i> ${checkIn} → ${checkOut}</span>
                        <span><i class="fa-solid fa-moon"></i> ${nights} đêm</span>
                        <span><i class="fa-solid fa-user"></i> ${guests} khách</span>
                        <span><i class="fa-solid fa-door-open"></i> ${rooms} phòng</span>
                    </div>
                    <div class="booking-card-footer">
                        <div class="booking-price">${totalPrice}</div>
                        <div class="booking-actions">
                            ${actionButtons}
                        </div>
                    </div>
                </div>
            </div>`;
        }).join('');

        // Attach card-level click for detail (but not on buttons)
        bookingList.querySelectorAll('.booking-card').forEach(card => {
            card.addEventListener('click', function(e) {
                if (e.target.closest('button')) return; // ignore button clicks
                const id = parseInt(this.dataset.bookingId);
                openDetail(id);
            });
        });

        // Attach action buttons directly
        bookingList.querySelectorAll('.btn-action').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const action = this.dataset.action;
                const id = parseInt(this.dataset.id);
                handleAction(action, id);
            });
        });
    }

    // ===========================
    // ACTION HANDLER - FIXED with invoice case
    // ===========================
    function handleAction(action, bookingId) {
        switch(action) {
            case 'detail':   openDetail(bookingId); break;
            case 'cancel':   openCancelConfirm(bookingId); break;
            case 'rebook':   rebook(bookingId); break;
            case 'review':   openReviewModal(bookingId); break;
            case 'invoice':  downloadInvoice(bookingId); break;
        }
    }

    // ===========================
    // DETAIL PANEL
    // ===========================
    async function openDetail(bookingId) {
        selectedBookingId = bookingId;
        highlightCard(bookingId);
        detailPanel.classList.add('show');
        showLoadingInDetail();

        try {
            const res = await api(`/bookings/${bookingId}`);
            if (!res.ok) throw new Error('Lỗi tải chi tiết');
            const booking = await res.json();
            renderDetail(booking);
        } catch (err) {
            detailContent.innerHTML = `<p style="color:var(--danger); padding:20px;">${escapeHtml(err.message)}</p>`;
        }
    }

    function renderDetail(booking) {
        const hotel = booking.hotel || {};
        const img = hotel.images?.[0]?.image_path || `https://picsum.photos/seed/${hotel.id}/400/200`;
        const br = booking.booking_rooms?.[0] || {};
        const roomTypeName = br.room_type?.name || br.room?.name || 'N/A';
        const nights = br.nights || 1;
        const guests = booking.num_guests || 1;
        const rooms = booking.booking_rooms?.length || 1;
        const paymentMethod = booking.payment?.payment_method || 'cash';
        const note = booking.special_request || 'Không có';

        detailContent.innerHTML = `
            <img class="detail-img" src="${img}" alt="${escapeHtml(hotel.name || '')}">
            <div class="detail-info-grid">
                <div class="detail-item"><span class="detail-label">Mã booking</span><span class="detail-value">BK-${booking.id}</span></div>
                <div class="detail-item"><span class="detail-label">Trạng thái</span><span class="detail-value"><span class="status-badge status-${booking.status}">${getStatusText(booking.status)}</span></span></div>
                <div class="detail-item"><span class="detail-label">Nhận phòng</span><span class="detail-value">${formatDate(booking.check_in)}</span></div>
                <div class="detail-item"><span class="detail-label">Trả phòng</span><span class="detail-value">${formatDate(booking.check_out)}</span></div>
                <div class="detail-item"><span class="detail-label">Số đêm</span><span class="detail-value">${nights}</span></div>
                <div class="detail-item"><span class="detail-label">Loại phòng</span><span class="detail-value">${escapeHtml(roomTypeName)}</span></div>
                <div class="detail-item"><span class="detail-label">Số phòng</span><span class="detail-value">${rooms}</span></div>
                <div class="detail-item"><span class="detail-label">Số khách</span><span class="detail-value">${guests}</span></div>
                <div class="detail-item"><span class="detail-label">Thanh toán</span><span class="detail-value">${paymentMethod === 'banking' ? 'Chuyển khoản' : 'Tiền mặt'}</span></div>
                <div class="detail-item" style="grid-column: span 2;"><span class="detail-label">Ghi chú</span><span class="detail-value">${escapeHtml(note)}</span></div>
            </div>
            <hr class="detail-divider">
            <div class="detail-total">Tổng tiền: ${formatPrice(booking.total_price)}</div>
        `;
    }

    function getStatusText(status) {
        const map = { pending: 'Chờ xác nhận', confirmed: 'Đã xác nhận', completed: 'Hoàn thành', cancelled: 'Đã huỷ' };
        return map[status] || status;
    }

    function showLoadingInDetail() {
        detailContent.innerHTML = '<p style="text-align:center; padding:40px;">Đang tải...</p>';
    }

    function closeDetail() {
        detailPanel.classList.remove('show');
        selectedBookingId = null;
        highlightCard(null);
    }

    function highlightCard(id) {
        bookingList.querySelectorAll('.booking-card').forEach(c => c.classList.remove('selected'));
        if (id) {
            const card = bookingList.querySelector(`[data-booking-id="${id}"]`);
            if (card) card.classList.add('selected');
        }
    }

    if (btnCloseDetail) btnCloseDetail.addEventListener('click', closeDetail);

    // ===========================
    // CANCEL CONFIRM
    // ===========================
    async function openCancelConfirm(bookingId) {
        if (!confirm('Bạn có chắc muốn huỷ đặt phòng này?')) return;
        try {
            const res = await api(`/bookings/${bookingId}/cancel`, { method: 'POST' });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Lỗi huỷ');
            showToast('Đã huỷ đặt phòng thành công', 'success');
            fetchBookings(pagination.currentPage);
        } catch (err) {
            showToast(err.message, 'error');
        }
    }

    // ===========================
    // REBOOK
    // ===========================
    function rebook(bookingId) {
        const booking = bookings.find(b => b.id === bookingId);
        const hotelId = booking?.hotel_id || booking?.hotel?.id;
        if (hotelId) {
            window.location.href = `/hotels/${hotelId}`;
        } else {
            window.location.href = '/';
        }
    }

    // ===========================
    // INVOICE DOWNLOAD - NEW FUNCTION
    // ===========================
    async function downloadInvoice(bookingId) {
        const btn = document.querySelector(`[data-action="invoice"][data-id="${bookingId}"]`);
        if (btn) { btn.disabled = true; btn.textContent = 'Đang tải...'; }

        try {
            const token = localStorage.getItem('token');
            const res = await fetch(`/api/invoices/${bookingId}/pdf`, {
                headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/pdf' }
            });

            if (!res.ok) {
                const err = await res.json().catch(() => ({}));
                throw new Error(err.message || 'Không thể tải hóa đơn');
            }

            const blob = await res.blob();
            const url  = URL.createObjectURL(blob);
            const a    = document.createElement('a');
            a.href     = url;
            a.download = `hoadon_BK-${bookingId}.pdf`;
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
            showToast('Tải hóa đơn thành công!', 'success');
        } catch (err) {
            showToast(err.message, 'error');
        } finally {
            if (btn) { btn.disabled = false; btn.textContent = 'Hóa đơn PDF'; }
        }
    }

    // ===========================
    // REVIEW MODAL - IMPROVED UI + hotel_id fix
    // ===========================
    let selectedReviewBookingId = null;
    let currentRating = 0;

    function openReviewModal(bookingId) {
        selectedReviewBookingId = bookingId;
        currentRating = 0;

        reviewModalOverlay.innerHTML = '';
        reviewModalOverlay.style.display = 'none';
        
        const booking = bookings.find(b => b.id === bookingId);
        if (!booking) return;
        const hotel = booking.hotel || {};
        const hotelName = hotel.name || 'Khách sạn';
        const hotelImg = hotel.images?.[0]?.image_path || `https://picsum.photos/seed/${hotel.id || 1}/48/48`;

        reviewModalOverlay.innerHTML = `
            <div class="modal-overlay" id="reviewOverlay">
                <div class="modal-content">
                    <h3 class="modal-title">Viết đánh giá</h3>
                    <div class="review-modal-hotel">
                        <img src="${hotelImg}" alt="${escapeHtml(hotelName)}" onerror="this.src='/images/hotel-placeholder.jpg'">
                        <div>
                            <div class="review-modal-hotel-name">${escapeHtml(hotelName)}</div>
                            <div class="review-modal-hotel-code">#BK-${bookingId}</div>
                        </div>
                    </div>
                    <div class="review-stars-label">Đánh giá của bạn</div>
                    <div class="review-stars" id="modalStars">
                        ${[1,2,3,4,5].map(i => `<button data-star="${i}" title="${i} sao"><i class="fa-solid fa-star"></i></button>`).join('')}
                    </div>
                    <textarea class="review-text" id="reviewText" placeholder="Chia sẻ trải nghiệm của bạn về khách sạn..." maxlength="500" oninput="document.getElementById('reviewCharCount') && (document.getElementById('reviewCharCount').textContent=this.value.length+'/500')"></textarea>
                    <div class="review-char-count"><span id="reviewCharCount">0</span>/500</div>
                    <div class="modal-actions">
                        <button class="btn-modal" id="btnCancelReview">Huỷ</button>
                        <button class="btn-modal primary" id="btnSubmitReview">Gửi đánh giá</button>
                    </div>
                </div>
            </div>
        `;
        reviewModalOverlay.style.display = 'block';

        const overlay = reviewModalOverlay.querySelector('#reviewOverlay');
        if (overlay) {
            void overlay.offsetWidth; // reflow để animation chạy
            overlay.classList.add('open');
        }

        // Star interaction
        const stars = reviewModalOverlay.querySelectorAll('#modalStars button');
        stars.forEach(btn => {
            btn.addEventListener('mouseover', function() {
                const hovered = parseInt(this.dataset.star);
                stars.forEach((b, idx) => b.classList.toggle('active', idx < hovered));
            });
            btn.addEventListener('click', function() {
                currentRating = parseInt(this.dataset.star);
                stars.forEach((b, idx) => b.classList.toggle('active', idx < currentRating));
            });
        });
        const starsContainer = reviewModalOverlay.querySelector('#modalStars');
        if (starsContainer) {
            starsContainer.addEventListener('mouseleave', function() {
                stars.forEach((b, idx) => b.classList.toggle('active', idx < currentRating));
            });
        }

        const cancelBtn = document.getElementById('btnCancelReview');
        const submitBtn = document.getElementById('btnSubmitReview');
        if (cancelBtn) cancelBtn.addEventListener('click', closeReviewModal);
        if (submitBtn) submitBtn.addEventListener('click', submitReview);
        
    }

    function closeReviewModal() {
        if (reviewModalOverlay) {
            reviewModalOverlay.innerHTML = '';
            reviewModalOverlay.style.display = 'none';
        }
        selectedReviewBookingId = null;
    }

    async function submitReview() {
        if (currentRating === 0) {
            showToast('Vui lòng chọn số sao', 'error');
            return;
        }
        const comment = document.getElementById('reviewText')?.value.trim() || '';
        const btn = document.getElementById('btnSubmitReview');
        if (btn) {
            btn.disabled = true;
            btn.textContent = 'Đang gửi...';
        }

        try {
            const reviewBooking = bookings.find(b => b.id === selectedReviewBookingId);
            const hotelId = reviewBooking?.hotel_id || reviewBooking?.hotel?.id;
            if (!hotelId) throw new Error('Không tìm thấy thông tin khách sạn');

            const res = await api('/reviews', {
                method: 'POST',
                body: JSON.stringify({
                    hotel_id:   hotelId,
                    booking_id: selectedReviewBookingId,
                    rating:     currentRating,
                    comment:    comment
                })
            });
            const data = await res.json();
            if (!res.ok) throw new Error(data.message || 'Lỗi gửi đánh giá');

            // Mark as reviewed
            reviewedBookingIds.add(selectedReviewBookingId);
            showToast('Đánh giá của bạn đã được gửi thành công', 'success');
            closeReviewModal();
            // Re-render list to hide review button
            renderBookingList(bookings);
            // If detail open, refresh detail
            if (selectedBookingId) openDetail(selectedBookingId);
        } catch (err) {
            showToast(err.message, 'error');
        } finally {
            if (btn) {
                btn.disabled = false;
                btn.textContent = 'Gửi đánh giá';
            }
        }
    }

    // ===========================
    // PAGINATION
    // ===========================
    function renderPagination() {
        if (!pagination.lastPage || pagination.lastPage <= 1) {
            paginationContainer.innerHTML = '';
            return;
        }
        const current = pagination.currentPage;
        const last = pagination.lastPage;
        let html = '<ul class="pagination">';
        // Previous
        html += `<li class="page-item ${current === 1 ? 'disabled' : ''}">
            <span class="page-link" data-page="${current-1}"><i class="fa-solid fa-chevron-left"></i></span></li>`;
        // Pages (simple: first, last, and current +/- 1)
        const pages = new Set([1, last, current, current-1, current+1]);
        const sorted = Array.from(pages).filter(p => p >= 1 && p <= last).sort((a,b)=>a-b);
        for (const page of sorted) {
            html += `<li class="page-item ${page === current ? 'active' : ''}">
                <span class="page-link" data-page="${page}">${page}</span></li>`;
        }
        // Next
        html += `<li class="page-item ${current === last ? 'disabled' : ''}">
            <span class="page-link" data-page="${current+1}"><i class="fa-solid fa-chevron-right"></i></span></li>`;
        html += '</ul>';
        paginationContainer.innerHTML = html;

        // Attach events
        paginationContainer.querySelectorAll('.page-link').forEach(link => {
            link.addEventListener('click', function() {
                const page = parseInt(this.dataset.page);
                if (page && page >= 1 && page <= last && page !== current) {
                    selectedBookingId = null;
                    closeDetail();
                    fetchBookings(page);
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                }
            });
        });
    }

    // ===========================
    // TOAST
    // ===========================
    function showToast(message, type = 'success') {
        const existing = document.querySelector('.toast');
        if (existing) existing.remove();
        const toast = document.createElement('div');
        toast.className = 'toast';
        toast.style.cssText = `
            position: fixed; bottom: 24px; right: 24px;
            background: ${type === 'error' ? '#ef4444' : '#22c55e'};
            color: #fff; padding: 14px 20px; border-radius: 12px;
            font-size: 14px; font-weight: 600; z-index: 9999;
            box-shadow: 0 8px 16px rgba(0,0,0,.2);
            animation: toastIn .3s ease;
        `;
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }

    function showLoading() {
        bookingList.innerHTML = '<p style="text-align:center; padding:40px; color: var(--text-muted);">Đang tải...</p>';
        paginationContainer.innerHTML = '';
    }

    // ===========================
    // INIT FETCH REVIEWED BOOKINGS
    // ===========================
    async function fetchReviewedBookings() {
        try {
            const res = await api('/reviews/my?per_page=100');
            const data = await res.json();
            if (data.data) {
                data.data.forEach(review => {
                    if (review.booking_id) reviewedBookingIds.add(review.booking_id);
                });
            } else if (Array.isArray(data)) {
                data.forEach(review => { if (review.booking_id) reviewedBookingIds.add(review.booking_id); });
            }
        } catch (e) {
            console.warn('Could not load reviews');
        }
    }

    // ===========================
    // INIT
    // ===========================
    async function init() {
        await fetchReviewedBookings();
        fetchBookings();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>