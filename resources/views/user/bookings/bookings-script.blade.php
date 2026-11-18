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
    let currentTab = 'all', currentSearch = '', bookingRequestSequence = 0;
    let bookings = [];
    let pagination = {};
    let selectedBookingId = null;
    let reviewedBookingIds = new Set(); // track reviewed
    const ownHotelReviews = new Map();

    // ===========================
    // API HELPER
    // ===========================
    async function api(path, options = {}) {
        const token = localStorage.getItem('token');
        const response = await fetch('/api' + path, {
            ...options,
            headers: {
                'Authorization': `Bearer ${token}`,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                ...(options.headers || {})
            }
        });
        if (response.status === 401) window.location.href = '/login';
        return response;
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
    const bookingSearchForm = document.getElementById('bookingSearchForm');
    const bookingSearchInput = document.getElementById('bookingSearch');
    window.addEventListener('aurel:notification',event=>{
        if(event.detail?.type==='review_approved')fetchReviewedBookings().then(()=>renderBookingList(bookings));
        if (['payment_success','booking_success','booking_cancelled','booking_expired'].includes(event.detail?.type)) {
            fetchBookings(pagination.currentPage||1);
            if (selectedBookingId) openDetail(selectedBookingId);
        }
    });

    // ===========================
    // FORMAT HELPERS
    // ===========================
    function roomSummary(booking) {
        const lines = booking.booking_rooms || [];
        const valid = lines.length > 0 && lines.every(line => Number.isSafeInteger(Number(line.quantity)) && Number(line.quantity) > 0);
        return {
            quantity: valid ? lines.reduce((sum, line) => sum + Number(line.quantity), 0) : '—',
            labels: lines.map(line => (line.room_type?.name || 'Loại phòng') + ' × ' + (Number.isSafeInteger(Number(line.quantity)) && Number(line.quantity) > 0 ? line.quantity : '—') + ' phòng').join(', ') || 'Chưa có chi tiết loại phòng',
        };
    }

    function formatDate(dateStr) {
        if (!dateStr || Number.isNaN(Date.parse(dateStr))) return '—';
        return new Date(dateStr).toLocaleDateString('vi-VN', {
            day: '2-digit', month: '2-digit', year: 'numeric'
        });
    }

    function formatPrice(amount) {
        if (amount === null || amount === undefined || amount === '' || !Number.isFinite(Number(amount))) return 'Chưa có thông tin';
        return Number(amount).toLocaleString('vi-VN') + ' VND';
    }

    function escapeHtml(text) {
        return String(text ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function positiveCount(value) {
        const count = Number(value);
        return Number.isSafeInteger(count) && count > 0 ? count : '—';
    }

    function paymentInfo(booking) {
        const payment = booking.payment;
        const method = payment?.payment_method ?? booking.payment_method;
        const status = payment?.display_status ?? payment?.payment_status;
        const methods = { demo:'Thanh toán đặt phòng', cash: 'Tiền mặt', banking: 'Chuyển khoản', vnpay: 'VNPay', sepay: 'Chuyển khoản SePay', card: 'Thẻ', momo: 'MoMo', zalopay: 'ZaloPay' };
        const states = { success: 'Đã thanh toán', pending: 'Chờ thanh toán', failed: 'Thanh toán thất bại', expired: 'Hết hạn thanh toán', refunded: 'Đã hoàn tiền', reconciliation: 'Cần đối soát', cancelled: 'Đã hủy' };
        return {
            method: methods[method] || (method ? 'Chưa xác định (' + String(method) + ')' : 'Chưa xác định'),
            status: states[status] || (status ? 'Chưa xác định (' + String(status) + ')' : 'Chưa có giao dịch'),
            tone: ({success:'success',pending:'warning',failed:'danger',expired:'danger',reconciliation:'warning'})[status] || 'neutral',
        };
    }

    function canRequestCancellation(booking) {
        if (booking.is_demo) return booking.status === 'pending';
        // Both existing flows enforce the 24-hour cutoff. The API remains authoritative.
        const checkIn = Date.parse(booking.check_in);
        return ['pending','confirmed','expired'].includes(booking.status)
            && Number.isFinite(checkIn) && checkIn - Date.now() >= 24 * 60 * 60 * 1000;
    }

    function canWriteReview(booking) {
        const payment=booking.payment;
        return ['confirmed','completed'].includes(booking.status)
            && payment?.payment_status==='success'
            && Number.isFinite(Number(payment.amount))
            && Number(payment.amount)===Number(booking.total_price);
    }

    function resumePaymentLink(booking) {
        const method = booking.payment?.payment_method ?? booking.payment_method;
        const state = booking.payment?.display_status ?? booking.payment?.payment_status;
        const policy = booking.banking_policy;
        const allowed = document.getElementById('bookingHistoryApp').dataset.paymentPage === 'true'
            && booking.status === 'pending' && (state === 'pending' || (method === 'demo' && state === 'failed')) && ['banking','vnpay','demo'].includes(method)
            && policy?.confirmation_allowed === true && Date.parse(policy.payment_deadline) > Date.now();
        return allowed ? `<a class="btn-action primary" data-action="resume" href="/payments/bookings/${positiveCount(booking.id)}">Tiếp tục thanh toán</a>` : '';
    }

    // ===========================
    // FETCH BOOKINGS
    // ===========================
    async function fetchBookings(page = 1) {
        const sequence = ++bookingRequestSequence;
        showLoading();
        try {
            const params = new URLSearchParams({ per_page: 10, page });
            if (currentTab !== 'all') params.append('status', currentTab);
            if (currentSearch) params.append('search', currentSearch);

            const res = await api(`/bookings/my?${params}`);
            if (res.status === 401) { window.location.href = '/login'; return; }
            if (!res.ok) throw new Error('Lỗi tải dữ liệu');
            const data = await res.json();
            if (sequence !== bookingRequestSequence) return;
            bookings = data.data || [];
            pagination = {
                currentPage: data.current_page,
                lastPage: data.last_page,
                total: data.total,
                from: data.from,
                to: data.to,
            };
            await fetchStatusCounts();
            if (sequence !== bookingRequestSequence) return;
            renderBookingList(bookings);
            renderPagination();
            // re-select detail if any
            if (selectedBookingId) {
                highlightCard(selectedBookingId);
            }
        } catch (err) {
            if (sequence !== bookingRequestSequence) return;
            console.error(err);
            bookingList.innerHTML = `<div class="empty-state">
                ${accountIcon('circle-alert')}
                <h2>Không thể tải danh sách đặt phòng</h2>
                <p>Vui lòng kiểm tra kết nối và thử lại.</p><button type="button" class="button secondary" id="retryBookings">Thử lại</button>
            </div>`;
            document.getElementById("retryBookings").onclick = () => fetchBookings(page);
        } finally { if (sequence === bookingRequestSequence) bookingList.setAttribute("aria-busy", "false"); }
    }

    async function fetchStatusCounts() {
        try {
            const statuses = ['pending', 'confirmed', 'completed', 'cancelled', 'expired'];
            const counts = { all: 0 };
            const promises = statuses.map(async status => {
                const res = await api(`/bookings/my?status=${status}&per_page=1`);
                if (!res.ok) throw new Error('Không tải được số đơn');
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
            { key: 'expired', label: 'Hết hạn', count: counts.expired ?? '...' },
        ];

        tabsContainer.innerHTML = tabs.map(tab => `
            <button class="tab-btn ${currentTab === tab.key ? 'active' : ''}" data-tab="${tab.key}" aria-pressed="${currentTab === tab.key}">
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
                ${accountIcon('calendar-check')}
                <h2>Chưa có đặt phòng nào</h2><p>${currentSearch ? 'Không tìm thấy đặt phòng phù hợp với từ khóa này.' : (currentTab === 'all' ? 'Chuyến đi tiếp theo của bạn sẽ xuất hiện tại đây.' : 'Không có đặt phòng phù hợp với bộ lọc này.')}</p>
                <a href="{{ route('home') }}" class="btn-main">Đặt phòng ngay</a>
            </div>`;
            return;
        }

        bookingList.innerHTML = list.map(booking => {
            const hotel = booking.hotel || {};
            const img = HolidayVietImages.cover(hotel);
            const status = booking.status; // pending/confirmed/completed/cancelled
            const ownReview=ownHotelReviews.get(Number(booking.hotel_id||hotel.id));
            const isReviewed = Boolean(ownReview);
            let statusClass = '';
            let statusText = 'Chưa xác định';
            switch(status) {
                case 'pending': statusClass = 'status-pending'; statusText = 'Chờ xác nhận'; break;
                case 'confirmed': statusClass = 'status-confirmed'; statusText = 'Đã xác nhận'; break;
                case 'completed': statusClass = 'status-completed'; statusText = 'Hoàn thành'; break;
                case 'expired': statusClass = 'status-expired'; statusText = 'Hết hạn'; break;
                case 'cancelled': statusClass = 'status-cancelled'; statusText = 'Đã huỷ'; break;
            }
            if (isReviewed && status === 'completed') statusClass = 'status-reviewed';

            const nights = positiveCount(booking.booking_rooms?.[0]?.nights);
            const summary = roomSummary(booking);
            const rooms = summary.quantity;
            const guests = positiveCount(booking.num_guests);
            const totalPrice = formatPrice(booking.total_price);
            const checkIn = formatDate(booking.check_in);
            const checkOut = formatDate(booking.check_out);

            let actionButtons = `<button type="button" class="btn-action" data-action="detail" data-id="${positiveCount(booking.id)}">Xem chi tiết</button>`;
            if (canRequestCancellation(booking)) actionButtons += `<button type="button" class="btn-action danger" data-action="cancel" data-id="${positiveCount(booking.id)}">Hủy đặt phòng</button>`;
            if (['completed','cancelled','expired'].includes(status)) actionButtons += `<button type="button" class="btn-action" data-action="rebook" data-id="${positiveCount(booking.id)}">Đặt lại</button>`;
            if (isReviewed || canWriteReview(booking)) actionButtons += `<button type="button" class="btn-action review" data-action="review" data-id="${positiveCount(booking.id)}">${isReviewed?'Xem đánh giá':'Viết đánh giá'}</button>`;
            if (booking.invoice?.id) actionButtons += `<button type="button" class="btn-action invoice" data-action="invoice" data-id="${positiveCount(booking.id)}">${accountIcon('download')} Tải hóa đơn</button>`;
            actionButtons += resumePaymentLink(booking);
            const payment = paymentInfo(booking);
            return `<article class="booking-card ${selectedBookingId === booking.id ? 'selected' : ''}" data-booking-id="${positiveCount(booking.id)}">
                <img class="booking-card-img" src="${HolidayVietImages.escapeAttribute(img)}" data-accommodation-image alt="${escapeHtml(hotel.name || 'Khách sạn')}">
                <div class="booking-card-content">
                    <div class="booking-card-top"><span class="booking-card-code">BK-${positiveCount(booking.id)}</span><span class="status-badge ${statusClass}">${statusText}</span></div>
                    <h2 class="booking-card-name">${escapeHtml(hotel.name || 'Chưa có tên khách sạn')}</h2>
                    <div class="booking-card-location">${escapeHtml([hotel.city,hotel.district].filter(Boolean).join(', '))}</div>
                    <div class="booking-card-meta">
                        <span>${accountIcon('calendar-days')} ${checkIn} → ${checkOut}</span><span>${nights} đêm · ${guests} khách · ${rooms} phòng</span>
                        <span class="room-line">${escapeHtml(summary.labels)}</span>
                        <span class="payment-line"><strong>Thanh toán:</strong> <span class="payment-status" data-tone="${payment.tone}">${escapeHtml(payment.status)}</span> · ${escapeHtml(payment.method)}</span>
                        ${ownReview?`<span class="payment-line"><strong>Đánh giá:</strong> <span class="payment-status" data-tone="${ownReview.status==='approved'?'success':ownReview.status==='pending'?'warning':'neutral'}">${({pending:'Chờ duyệt',approved:'Đã duyệt',hidden:'Tạm ẩn'})[ownReview.status]||'Đã gửi'}</span></span>`:''}
                    </div>
                    <div class="booking-card-footer"><div class="booking-price">${totalPrice}</div><div class="booking-actions">${actionButtons}</div></div>
                </div></article>`;
        }).join('');

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
        document.getElementById('bookingHistoryApp').classList.add('has-detail');
        document.getElementById('bookingDetailHeading').focus({preventScroll:true});
        if (innerWidth < 1280) detailPanel.scrollIntoView({block:'start',behavior:'smooth'});
        showLoadingInDetail();

        try {
            const res = await api(`/bookings/${bookingId}`);
            if (!res.ok) throw new Error('Lỗi tải chi tiết');
            const payload = await res.json();
            const booking = payload.booking ?? payload;
            if (!booking || !Number.isSafeInteger(Number(booking.id))) throw new Error('Chi tiết không hợp lệ');
            if (selectedBookingId === bookingId) renderDetail(booking);
        } catch (err) {
            if (selectedBookingId !== bookingId) return;
            detailContent.innerHTML = `<p style="color:var(--danger); padding:20px;">${escapeHtml(err.message)}</p>`;
        }
    }

    function renderDetail(booking) {
        const hotel = booking.hotel || {};
        const img = HolidayVietImages.cover(hotel);
        const br = booking.booking_rooms?.[0] || {};
        const roomTypeName = roomSummary(booking).labels;
        const nights = positiveCount(br.nights);
        const guests = positiveCount(booking.num_guests);
        const summary = roomSummary(booking);
        const rooms = summary.quantity;
        const payment = paymentInfo(booking);
        const note = booking.special_request || 'Không có';

        detailContent.innerHTML = `
            <img class="detail-img" src="${HolidayVietImages.escapeAttribute(img)}" data-accommodation-image alt="${escapeHtml(hotel.name || '')}">
            <h3 class="detail-hotel-name">${escapeHtml(hotel.name || 'Chưa có tên khách sạn')}</h3><div class="detail-info-grid">
                <div class="detail-item"><span class="detail-label">Mã booking</span><span class="detail-value">BK-${positiveCount(booking.id)}</span></div>
                <div class="detail-item"><span class="detail-label">Trạng thái</span><span class="detail-value"><span class="status-badge status-${["pending", "confirmed", "completed", "cancelled", "expired"].includes(booking.status) ? booking.status : "cancelled"}">${escapeHtml(getStatusText(booking.status))}</span></span></div>
                <div class="detail-item"><span class="detail-label">Nhận phòng</span><span class="detail-value">${formatDate(booking.check_in)}</span></div>
                <div class="detail-item"><span class="detail-label">Trả phòng</span><span class="detail-value">${formatDate(booking.check_out)}</span></div>
                <div class="detail-item"><span class="detail-label">Số đêm</span><span class="detail-value">${nights}</span></div>
                <div class="detail-item"><span class="detail-label">Loại phòng</span><span class="detail-value">${escapeHtml(roomTypeName)}</span></div>
                <div class="detail-item"><span class="detail-label">Số phòng</span><span class="detail-value">${rooms}</span></div>
                <div class="detail-item"><span class="detail-label">Số khách</span><span class="detail-value">${guests}</span></div>
                <div class="detail-item"><span class="detail-label">Phương thức</span><span class="detail-value">${escapeHtml(payment.method)}</span></div>
                <div class="detail-item"><span class="detail-label">Thanh toán</span><span class="detail-value"><span class="payment-status" data-tone="${payment.tone}">${escapeHtml(payment.status)}</span></span></div>
                <div class="detail-item"><span class="detail-label">Hóa đơn</span><span class="detail-value">${booking.invoice?.id ? escapeHtml(booking.is_demo ? 'AUREL-HD-'+String(booking.id).padStart(6,'0') : booking.invoice.invoice_no) : 'Chưa phát hành'}</span></div>
                <div class="detail-item" style="grid-column: span 2;"><span class="detail-label">Ghi chú</span><span class="detail-value">${escapeHtml(note)}</span></div>
            </div>
            <hr class="detail-divider">
            <div class="detail-total-row"><span>Tổng thanh toán</span><strong class="detail-total-price">${formatPrice(booking.total_price)}</strong></div><div class="detail-actions">${resumePaymentLink(booking)}${booking.invoice?.id ? `<button type="button" class="btn-action invoice" data-action="invoice" data-id="${positiveCount(booking.id)}">${accountIcon('download')} Tải hóa đơn</button>` : ''}</div>
        `;
        detailContent.querySelector('[data-action=invoice]')?.addEventListener('click', () => downloadInvoice(booking.id));
    }

    function getStatusText(status) {
        const map = { pending: 'Chờ xác nhận', confirmed: 'Đã xác nhận', completed: 'Hoàn thành', cancelled: 'Đã huỷ', expired: 'Hết hạn' };
        return map[status] || status;
    }

    function showLoadingInDetail() {
        detailContent.innerHTML = accountLoading('Đang tải chi tiết…');
    }

    function closeDetail() {
        detailPanel.classList.remove('show');
        document.getElementById('bookingHistoryApp').classList.remove('has-detail');
        bookingList.querySelector(`[data-action=detail][data-id="${selectedBookingId}"]`)?.focus({preventScroll:true});
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

    bookingSearchForm?.addEventListener('submit', function(event) {
        event.preventDefault();
        const nextSearch = bookingSearchInput.value.trim();
        if (nextSearch === currentSearch) return;
        currentSearch = nextSearch;
        selectedBookingId = null;
        closeDetail();
        fetchBookings(1);
    });

    // ===========================
    // CANCEL CONFIRM
    // ===========================
    function openCancelConfirm(bookingId) {
        showConfirmModal('Hủy đặt phòng này?', 'Yêu cầu sẽ được kiểm tra theo chính sách hủy của đặt phòng. Vui lòng xem lại thông tin trước khi xác nhận.', async () => {
            try {
                const res = await api(`/bookings/${bookingId}/cancel`, {method:'POST'});
                const data = await res.json();
                if (!res.ok) throw new Error(data.message || 'Chưa hủy được đặt phòng.');
                showToast('Đã hủy đặt phòng thành công', 'success');
                closeDetail(); await fetchBookings(pagination.currentPage); return true;
            } catch (error) { showToast(error.message || 'Lỗi kết nối', 'error'); return false; }
        }, {confirmText:'Hủy đặt phòng'});
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
        const originalLabel = btn?.innerHTML;
        if (btn) { btn.disabled = true; btn.textContent = 'Đang tải...'; }

        try {
            const token = localStorage.getItem('token');
            const res = await fetch(`/api/invoices/${bookingId}/pdf`, {
                headers: { 'Authorization': `Bearer ${token}`, 'Accept': 'application/pdf' }
            });
            if (res.status === 401) { window.location.href = '/login'; return; }

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
            if (btn) { btn.disabled = false; btn.innerHTML = originalLabel; }
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
        const hotelImg = HolidayVietImages.cover(hotel);
        const existingReview=ownHotelReviews.get(Number(booking.hotel_id||hotel.id));
        if(existingReview){
            reviewModalOverlay.innerHTML=`<div class="modal-overlay open" id="reviewOverlay" role="dialog" aria-modal="true" aria-labelledby="reviewTitle"><div class="modal-content"><h3 id="reviewTitle" class="modal-title">Đánh giá của bạn</h3><p>${escapeHtml(hotelName)}</p><p class="section-gap"><strong>${Number(existingReview.rating)} / 5 sao</strong> · ${escapeHtml(({pending:'Chờ Admin duyệt',approved:'Đã duyệt',hidden:'Tạm ẩn'})[existingReview.status]||'Đã gửi')}</p><p class="section-gap" style="white-space:pre-wrap;overflow-wrap:anywhere">${escapeHtml(existingReview.comment||'Không có nhận xét.')}</p><div class="modal-actions"><button type="button" class="btn-modal primary" id="btnCancelReview">Đóng</button></div></div></div>`;
            reviewModalOverlay.style.display='block';document.body.classList.add('modal-open');document.getElementById('btnCancelReview').addEventListener('click',closeReviewModal);document.getElementById('btnCancelReview').focus();return;
        }
        if(!canWriteReview(booking)){showToast('Bạn chỉ có thể viết đánh giá khi đơn đã thanh toán thành công.','error');return;}

        reviewModalOverlay.innerHTML = `
            <div class="modal-overlay" id="reviewOverlay" role="dialog" aria-modal="true" aria-labelledby="reviewTitle">
                <div class="modal-content">
                    <h3 class="modal-title" id="reviewTitle">Viết đánh giá</h3>
                    <div class="review-modal-hotel">
                        <img src="${HolidayVietImages.escapeAttribute(hotelImg)}" data-accommodation-image alt="${escapeHtml(hotelName)}">
                        <div>
                            <div class="review-modal-hotel-name">${escapeHtml(hotelName)}</div>
                            <div class="review-modal-hotel-code">#BK-${bookingId}</div>
                        </div>
                    </div>
                    <div class="review-stars-label">Đánh giá của bạn</div>
                    <div class="review-stars" id="modalStars">
                        ${[1,2,3,4,5].map(i => `<button type="button" data-star="${i}" aria-label="${i} sao" aria-pressed="false" title="${i} sao">★</button>`).join('')}
                    </div>
                    <label class="form-label" for="reviewText">Nhận xét</label><textarea class="review-text" id="reviewText" placeholder="Chia sẻ trải nghiệm của bạn về khách sạn..." maxlength="500" oninput="document.getElementById('reviewCharCount') && (document.getElementById('reviewCharCount').textContent=this.value.length)"></textarea>
                    <div class="review-char-count"><span id="reviewCharCount">0</span>/500</div>
                    <div class="modal-actions">
                        <button class="btn-modal" id="btnCancelReview">Huỷ</button>
                        <button class="btn-modal primary" id="btnSubmitReview">Gửi đánh giá</button>
                    </div>
                </div>
            </div>
        `;
        reviewModalOverlay.style.display = 'block';
        document.body.classList.add('modal-open');


        const overlay = reviewModalOverlay.querySelector('#reviewOverlay');
        if (overlay) {
            void overlay.offsetWidth; // reflow để animation chạy
            overlay.classList.add('open');
            document.getElementById('btnCancelReview').focus();
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
                stars.forEach(b=>b.setAttribute('aria-pressed',String(Number(b.dataset.star)===currentRating)));
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
        if (document.getElementById('btnSubmitReview')?.disabled) return;
        document.body.classList.remove('modal-open');
        bookingList.querySelector(`[data-action=review][data-id="${selectedReviewBookingId}"]`)?.focus();
        if (reviewModalOverlay) {
            reviewModalOverlay.innerHTML = '';
            reviewModalOverlay.style.display = 'none';
        }
        selectedReviewBookingId = null;
    }

    document.addEventListener('keydown', event => {
        const review = document.getElementById('reviewOverlay');
        if (review?.classList.contains('open')) { if (event.key === 'Escape') closeReviewModal(); else accountTrapFocus(event, review); }
    });
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
            ownHotelReviews.set(Number(hotelId),data.review);
            showToast(data.message || 'Đã gửi đánh giá, đang chờ Admin duyệt.', 'success');
            if (btn) btn.disabled = false;
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
            <button type="button" class="page-link" data-page="${current-1}" aria-label="Trang trước" ${current === 1 ? 'disabled' : ''}>${accountIcon('chevron-left')}</button></li>`;
        // Pages (simple: first, last, and current +/- 1)
        const pages = new Set([1, last, current, current-1, current+1]);
        const sorted = Array.from(pages).filter(p => p >= 1 && p <= last).sort((a,b)=>a-b);
        for (const page of sorted) {
            html += `<li class="page-item ${page === current ? 'active' : ''}">
                <button type="button" class="page-link" data-page="${page}" aria-label="Trang ${page}" ${page === current ? 'aria-current="page"' : ''}>${page}</button></li>`;
        }
        // Next
        html += `<li class="page-item ${current === last ? 'disabled' : ''}">
            <button type="button" class="page-link" data-page="${current+1}" aria-label="Trang sau" ${current === last ? 'disabled' : ''}>${accountIcon('chevron-right')}</button></li>`;
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
    function showLoading() {
        bookingList.setAttribute('aria-busy','true');
        bookingList.innerHTML = accountLoading('Đang tải đặt phòng…');
        paginationContainer.innerHTML = '';
    }

    // ===========================
    // INIT FETCH REVIEWED BOOKINGS
    // ===========================
    async function fetchReviewedBookings() {
        try {
            const res = await api('/reviews/my?per_page=100');
            if(!res.ok)throw new Error('Chưa tải được đánh giá của bạn');
            const data = await res.json();
            const items=Array.isArray(data)?data:(data.data||[]);
            ownHotelReviews.clear();items.forEach(review=>{if(review.hotel_id)ownHotelReviews.set(Number(review.hotel_id),review);});
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
