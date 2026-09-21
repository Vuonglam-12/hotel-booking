<script>
// ============================================================
// itineraries-script.blade.php – Logic cho Lịch trình AI
// ============================================================

// --- Format hiển thị thời gian (chỉ lấy HH:mm) ---
function formatTime(timeStr) {
    if (!timeStr) return '--';
    return timeStr.substring(0, 5); // Cắt lấy HH:mm (VD: 08:00)
}

// --- Utility ---
function escapeHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// --- State ---
let currentItineraryId = null;
let currentItineraryData = null;
let activeDay = 1;
let interestsList = [];
let pendingAiMessageId = null;
let chatHistory = [];           // Lưu lịch sử chat để gửi lên AI

// --- Helper functions ---
function formatCurrency(value) {
    if (!value) return '0đ';
    return Number(value).toLocaleString('vi-VN') + 'đ';
}


// Chỉ cho phép nhập số vào ô ngân sách
function formatBudgetInput(input) {
    const raw = input.value.replace(/\D/g, ''); // Chỉ giữ lại số
    input.value = raw;
}

// Lấy giá trị ngân sách, nếu trống hoặc không hợp lệ thì trả về 0
function parseBudgetValue(input) {
    if (!input || !input.value) return 0;
    return parseInt(input.value) || 0;
}

// --- Tag chip logic ---
function initTagInput() {
    const wrapper = document.getElementById('interestsTagsWrapper');
    const input = document.getElementById('interestTagInput');
    if (!wrapper || !input) return;

    input.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const value = input.value.trim();
            if (value) {
                addInterestTag(value);
                input.value = '';
            }
        }
    });
    renderTags();
}

function addInterestTag(text) {
    if (!text || interestsList.includes(text)) return;
    interestsList.push(text);
    renderTags();
}

function removeInterestTag(index) {
    interestsList.splice(index, 1);
    renderTags();
}

function renderTags() {
    const wrapper = document.getElementById('interestsTagsWrapper');
    const input = document.getElementById('interestTagInput');
    if (!wrapper || !input) return;

    wrapper.querySelectorAll('.iti-tag').forEach(el => el.remove());
    interestsList.forEach((tag, idx) => {
        const span = document.createElement('span');
        span.className = 'iti-tag';
        span.innerHTML = `${tag} <i class="fa-solid fa-xmark" data-index="${idx}"></i>`;
        span.querySelector('i').addEventListener('click', function(e) {
            e.stopPropagation();
            removeInterestTag(parseInt(this.dataset.index));
        });
        wrapper.insertBefore(span, input);
    });
}

function fillDestination(name) {
    document.getElementById('itineraryDestination').value = name;
}

// --- Skeleton loading ---
function showSkeletonInResult() {
    const container = document.getElementById('itineraryResult');
    container.style.display = 'block';
    container.innerHTML = `
        <div class="iti-card-grid">
            <div class="skeleton-card"><div class="skeleton-line" style="width:80%"></div><div class="skeleton-line" style="width:60%"></div></div>
            <div class="skeleton-card"><div class="skeleton-line" style="width:70%"></div><div class="skeleton-line" style="width:90%"></div></div>
            <div class="skeleton-card"><div class="skeleton-line" style="width:75%"></div><div class="skeleton-line" style="width:50%"></div></div>
        </div>
    `;
}

// --- Generate itinerary ---
async function generateItinerary() {
    const destination = document.getElementById('itineraryDestination').value.trim();
    if (!destination) {
        showToast('Vui lòng nhập điểm đến', 'error');
        return;
    }

    const btn = document.getElementById('generateBtn');
    const btnText = document.getElementById('btnText');
    btn.disabled = true;
    btnText.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> AI đang phân tích...';

    showSkeletonInResult();
    document.getElementById('itineraryResult').scrollIntoView({ behavior: 'smooth' });

    const days = parseInt(document.getElementById('itineraryDays').value);
    const people = parseInt(document.getElementById('itineraryPeople').value);
    const budgetMin = parseBudgetValue(document.getElementById('itineraryBudgetMin'));
    const budgetMax = parseBudgetValue(document.getElementById('itineraryBudgetMax'));
    const style = document.getElementById('itineraryStyle').value;
    const pace = document.getElementById('itineraryPace').value;
    const transport = document.getElementById('itineraryTransport').value;
    const stayArea = document.getElementById('itineraryStayArea').value;
    const spendPriority = document.getElementById('itinerarySpendPriority').value;
    const mustVisit = document.getElementById('itineraryMustVisit').value;
    const notes = document.getElementById('itineraryNotes').value;

    const budget = budgetMax || budgetMin || 5000000;

    try {
        const res = await api('/itineraries/generate', {
            method: 'POST',
            body: JSON.stringify({
                destination,
                days,
                people,
                budget,
                trip_style: style,
                pace,
                transport,
                stay_area: stayArea,
                spend_priority: spendPriority,
                must_visit: mustVisit,
                notes,
                interests: interestsList
            })
        });
        const data = await res.json();
        if (data.action === 'regenerate') {
            showToast('AI gợi ý tạo lịch trình mới!', 'info');
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return;
        }
        if (res.ok) {
            showToast(data.message || 'Tạo lịch trình thành công!', 'success');
            showItineraryResult(data.itinerary);
            loadItineraryList();
        } else {
            showToast(data.message || 'Tạo thất bại', 'error');
        }
    } catch (err) {
        showToast('Lỗi kết nối', 'error');
    } finally {
        btn.disabled = false;
        btnText.innerHTML = 'Tạo lịch trình với AI';
    }
}

// --- Render Section 2: Kết quả lịch trình ---
function showItineraryResult(itinerary) {
    currentItineraryId = itinerary.id;
    currentItineraryData = itinerary;
    activeDay = 1;

    const container = document.getElementById('itineraryResult');
    container.style.display = 'block';
    container.innerHTML = buildResultHTML(itinerary);
    container.scrollIntoView({ behavior: 'smooth' });
    attachResultEvents();
    loadSuggestedHotels(itinerary.destination);
    initCskhSession(); // Tạo session CSKH mới
}

function buildResultHTML(itinerary) {
    const totalCost = itinerary.total_cost || 0;
    // FIX 2: Tính số điểm tham quan và bữa ăn từ days
    const allItems = (itinerary.days || []).flatMap(d => d.items || []);
    const totalSights = allItems.filter(i => i.item_type === 'attraction').length;
    const totalMeals  = allItems.filter(i => i.item_type === 'restaurant').length;
    const aiRating = 4.5; // hoặc lấy từ itinerary.ai_rating nếu có

    let daysHtml = '';
    if (itinerary.days && itinerary.days.length) {
        let dayTabs = '';
        itinerary.days.forEach(day => {
            const num = day.day_number;
            dayTabs += `<button class="iti-day-tab ${num === activeDay ? 'active' : ''}" data-day="${num}">Ngày ${num}</button>`;
        });
        daysHtml = `
            <div class="iti-day-tabs">${dayTabs}</div>
            <div id="dayContent">${renderDayItems(itinerary.days.find(d => d.day_number === activeDay))}</div>
        `;
    }

    return `
        <div class="iti-result-banner">
            <div class="iti-banner-main">
                <h2>${escapeHtml(itinerary.destination || 'Điểm đến')} ${itinerary.total_days} ngày</h2>
                <div class="iti-banner-meta">
                    <span>📅 ${itinerary.start_date || 'Chưa có ngày'} · 👥 ${itinerary.num_people || 2} người · 🕒 Tạo ${new Date().toLocaleDateString('vi-VN')}</span>
                </div>
            </div>
            <div><span style="font-size:13px; opacity:.8;">Mã lịch trình: ITI-${itinerary.id}</span></div>
        </div>
        <div class="iti-stats-bar">
            <div class="iti-stat cost"><span>Tổng chi phí</span><strong>${formatCurrency(totalCost)}</strong></div>
            <div class="iti-stat sights"><span>Điểm tham quan</span><strong>${totalSights}</strong></div>
            <div class="iti-stat meals"><span>Bữa ăn</span><strong>${totalMeals}</strong></div>
            <div class="iti-stat rating"><span>Đánh giá AI</span><strong>${aiRating} ⭐</strong></div>
        </div>
        <div class="iti-detail-layout">
            <div class="iti-timeline-col">${daysHtml}</div>
            <div class="iti-chat-col" id="chatPanel">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
                    <h4 style="margin:0; font-size:15px;"><i class="fa-solid fa-headset"></i> Hỗ trợ khách hàng <span class="cskh-header-dot"></span></h4>
                </div>
                <div class="iti-chat-messages" id="chatMessages">
                    <div class="iti-chat-msg ai">👋 Xin chào! Tôi là trợ lý CSKH của HolidayViet. Tôi có thể tư vấn khách sạn, giải đáp thắc mắc và hỗ trợ đặt phòng cho bạn.</div>
                </div>
                <div class="chat-input-group" style="display:flex; gap:8px;">
                    <input type="text" id="chatInput" class="iti-form-input" placeholder="Nhập tin nhắn..." style="flex:1;">
                    <button style="padding:8px 16px; background:var(--primary); color:#fff; border:none; border-radius:var(--radius-sm); cursor:pointer;" onclick="sendCskhMessage()">Gửi</button>
                </div>
            </div>
        </div>
        <div class="iti-action-row">
            <button class="btn-action btn-save-iti" onclick="saveItinerary('${itinerary.id}')">💾 Lưu lịch trình</button>
            <button class="btn-action btn-export-pdf" onclick="exportItineraryPDF('${itinerary.id}')">📄 Xuất PDF</button>
            <button class="btn-action btn-new-iti" onclick="window.scrollTo({top:0, behavior:'smooth'})">✨ Tạo mới</button>
        </div>
    `;
}

function renderDayItems(day) {
    if (!day || !day.items || day.items.length === 0) return '<p>Chưa có hoạt động.</p>';
    const sorted = [...day.items].sort((a, b) => {
        const ta = (a.start_time || '00:00').substring(0, 5);
        const tb = (b.start_time || '00:00').substring(0, 5);
        return ta.localeCompare(tb);
    });
    return sorted.map(item => {

        const iconMap = { hotel: '🏨', restaurant: '🍜', attraction: '🏛', activity: '🎯' };
        const icon = iconMap[item.item_type] || '📍';
        return `
            <div class="iti-timeline-item">
                <div class="iti-item-icon">${icon}</div>
                <div class="iti-item-content">
                    <div class="iti-item-title">${escapeHtml(item.title)} <span style="font-size:11px; background:var(--border-light); padding:1px 6px; border-radius:10px;">${item.item_type}</span></div>
                    <div class="iti-item-meta"><i class="fa-regular fa-clock"></i> ${formatTime(item.start_time)} - ${formatTime(item.end_time)}</div>
                    ${item.description ? `<div class="iti-item-meta">${escapeHtml(item.description)}</div>` : ''}
                    ${item.estimated_cost ? `<div class="iti-item-cost">💰 ${formatCurrency(item.estimated_cost)}</div>` : ''}
                </div>
            </div>
        `;
    }).join('');
}

function attachResultEvents() {
    document.querySelectorAll('.iti-day-tab').forEach(btn => {
        btn.addEventListener('click', function() {
            const day = parseInt(this.dataset.day);
            switchDay(day);
        });
    });
    const chatInput = document.getElementById('chatInput');
    if (chatInput) {
        chatInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') sendCskhMessage();
        });
    }
}

function switchDay(dayNumber) {
    activeDay = dayNumber;
    if (!currentItineraryData || !currentItineraryData.days) return;
    const day = currentItineraryData.days.find(d => d.day_number === dayNumber);
    document.getElementById('dayContent').innerHTML = renderDayItems(day);
    document.querySelectorAll('.iti-day-tab').forEach(btn => {
        btn.classList.toggle('active', parseInt(btn.dataset.day) === dayNumber);
    });
}

// --- CSKH Chat (dùng ChatController) ---
let cskhSessionId = null;   // ID session từ POST /api/chat/start
let cskhHistory   = [];     // Lưu history hiển thị (không cần gửi lên vì ChatController tự lấy từ DB)

// Khởi tạo session CSKH khi kết quả lịch trình hiện ra
async function initCskhSession() {
    cskhSessionId = null;
    cskhHistory   = [];
    try {
        const res = await api('/chat/start', { method: 'POST' });
        const data = await res.json();
        if (res.ok && data.session_id) {
            cskhSessionId = data.session_id;
        }
    } catch (e) {
        console.warn('CSKH: Không khởi tạo được session', e);
    }
}

// Gửi tin nhắn CSKH
async function sendCskhMessage() {
    const input = document.getElementById('chatInput');
    const message = input.value.trim();
    if (!message) return;
    input.value = '';

    const chatDiv = document.getElementById('chatMessages');

    // Hiển thị tin user
    chatDiv.innerHTML += `<div class="iti-chat-msg user">${escapeHtml(message)}</div>`;
    chatDiv.scrollTop = chatDiv.scrollHeight;

    // Nếu chưa có session thì tạo mới
    if (!cskhSessionId) {
        await initCskhSession();
    }

    // Loading
    const loadingId = 'cskh-loading-' + Date.now();
    chatDiv.innerHTML += `<div class="iti-chat-msg ai" id="${loadingId}">⏳ Đang xử lý...</div>`;
    chatDiv.scrollTop = chatDiv.scrollHeight;

    try {
        const res = await api(`/chat/${cskhSessionId}/message`, {
            method: 'POST',
            body: JSON.stringify({ message })
        });

        document.getElementById(loadingId)?.remove();

        if (!res.ok) {
            chatDiv.innerHTML += `<div class="iti-chat-msg ai">❌ Xin lỗi, hệ thống đang bận. Vui lòng thử lại!</div>`;
            chatDiv.scrollTop = chatDiv.scrollHeight;
            return;
        }

        const data = await res.json();
        const reply = (data.message || 'Xin lỗi, tôi chưa hiểu yêu cầu của bạn.')
            .replace(/\n/g, '<br>')
            .replace(/- /g, '<br>• ');

        // Nếu bot gợi ý khách sạn → hiển thị card nhỏ
        let hotelCard = '';
        if (data.hotel) {
            const h = data.hotel;
            hotelCard = `
                <div class="cskh-hotel-card" onclick="window.location.href='/hotels/${h.id}'">
                    🏨 <strong>${escapeHtml(h.name)}</strong>
                    · ${h.star_rating} ⭐ · Rating: ${h.avg_rating}
                    <br><span style="font-size:11px; color:#0284c7;">Nhấn để xem chi tiết →</span>
                </div>`;
        }

        chatDiv.innerHTML += `
            <div class="iti-chat-msg ai">
                ${reply}
                ${hotelCard}
            </div>`;
        chatDiv.scrollTop = chatDiv.scrollHeight;

    } catch (err) {
        document.getElementById(loadingId)?.remove();
        chatDiv.innerHTML += `<div class="iti-chat-msg ai">❌ Lỗi kết nối. Vui lòng thử lại!</div>`;
        chatDiv.scrollTop = chatDiv.scrollHeight;
    }
}

// Gửi tin nhắn CSKH (có thể dùng lại cho nút gửi ở nhiều chỗ khác)
function sendChatMessage() { sendCskhMessage(); }
function resetItinerary() {
    cskhSessionId = null;
    cskhHistory   = [];
    const chatDiv = document.getElementById('chatMessages');
    if (chatDiv) chatDiv.innerHTML = `<div class="iti-chat-msg ai">👋 Phiên mới bắt đầu. Tôi có thể giúp gì cho bạn?</div>`;
}

// Áp dụng thay đổi từ CSKH (ví dụ: chỉnh sửa lịch trình theo yêu cầu)
function applyChatChanges(updatedJson, btn) {
    if (updatedJson) {
        try {
            const updated = JSON.parse(updatedJson.replace(/\\'/g, "'"));
            showItineraryResult(updated);
            showToast('Đã cập nhật lịch trình!', 'success');
        } catch (e) {
            showToast('Lỗi áp dụng thay đổi', 'error');
        }
    } else {
        showToast('Không có thay đổi nào được áp dụng', 'error');
    }
}
// Xóa tin nhắn chat (nếu cần)
function removeChatMessage(msgId) {
    const el = document.getElementById(msgId);
    if (el) el.remove();
}

// --- Lưu, xuất PDF ---
function saveItinerary(id) {
    const btn = document.querySelector('.btn-save-iti');
    if (btn) { btn.disabled = true; btn.textContent = '💾 Đang lưu...'; }
    
    api(`/itineraries/${id}`, { method: 'PUT', body: JSON.stringify({ status: 'saved' }) })
        .then(res => res.json())
        .then(data => {
            if (data.id || data.message) {
                showToast('Đã lưu lịch trình!', 'success');
                if (btn) { btn.textContent = '✅ Đã lưu'; }
                loadItineraryList();
            } else {
                showToast('Lưu thất bại', 'error');
                if (btn) { btn.disabled = false; btn.textContent = '💾 Lưu lịch trình'; }
            }
        })
        .catch(() => {
            showToast('Lỗi kết nối', 'error');
            if (btn) { btn.disabled = false; btn.textContent = '💾 Lưu lịch trình'; }
        });
}

function exportItineraryPDF(id) {
    window.open(`/itineraries/${id}/print`, '_blank');
}

// --- Danh sách lịch trình (Section 3) ---
async function loadItineraryList() {
    const container = document.getElementById('itineraryListContainer');
    container.innerHTML = '<div class="empty-state"><i class="fa-solid fa-spinner fa-spin"></i> Đang tải...</div>';
    try {
        const res = await api('/itineraries');
        const list = await res.json();
        if (!res.ok) throw new Error('Lỗi tải');
        if (!list || list.length === 0) {
            container.innerHTML = `<div class="empty-state">
                <svg width="80" height="80" viewBox="0 0 24 24" fill="none" stroke="var(--text-muted)" stroke-width="1.5"><path d="M21 10.5V19a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h7.5m3.5 7L21 4.5M19 8.5H15V4.5"/></svg>
                <p>Bạn chưa có lịch trình nào</p>
                <p style="font-size:13px;">Hãy tạo lịch trình đầu tiên với AI ở trên.</p>
            </div>`;
            return;
        }

        const saved = list.filter(it => it.status === 'saved');
        const drafts = list.filter(it => it.status !== 'saved');

        // FIX 4: Header đỏ + nút xóa tất cả
        const draftHeader = drafts.length > 0 ? `
            <div style="display:flex; justify-content:space-between; align-items:center; 
                        background:#fef2f2; border:1px solid #fecaca; border-radius:8px; 
                        padding:10px 14px; margin-bottom:16px;">
                <span style="font-size:13px; color:#dc2626; font-weight:500;">
                    🗑 ${drafts.length} bản nháp chưa lưu
                </span>
                <button onclick="deleteAllDrafts()" 
                        style="font-size:12px; color:#fff; background:#ef4444; border:none; 
                               border-radius:6px; padding:4px 12px; cursor:pointer; font-weight:500;">
                    Xóa tất cả
                </button>
            </div>` : '';

        container.innerHTML = draftHeader + list.map(it => `
            <div class="iti-card">
                <span class="iti-card-badge ${it.status === 'saved' ? 'saved' : ''}">${it.status === 'saved' ? 'Đã lưu' : 'Bản nháp'}</span>
                <div class="iti-card-title">${escapeHtml(it.title)}</div>
                <div class="iti-card-dest"><i class="fa-solid fa-location-dot"></i> ${escapeHtml(it.destination || '')}</div>
                <div class="iti-card-meta">📅 ${it.total_days} ngày · 👥 ${it.num_people || '2'} người · ${it.created_at ? new Date(it.created_at).toLocaleDateString('vi-VN') : ''}</div>
                <div class="iti-card-actions">
                    <button class="iti-card-menu-btn"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                    <div class="iti-card-dropdown">
                        <button onclick="viewItinerary(${it.id})"><i class="fa-regular fa-eye"></i> Xem chi tiết</button>
                        <!-- FIX 4: Đã bỏ nút Chỉnh sửa -->
                        <button onclick="deleteItinerary(${it.id})"><i class="fa-solid fa-trash"></i> Xoá</button>
                    </div>
                </div>
            </div>
        `).join('');

        container.querySelectorAll('.iti-card-menu-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                const dropdown = this.nextElementSibling;
                dropdown.classList.toggle('show');
            });
        });
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.iti-card-actions')) {
                container.querySelectorAll('.iti-card-dropdown.show').forEach(d => d.classList.remove('show'));
            }
        });
    } catch (err) {
        container.innerHTML = '<div class="empty-state">Lỗi tải danh sách</div>';
    }
}

function viewItinerary(id) {
    api(`/itineraries/${id}`)
        .then(res => res.json())
        .then(data => {
            if (data.id) {
                showItineraryResult(data);
            } else {
                showToast('Không tải được chi tiết', 'error');
            }
        })
        .catch(() => showToast('Lỗi', 'error'));
}

function deleteItinerary(id) {
    const confirmModal = document.getElementById('confirmModal');
    document.getElementById('confirmMessage').textContent = 'Bạn có chắc muốn xoá lịch trình này?';
    confirmModal.style.display = 'flex';

    document.getElementById('confirmOkBtn').onclick = async function() {
        // Đổi nút thành trạng thái đang xóa
        const okBtn = document.getElementById('confirmOkBtn');
        const originalText = okBtn.textContent;
        okBtn.disabled = true;
        okBtn.textContent = '⏳ Đang xóa...';

        try {
            const res = await api(`/itineraries/${id}`, { method: 'DELETE' });
            if (res.ok) {
                confirmModal.style.display = 'none';
                showToast('Đã xoá lịch trình', 'success');
                loadItineraryList();
                if (currentItineraryId === id) {
                    document.getElementById('itineraryResult').style.display = 'none';
                    currentItineraryId = null;
                }
            } else {
                const data = await res.json();
                showToast(data.message || 'Xoá thất bại', 'error');
                // Khôi phục nút nếu thất bại
                okBtn.disabled = false;
                okBtn.textContent = originalText;
            }
        } catch (err) {
            showToast('Lỗi kết nối', 'error');
            okBtn.disabled = false;
            okBtn.textContent = originalText;
        }
    };

    document.getElementById('confirmCancelBtn').onclick = function() {
        confirmModal.style.display = 'none';
    };
}

async function deleteAllDrafts() {
    const confirmModal = document.getElementById('confirmModal');
    document.getElementById('confirmMessage').textContent = 'Xóa tất cả bản nháp? Các lịch trình đã lưu sẽ không bị ảnh hưởng.';
    confirmModal.style.display = 'flex';
    document.getElementById('confirmOkBtn').onclick = async function() {
        confirmModal.style.display = 'none';
        try {
            const res = await api('/itineraries');
            const list = await res.json();
            const drafts = list.filter(it => it.status !== 'saved');
            await Promise.all(drafts.map(it => api(`/itineraries/${it.id}`, { method: 'DELETE' })));
            showToast(`Đã xóa ${drafts.length} bản nháp`, 'success');
            loadItineraryList();
        } catch(e) {
            showToast('Lỗi khi xóa', 'error');
        }
    };
    document.getElementById('confirmCancelBtn').onclick = function() {
        confirmModal.style.display = 'none';
    };
}

// --- Khởi tạo khi DOM sẵn sàng ---
document.addEventListener('DOMContentLoaded', function() {
    initTagInput();
    loadItineraryList();
    
    // Nút mở/đóng tuỳ chỉnh nâng cao
    const toggleBtn = document.getElementById('toggleAdvancedBtn');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            document.getElementById('advancedFields').classList.toggle('show');
        });
    }
    
    // Set ngày bắt đầu tối thiểu là hôm nay
    const startDateInput = document.getElementById('itineraryStartDate');
    if (startDateInput) {
        const today = new Date().toISOString().split('T')[0];
        startDateInput.setAttribute('min', today);
    }

    // Xử lý ô nhập ngân sách (chỉ cho phép nhập số nguyên)
    const minInput = document.getElementById('itineraryBudgetMin');
    const maxInput = document.getElementById('itineraryBudgetMax');
    if (minInput && maxInput) {
        [minInput, maxInput].forEach(input => {
            input.addEventListener('input', function(e) { formatBudgetInput(e.target); });
        });
    }
});

// --- Tải khách sạn gợi ý (FIX 3: ẩn khi không có dữ liệu) ---
async function loadSuggestedHotels(destination) {
    const old = document.getElementById('suggestedHotelsSection');
    if (old) old.remove();

    if (!destination) return;

    const section = document.createElement('div');
    section.id = 'suggestedHotelsSection';
    section.style.cssText = 'margin-top:24px;';
    section.innerHTML = `
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3 style="font-size:16px; font-weight:600; color:var(--text-primary);">
                🏨 Khách sạn gợi ý tại ${escapeHtml(destination)}
            </h3>
        </div>
        <div id="suggestedHotelsList" style="display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:16px;">
            <div style="color:var(--text-muted); font-size:13px;">Đang tải...</div>
        </div>
    `;
    document.getElementById('itineraryResult').appendChild(section);

    try {
        const res = await api(`/hotels?city=${encodeURIComponent(destination)}&per_page=4`);
        const data = await res.json();
        const hotels = data.data || data || [];

        // Nếu Database không có khách sạn nào ở tỉnh này -> Xóa luôn section gợi ý
        if (!hotels || hotels.length === 0) {
            section.remove();
            return;
        }

        const list = document.getElementById('suggestedHotelsList');
        list.innerHTML = hotels.map(h => {
            const imgSrc = h.images?.[0]?.image_path 
                || `https://picsum.photos/seed/${h.id || 1}/400/140`;
            
            return `
                <div style="background:var(--bg-card); border:1px solid var(--border); border-radius:var(--radius); overflow:hidden; cursor:pointer;" 
                    onclick="window.location.href='/hotels/${h.id}'">
                    <img src="${imgSrc}"
                        style="width:100%; height:140px; object-fit:cover;"
                        onerror="this.src='https://picsum.photos/seed/${h.id}/400/140'">
                    <div style="padding:12px;">
                        <div style="font-weight:600; font-size:14px; margin-bottom:4px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${escapeHtml(h.name)}</div>
                        <div style="font-size:12px; color:var(--text-muted); margin-bottom:6px;">📍 ${escapeHtml(h.city || h.location?.name || '')}</div>
                        <div style="font-size:13px; color:var(--primary); font-weight:600;">
                            ${h.min_price ? Number(h.min_price).toLocaleString('vi-VN') + 'đ/đêm' : 'Liên hệ'}
                        </div>
                        ${h.rating ? `<div style="font-size:12px; color:#f59e0b;">⭐ ${h.rating}</div>` : ''}
                    </div>
                </div>
            `;
        }).join('');
    } catch(e) {

        // Nếu lỗi cũng nên ẩn section để tránh hiển thị thông báo lỗi thô
        const sectionElem = document.getElementById('suggestedHotelsSection');
        if (sectionElem) sectionElem.remove();
    }
}
</script>