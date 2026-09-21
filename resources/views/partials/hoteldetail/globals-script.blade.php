<script>
// ============================================================
// 📌 GLOBAL VARIABLES - STATE MANAGEMENT
// ============================================================
// Quản lý trạng thái của quá trình đặt phòng toàn bộ ứng dụng

    // 🎯 Dữ liệu đặt phòng
    let pendingBookingData = null;           // Lưu dữ liệu đặt phòng đang chờ xử lý
    let currentBookingId = null;             // ID của đơn đặt phòng hiện tại
    let currentTotal = 0;                    // Tổng tiền cuối cùng (sau giảm giá)
    let baseTotal = 0;                       // Tổng tiền gốc (trước giảm giá)
    let appliedVoucher = null;               // Voucher đã áp dụng hiện tại

    // 🔄 Trạng thái xử lý
    let isConfirming = false;                // Cờ kiểm tra xem đang xác nhận đơn hàng hay không

    // 🏨 Dữ liệu phòng được chọn
    let modalSelectedRoom = null;            // Phòng được chọn trong modal
    let modalCurrentHotelId = null;          // ID khách sạn trong modal
    let modalCurrentRoomTypeId = null;       // ID loại phòng trong modal
    let modalCurrentPrice = 0;               // Giá phòng trong modal

    // ⏱️ Bộ đếm ngược thời gian (Countdown)
    let modalTimerInterval = null;           // ID của interval đếm ngược
    let modalTimerSeconds = 600;             // 10 phút = 600 giây để hoàn tất đặt phòng

    // 📱 QR Code Timer (cho payment)
    let _qrTimer = null;                     // ID của interval đếm ngược QR
    let _qrSeconds = 0;                      // Thời gian còn lại cho QR code

    // ============================================================
    // 🎟️ VOUCHER CONFIGURATION - CÁC MÃ GIẢM GIÁ CÓ SẴN
    // ============================================================
    // Tất cả các voucher hỗ trợ, có kiểu (phần trăm/cố định), giá trị & điều kiện tối thiểu
    
    const MOCK_VOUCHERS = {
        'SUMMER20': { type: 'percent', value: 20, label: 'Giảm 20%', min: 500000 },
        'WELCOME50K': { type: 'fixed', value: 50000, label: 'Giảm 50.000đ', min: 0 },
        'VIP30': { type: 'percent', value: 30, label: 'Giảm 30% (VIP)', min: 1000000 },
    };

    // ============================================================
    // ⚙️ UTILITY FUNCTIONS - CÁC HÀM TIỆN ÍCH
    // ============================================================

    /**
     * Cuộn mượt đến phần tử HTML cụ thể
     * @param {string} elementId - ID của phần tử cần cuộn đến
     * Dùng khi muốn người dùng focus vào một section nhất định
     */
    function smoothScrollToElement(elementId) {
        const el = document.getElementById(elementId);
        if (!el) return;
        const rect = el.getBoundingClientRect();
        const absTop = rect.top + window.scrollY;
        const centerY = absTop - (window.innerHeight / 2) + (rect.height / 2);
        window.scrollTo({ top: Math.max(0, centerY), behavior: 'smooth' });
    }

    /**
     * Trích xuất thông báo lỗi chi tiết từ response API
     * @param {Object} data - Response data từ server
     * @returns {string} Thông báo lỗi (ưu tiên: lỗi validation → message → stringify)
     * Xử lý 3 trường hợp: có errors, có message, hoặc stringify toàn bộ data
     */
    function getApiErrorMessage(data) {
        if (!data) return 'Thao tác thất bại!';
        if (data.message && data.errors) {
            const errors = Object.values(data.errors).flat();
            return errors.length ? errors[0] : data.message;
        }
        return data.message || (typeof data === 'string' ? data : JSON.stringify(data));
    }

    /**
     * Hiển thị thông báo tạm thời (toast notification)
     * @param {string} message - Nội dung thông báo cần hiển thị
     * Tự động biến mất sau 3 giây, hiển thị ở góc dưới bên phải
     */
    function showToast(message) {
        const toast = document.createElement('div');
        toast.className = 'fixed bottom-4 right-4 bg-[#3A4A5A] text-white px-4 py-2 rounded-xl text-sm z-50 animate-fade-in-up';
        toast.textContent = message;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }

    /**
     * Định dạng ngày tháng từ chuỗi ISO sang định dạng Việt Nam
     * @param {string} str - Chuỗi ngày (ISO format: YYYY-MM-DD)
     * @returns {string} Ngày định dạng (DD/MM/YYYY)
     * Ví dụ: "2026-04-22" → "22/4/2026"
     */
    function formatDate(str) {
        if (!str) return '';
        const d = new Date(str);
        return `${d.getDate()}/${d.getMonth()+1}/${d.getFullYear()}`;
    }

    /**
     * Hàm gọi API chung cho toàn ứng dụng
     * @param {string} endpoint - URL endpoint (có hoặc không dấu /)
     * @param {Object} options - Cấu hình fetch (method, body, headers, v.v)
     * @returns {Promise<Response>} Response từ server
     * 
     * Tự động:
     * - Thêm token Authorization từ localStorage
     * - Thêm headers mặc định (Content-Type, Accept)
     * - Chuẩn hóa endpoint thành /endpoint
     */
    async function api(endpoint, options = {}) {
        options = options || {};
        const token = localStorage.getItem('token');
        const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json', ...options.headers };
        if (token) headers['Authorization'] = `Bearer ${token}`;
        return fetch(endpoint.startsWith('/') ? endpoint : `/${endpoint}`, { ...options, headers });
    }

    // ============================================================
    // 🏨 HOTEL CONTEXT — dữ liệu KS hiện tại truyền từ Blade sang JS
    // ============================================================
    const HOTEL_ID   = {{ $hotel->id }};
    const HOTEL_NAME = @json($hotel->name);
    const HOTEL_AVG_RATING  = {{ $hotel->avg_rating ?? 0 }};
    const HOTEL_REVIEW_COUNT = {{ $hotel->review_count ?? 0 }};
</script>