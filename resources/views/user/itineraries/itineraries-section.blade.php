{{-- ============================================================
    itineraries-section.blade.php
    UI cho Lịch trình AI – Form tạo, Kết quả, Danh sách
============================================================ --}}

<style>
    /* ============================================================
       ITINERARIES PAGE – ENHANCED STYLES
       Dùng biến CSS từ user.blade.php
       ============================================================ */
    .iti-page-header {
        margin-bottom: 28px;
    }
    .iti-page-header h1 {
        font-family: var(--font-heading);
        font-size: 26px;
        font-weight: 600;
        color: var(--text-primary);
        margin-bottom: 4px;
    }
    .iti-page-header p {
        font-size: 13.5px;
        color: var(--text-muted);
    }

    /* ========== SECTION 1: FORM CARD ========== */
    .iti-form-card {
        background: var(--bg-card);
        border-radius: var(--radius);
        border: 1px solid var(--border);
        box-shadow: var(--shadow-sm);
        margin-bottom: 32px;
        display: flex;
        flex-wrap: wrap;
    }
    .iti-form-main {
        flex: 1 1 65%;
        padding: 24px 28px;
        min-width: 300px;
    }
    .iti-form-tips {
        flex: 1 1 35%;
        padding: 24px;
        background: #f8fafc;
        border-left: 1px solid var(--border);
        border-radius: 0 var(--radius) var(--radius) 0;
        min-width: 260px;
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    .iti-form-title {
        display: flex;
        align-items: center;
        gap: 8px;
        font-family: var(--font-heading);
        font-size: 18px;
        font-weight: 600;
        margin-bottom: 20px;
    }
    .iti-form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 18px;
    }
    .iti-form-group {
        display: flex;
        flex-direction: column;
    }
    .iti-form-group.full-width {
        grid-column: span 2;
    }
    .iti-form-label {
        font-size: 12.5px;
        font-weight: 600;
        margin-bottom: 6px;
        color: var(--text-primary);
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .iti-form-input,
    .iti-form-select,
    .iti-form-textarea {
        border: 1.5px solid var(--border);
        border-radius: var(--radius-sm);
        padding: 10px 14px;
        font-size: 13px;
        font-family: var(--font-body);
        color: var(--text-primary);
        background: #fff;
        transition: var(--transition);
        outline: none;
    }
    .iti-form-input:focus,
    .iti-form-select:focus,
    .iti-form-textarea:focus {
        border-color: var(--primary-light);
        box-shadow: 0 0 0 3px rgba(59,130,246,.15);
    }
    .iti-budget-row {
        display: flex;
        gap: 8px;
        align-items: center;
    }
    .iti-budget-row input {
        flex: 1;
    }
    .iti-tags-wrapper {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        min-height: 36px;
        padding: 4px;
        border: 1.5px solid var(--border);
        border-radius: var(--radius-sm);
        background: #fff;
        align-items: center;
        cursor: text;
    }
    .iti-tag {
        background: rgba(59,130,246,0.1);
        color: var(--primary);
        border-radius: 20px;
        padding: 4px 10px;
        font-size: 12px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 4px;
    }
    .iti-tag i {
        font-size: 10px;
        cursor: pointer;
    }
    .iti-tag-input {
        flex: 1;
        min-width: 120px;
        border: none;
        outline: none;
        font-size: 13px;
        padding: 4px;
    }
    .advanced-toggle {
        margin-top: 16px;
    }
    .advanced-toggle button {
        background: none;
        border: none;
        font-size: 12.5px;
        font-weight: 600;
        color: var(--primary);
        cursor: pointer;
    }
    .advanced-fields {
        display: none;
        margin-top: 18px;
        padding-top: 18px;
        border-top: 1px dashed var(--border);
    }
    .advanced-fields.show {
        display: block;
    }

    /* Generate Button */
    .btn-generate {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: 100%;
        margin-top: 24px;
        padding: 14px 24px;
        background: linear-gradient(135deg, var(--primary, #3b82f6), #2563eb);
        color: #fff;
        font-size: 15px;
        font-weight: 600;
        font-family: var(--font-body);
        border: none;
        border-radius: var(--radius-sm, 8px);
        cursor: pointer;
        transition: var(--transition, all .2s);
        box-shadow: 0 4px 14px rgba(59,130,246,.35);
    }
    .btn-generate:hover:not(:disabled) {
        background: linear-gradient(135deg, #2563eb, #1d4ed8);
        box-shadow: 0 6px 18px rgba(59,130,246,.45);
        transform: translateY(-1px);
    }
    .btn-generate:disabled {
        opacity: .65;
        cursor: not-allowed;
        transform: none;
    }

    /* AI Tips */
    .iti-tips-card {
        background: #fff;
        border-radius: var(--radius-sm);
        padding: 18px;
        border: 1px solid var(--border-light);
    }
    .iti-tips-card h4 {
        font-size: 14px;
        font-weight: 700;
        margin-bottom: 12px;
    }
    .iti-tips-list {
        list-style: none;
        padding: 0;
        margin: 0 0 16px;
    }
    .iti-tips-list li {
        font-size: 13px;
        margin-bottom: 6px;
        display: flex;
        align-items: center;
        gap: 6px;
    }
    .iti-tips-list i {
        color: var(--primary);
        font-size: 14px;
    }
    .iti-suggest-dest {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    .iti-suggest-chip {
        font-size: 12px;
        background: var(--border-light);
        border-radius: 20px;
        padding: 5px 12px;
        border: none;
        cursor: pointer;
        transition: var(--transition);
    }
    .iti-suggest-chip:hover {
        background: var(--primary-light);
        color: var(--primary);
    }

    /* ========== SECTION 2: RESULT ========== */
    #itineraryResult {
        display: none;
        margin-bottom: 32px;
        animation: slideDown .4s ease;
    }
    @keyframes slideDown {
        from { opacity: 0; transform: translateY(-20px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .iti-result-banner {
        background: linear-gradient(135deg, #059669, #10b981);
        color: #fff;
        border-radius: var(--radius);
        padding: 24px 28px;
        margin-bottom: 20px;
        display: flex;
        flex-wrap: wrap;
        justify-content: space-between;
        align-items: center;
    }
    .iti-banner-main h2 {
        font-family: var(--font-heading);
        font-size: 24px;
        margin: 0 0 8px;
    }
    .iti-banner-meta {
        font-size: 13px;
        opacity: .9;
    }
    .iti-stats-bar {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 16px;
        margin-bottom: 24px;
    }
    .iti-stat {
        background: #fff;
        border-radius: var(--radius-sm);
        border: 1px solid var(--border);
        padding: 14px;
        text-align: center;
    }
    .iti-stat span {
        display: block;
        font-size: 13px;
        color: var(--text-secondary);
    }
    .iti-stat strong {
        font-size: 18px;
        font-weight: 700;
        color: var(--text-primary);
    }
    .iti-stat.cost strong { color: #16a34a; }
    .iti-stat.sights strong { color: #0284c7; }
    .iti-stat.meals strong { color: #ea580c; }
    .iti-stat.rating strong { color: #f59e0b; }

    .iti-detail-layout {
        display: flex;
        gap: 20px;
        align-items: flex-start;
    }
    .iti-timeline-col {
        flex: 1 1 60%;
        min-width: 0;
    }
    .iti-chat-col {
        flex: 1 1 40%;
        min-width: 300px;
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 18px;
        position: sticky;
        top: 90px;
    }
    .cskh-header-dot {
        width: 8px; height: 8px;
        background: #22c55e;
        border-radius: 50%;
        display: inline-block;
        margin-left: 6px;
        vertical-align: middle;
    }
    .cskh-hotel-card {
        margin-top: 8px;
        padding: 8px 12px;
        background: #f0f9ff;
        border: 1px solid #bae6fd;
        border-radius: 10px;
        font-size: 12px;
        color: #0369a1;
        cursor: pointer;
    }
    .cskh-hotel-card:hover { background: #e0f2fe; }
    .iti-day-tabs {
        display: flex;
        gap: 8px;
        overflow-x: auto;
        margin-bottom: 20px;
        padding-bottom: 8px;
    }
    .iti-day-tab {
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 600;
        border: 1px solid var(--border);
        background: #fff;
        cursor: pointer;
        white-space: nowrap;
        transition: var(--transition);
    }
    .iti-day-tab.active {
        background: var(--primary);
        color: #fff;
        border-color: var(--primary);
    }
    .iti-timeline-item {
        display: flex;
        gap: 12px;
        margin-bottom: 16px;
    }
    .iti-item-icon {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        background: var(--border-light);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }
    .iti-item-content {
        flex: 1;
    }
    .iti-item-title {
        font-weight: 600;
    }
    .iti-item-meta {
        font-size: 12px;
        color: var(--text-secondary);
    }
    .iti-item-cost {
        font-weight: 600;
        color: #16a34a;
    }
    .iti-chat-messages {
        max-height: 250px;
        overflow-y: auto;
        margin-bottom: 12px;
    }
    .iti-chat-msg {
        margin-bottom: 10px;
        padding: 8px 12px;
        border-radius: 12px;
        max-width: 85%;
        font-size: 13px;
        line-height: 1.4;
    }
    .iti-chat-msg.user {
        background: var(--primary);
        color: #fff;
        margin-left: auto;
    }
    .iti-chat-msg.ai {
        background: #fff;
        border: 1px solid var(--border);
    }
    .iti-chat-actions {
        display: flex;
        gap: 8px;
        margin-top: 6px;
    }
    .iti-chat-actions button {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        border: 1px solid var(--border);
        cursor: pointer;
    }
    .btn-apply {
        background: #16a34a;
        color: #fff;
        border-color: #16a34a;
    }
    .btn-ignore {
        background: #f1f5f9;
        color: var(--text-secondary);
    }
    .iti-action-row {
        display: flex;
        gap: 12px;
        margin-top: 24px;
        flex-wrap: wrap;
    }
    .iti-action-row .btn-action {
        flex: 1;
        padding: 10px;
        border-radius: var(--radius-sm);
        font-weight: 600;
        text-align: center;
        cursor: pointer;
        transition: var(--transition);
    }
    .btn-save-iti {
        background: var(--primary);
        color: #fff;
        border: none;
    }
    .btn-export-pdf {
        background: #fff;
        color: var(--text-primary);
        border: 1px solid var(--border);
    }
    .btn-new-iti {
        background: var(--accent);
        color: #fff;
        border: none;
    }

    /* ========== SECTION 3: LIST ========== */
    .iti-list-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin: 32px 0 16px;
    }
    .iti-list-header h3 {
        font-family: var(--font-heading);
        font-size: 20px;
    }
    .iti-card-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
    }
    .iti-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 18px;
        transition: var(--transition);
        position: relative;
    }
    .iti-card:hover {
        border-color: var(--primary-light);
        box-shadow: var(--shadow-md);
    }
    .iti-card-badge {
        position: absolute;
        top: 12px;
        right: 12px;
        font-size: 11px;
        font-weight: 600;
        padding: 3px 10px;
        border-radius: 20px;
        background: #f1f5f9;
        color: var(--text-muted);
    }
    .iti-card-badge.saved {
        background: #dcfce7;
        color: #16a34a;
    }
    .iti-card-title {
        font-weight: 700;
        margin-bottom: 6px;
        padding-right: 80px;
    }
    .iti-card-dest {
        font-size: 13px;
        color: var(--text-secondary);
        margin-bottom: 8px;
    }
    .iti-card-meta {
        font-size: 12px;
        color: var(--text-muted);
    }
    .iti-card-actions {
        margin-top: 12px;
        text-align: right;
        position: relative;
    }
    .iti-card-menu-btn {
        background: none;
        border: none;
        cursor: pointer;
        font-size: 16px;
        color: var(--text-muted);
    }
    .iti-card-dropdown {
        position: absolute;
        right: 0;
        top: 28px;
        background: #fff;
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        min-width: 140px;
        box-shadow: 0 4px 12px rgba(0,0,0,.08);
        display: none;
        z-index: 10;
    }
    .iti-card-dropdown.show {
        display: block;
    }
    .iti-card-dropdown button {
        display: block;
        width: 100%;
        padding: 8px 14px;
        font-size: 13px;
        background: none;
        border: none;
        text-align: left;
        cursor: pointer;
    }
    .iti-card-dropdown button:hover {
        background: var(--border-light);
    }
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: var(--text-muted);
    }

    /* Skeleton */
    .skeleton-card {
        background: var(--bg-card);
        border-radius: var(--radius);
        padding: 18px;
        box-shadow: var(--shadow-sm);
    }
    .skeleton-line {
        height: 14px;
        background: var(--border-light);
        border-radius: 4px;
        margin-bottom: 10px;
        animation: shimmer 1.5s infinite;
    }
    @keyframes shimmer {
        0% { opacity: 0.5; }
        50% { opacity: 1; }
        100% { opacity: 0.5; }
    }

    /* Confirm modal nhỏ (nếu dùng) */
    .confirm-modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,.4);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 1000;
    }
    .confirm-modal {
        background: #fff;
        border-radius: var(--radius);
        padding: 24px;
        max-width: 400px;
        width: 90%;
    }

    @media (max-width: 768px) {
        .iti-form-main, .iti-form-tips {
            flex: 1 1 100%;
            border-left: none;
            border-radius: 0 0 var(--radius) var(--radius);
        }
        .iti-stats-bar {
            grid-template-columns: 1fr 1fr;
        }
        .iti-detail-layout {
            flex-direction: column;
        }
        .iti-chat-col {
            position: static;
        }
    }
</style>

<div class="iti-page-header">
    <h1>Lịch trình AI</h1>
    <p>Tạo lịch trình du lịch thông minh với trợ lý AI, tinh chỉnh theo ý thích</p>
</div>

{{-- SECTION 1: Form + Tips --}}
<div class="iti-form-card">
    <div class="iti-form-main">
        <div class="iti-form-title"><i class="fa-solid fa-wand-magic-sparkles"></i> Tạo lịch trình mới</div>
        <div class="iti-form-grid">
            <div class="iti-form-group">
                <label class="iti-form-label"><i class="fa-solid fa-map-pin"></i> Điểm đến *</label>
                <input type="text" id="itineraryDestination" class="iti-form-input" placeholder="Đà Nẵng, Hà Nội...">
            </div>
            <div class="iti-form-group">
                <label class="iti-form-label"><i class="fa-solid fa-calendar"></i> Số ngày</label>
                <select id="itineraryDays" class="iti-form-select">
                    <option value="1">1 ngày</option><option value="2">2 ngày</option><option value="3" selected>3 ngày</option>
                    <option value="4">4 ngày</option><option value="5">5 ngày</option><option value="6">6 ngày</option>
                    <option value="7">7 ngày</option><option value="8">8 ngày</option><option value="9">9 ngày</option>
                    <option value="10">10 ngày</option><option value="11">11 ngày</option><option value="12">12 ngày</option>
                    <option value="13">13 ngày</option><option value="14">14 ngày</option><option value="15">15 ngày</option>
                    <option value="16">16 ngày</option><option value="17">17 ngày</option><option value="18">18 ngày</option>
                    <option value="19">19 ngày</option><option value="20">20 ngày</option><option value="21">21 ngày</option>
                </select>
            </div>
            <div class="iti-form-group">
                <label class="iti-form-label"><i class="fa-solid fa-user-group"></i> Số người</label>
                <select id="itineraryPeople" class="iti-form-select">
                    <option value="1">1 người</option><option value="2" selected>2 người</option>
                    <option value="3">3 người</option><option value="4">4 người</option><option value="5">5 người</option>
                    <option value="6">6 người</option><option value="7">7 người</option><option value="8">8 người</option>
                    <option value="9">9 người</option><option value="10">10 người</option>
                    <option value="11">11 người</option><option value="12">12 người</option>
                    <option value="13">13 người</option><option value="14">14 người</option><option value="15">15 người</option>
                    <option value="16">16 người</option><option value="17">17 người</option><option value="18">18 người</option>
                    <option value="19">19 người</option><option value="20">20 người</option>
                </select>
            </div>
            <div class="iti-form-group">
                <label class="iti-form-label"><i class="fa-solid fa-coins"></i> Ngân sách (VNĐ)</label>
                <div class="iti-budget-row">
                    <input type="text" id="itineraryBudgetMin" class="iti-form-input iti-budget-input" placeholder="(VD: 2.000.000)" inputmode="numeric">
                    <span>–</span>
                    <input type="text" id="itineraryBudgetMax" class="iti-form-input iti-budget-input" placeholder="(VD: 4.000.000)" inputmode="numeric">
                </div>
                <div style="font-size:11px; color: var(--text-muted); margin-top: 4px;">Ví dụ: 2.000.000 VND ~ 4.000.000 VND</div>
            </div>
            <div class="iti-form-group">
                <label class="iti-form-label"><i class="fa-solid fa-compass"></i> Phong cách</label>
                <select id="itineraryStyle" class="iti-form-select">
                    <option value="khám phá">Khám phá</option>
                    <option value="nghỉ dưỡng">Nghỉ dưỡng</option>
                    <option value="mạo hiểm">Mạo hiểm</option>
                    <option value="văn hóa">Văn hóa</option>
                    <option value="gia đình">Gia đình</option>
                </select>
            </div>
            <div class="iti-form-group full-width">
                <label class="iti-form-label"><i class="fa-solid fa-heart"></i> Sở thích (nhấn Enter để thêm)</label>
                <div class="iti-tags-wrapper" id="interestsTagsWrapper">
                    <input type="text" class="iti-tag-input" id="interestTagInput" placeholder="VD: ẩm thực, biển, lịch sử">
                </div>
            </div>
        </div>

        <div class="advanced-toggle">
            <button type="button" id="toggleAdvancedBtn"><i class="fa-solid fa-sliders"></i> Tùy chỉnh nâng cao</button>
        </div>
        <div id="advancedFields" class="advanced-fields">
            <div class="iti-form-grid">
                <div class="iti-form-group">
                    <label class="iti-form-label"><i class="fa-solid fa-gauge-high"></i> Nhịp độ</label>
                    <select id="itineraryPace" class="iti-form-select">
                        <option value="relaxed">Thư thả</option>
                        <option value="moderate" selected>Vừa phải</option>
                        <option value="packed">Dày đặc</option>
                    </select>
                </div>
                <div class="iti-form-group">
                    <label class="iti-form-label"><i class="fa-solid fa-car"></i> Phương tiện</label>
                    <input type="text" id="itineraryTransport" class="iti-form-input" placeholder="Xe máy, ô tô...">
                </div>
                <div class="iti-form-group">
                    <label class="iti-form-label"><i class="fa-solid fa-hotel"></i> Khu vực lưu trú</label>
                    <input type="text" id="itineraryStayArea" class="iti-form-input" placeholder="Trung tâm, gần biển...">
                </div>
                <div class="iti-form-group">
                    <label class="iti-form-label"><i class="fa-solid fa-chart-pie"></i> Ưu tiên chi tiêu</label>
                    <input type="text" id="itinerarySpendPriority" class="iti-form-input" placeholder="Ăn uống, khách sạn...">
                </div>
                <div class="iti-form-group">
                    <label class="iti-form-label"><i class="fa-solid fa-star"></i> Điểm must-visit</label>
                    <input type="text" id="itineraryMustVisit" class="iti-form-input" placeholder="Bà Nà Hills...">
                </div>
                <div class="iti-form-group full-width">
                    <label class="iti-form-label"><i class="fa-solid fa-pen"></i> Ghi chú</label>
                    <textarea id="itineraryNotes" class="iti-form-textarea" rows="2" placeholder="Ví dụ: ăn chay, không thích nơi đông đúc"></textarea>
                </div>
            </div>
        </div>

        <button class="btn-generate" id="generateBtn" onclick="generateItinerary()">
            <span id="btnText"><i class="fa-solid fa-robot"></i> Tạo lịch trình với AI</span>
        </button>
    </div>

    <div class="iti-form-tips">
        <div class="iti-tips-card">
            <h4>🤖 AI sẽ làm gì?</h4>
            <ul class="iti-tips-list">
                <li><i class="fa-solid fa-circle-check"></i> Lên lịch trình từng ngày chi tiết</li>
                <li><i class="fa-solid fa-circle-check"></i> Gợi ý điểm tham quan, nhà hàng, khách sạn</li>
                <li><i class="fa-solid fa-circle-check"></i> Tính toán chi phí ước tính</li>
                <li><i class="fa-solid fa-circle-check"></i> Tối ưu lộ trình theo nhịp độ bạn chọn</li>
            </ul>
            <div class="iti-suggest-dest">
                <span style="font-size:12px;color:var(--text-muted);">Gợi ý điểm đến:</span>
                <button class="iti-suggest-chip" onclick="fillDestination('Đà Nẵng')">Đà Nẵng</button>
                <button class="iti-suggest-chip" onclick="fillDestination('Hà Nội')">Hà Nội</button>
                <button class="iti-suggest-chip" onclick="fillDestination('Phú Quốc')">Phú Quốc</button>
            </div>
        </div>
    </div>
</div>

{{-- SECTION 2: Kết quả lịch trình (ẩn ban đầu) --}}
<div id="itineraryResult">
    <!-- JS render vào đây -->
</div>

{{-- SECTION 3: Danh sách lịch trình --}}
<div class="iti-list-header">
    <h3 id="itiListTitle">Lịch trình của bạn</h3>
    <a href="#" onclick="loadItineraryList(); return false;" style="font-size:13px; color:var(--primary);">Xem tất cả</a>
</div>
<div id="itineraryListContainer" class="iti-card-grid">
    <div class="empty-state">Đang tải...</div>
</div>

{{-- Confirm Modal dùng chung --}}
<div id="confirmModal" style="display: none;" class="confirm-modal-overlay">
    <div class="confirm-modal">
        <h4 id="confirmTitle">Xác nhận</h4>
        <p id="confirmMessage"></p>
        <div style="text-align: right; margin-top: 16px;">
            <button class="btn-action" id="confirmCancelBtn" style="background:var(--border-light); margin-right:8px;">Huỷ</button>
            <button class="btn-action" id="confirmOkBtn" style="background:var(--danger); color:red ;">Xoá</button>
        </div>
    </div>
</div>