{{-- resources/views/components/notification-script.blade.php --}}
<script>
    let _allNotifications = [];
    let _showingAll = false;

    function toggleNotifDropdown(e) {
        e.stopPropagation();
        const dd = document.getElementById('notif-dropdown');
        _notifOpen = !_notifOpen;
        dd.classList.toggle('open', _notifOpen);
        if (_notifOpen) loadNotifications();
    }

    document.addEventListener('click', function(e) {
        const dd   = document.getElementById('notif-dropdown');
        const bell = document.getElementById('notif-bell');
        if (dd && !dd.contains(e.target) && bell && !bell.contains(e.target)) {
            _notifOpen = false;
            dd.classList.remove('open');
        }
    });

    async function loadNotifications() {
        if (!localStorage.getItem('token')) return;
        try {
            const res = await api('/notifications?per_page=100');
            if (!res.ok) return;
            const data = await res.json();
            _allNotifications = Array.isArray(data) ? data : (data.data || []);
            _showingAll = false;
            updateBadge();
            renderNotifications();
        } catch(e) {
            console.log('Notification load error:', e);
        }
    }

    function updateBadge() {
        const unread = _allNotifications.filter(n => !n.read_at).length;

        const badge = document.getElementById('notif-badge');
        if (badge) {
            if (unread > 0) {
                badge.textContent = unread > 99 ? '99+' : unread;
                badge.classList.add('show');
            } else {
                badge.classList.remove('show');
            }
        }

        const mobileCount = document.getElementById('mobile-notif-count');
        if (mobileCount) {
            if (unread > 0) {
                mobileCount.textContent = unread;
                mobileCount.classList.remove('hidden');
            } else {
                mobileCount.classList.add('hidden');
            }
        }
    }

    function renderNotifications() {
        const list = document.getElementById('notif-list');
        const footer = document.getElementById('notif-footer');
        if (!list) return;

        if (!_allNotifications.length) {
            list.innerHTML = `<div class="text-center py-12 text-slate-400 text-sm">Hiện chưa có thông báo nào.</div>`;
            if (footer) footer.style.display = 'none';
            return;
        }

        const items = _showingAll ? _allNotifications : _allNotifications.slice(0, 3);
        const remaining = _allNotifications.length - 3;

        list.innerHTML = items.map(n => `
            <div class="notif-item ${n.read_at ? 'read' : 'unread'} relative group"
                onclick="handleNotifClick(${n.id}, '${n.data?.link || '/dashboard'}')">
                <span class="notif-dot"></span>
                <div class="flex-1 min-w-0 pr-6">
                    <p class="text-sm font-semibold text-slate-800 leading-snug">${n.data?.title || 'Thông báo'}</p>
                    <p class="text-xs text-slate-500 mt-0.5 leading-relaxed line-clamp-2">${n.data?.message || ''}</p>
                    <p class="text-xs text-blue-500 mt-1">${formatNotifTime(n.created_at)}</p>
                </div>
                <button onclick="deleteNotif(${n.id}, event)"
                        class="absolute top-3 right-3 text-slate-300 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-opacity"
                        title="Xóa thông báo">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>`).join('');

        // Cập nhật footer
        if (footer) {
            if (!_showingAll && remaining > 0) {
                footer.style.display = 'block';
                footer.innerHTML = `
                    <button onclick="showAllNotifications()" class="text-xs text-blue-600 hover:text-blue-700 font-bold">
                        Xem thêm ${remaining} thông báo ↓
                    </button>`;
            } else if (_showingAll && _allNotifications.length > 3) {
                footer.style.display = 'block';
                footer.innerHTML = `
                    <button onclick="collapseNotifications()" class="text-xs text-slate-400 hover:text-slate-600 font-medium">
                        Thu gọn ↑
                    </button>`;
            } else {
                footer.style.display = 'none';
            }
        }
    }

    function showAllNotifications() {
        _showingAll = true;
        renderNotifications();
    }

    function collapseNotifications() {
        _showingAll = false;
        renderNotifications();
    }

    async function deleteNotif(id, event) {
        event.stopPropagation();
        try {
            const res = await api(`/notifications/${id}`, { method: 'DELETE' });
            if (res.ok) {
                _allNotifications = _allNotifications.filter(n => n.id !== id);
                updateBadge();
                renderNotifications();
            }
        } catch (e) {
            console.error('Lỗi khi xóa:', e);
        }
    }

    async function handleNotifClick(id, link) {
        await api(`/notifications/${id}/read`, { method: 'POST' });
        const notif = _allNotifications.find(n => n.id === id);
        if (notif) notif.read_at = new Date().toISOString();
        updateBadge();
        renderNotifications();
        window.location.href = link;
    }

    async function markAllRead() {
        await api('/notifications/read-all', { method: 'POST' });
        _allNotifications.forEach(n => n.read_at = n.read_at || new Date().toISOString());
        updateBadge();
        renderNotifications();
        showToast('Đã đánh dấu tất cả là đã đọc');
    }

    function formatNotifTime(dateStr) {
        if (!dateStr) return '';
        const diff = Math.floor((Date.now() - new Date(dateStr)) / 1000);
        if (diff < 60)    return 'Vừa xong';
        if (diff < 3600)  return Math.floor(diff / 60) + ' phút trước';
        if (diff < 86400) return Math.floor(diff / 3600) + ' giờ trước';
        return new Date(dateStr).toLocaleDateString('vi-VN');
    }

    if (localStorage.getItem('token')) {
        loadNotifications();
        setInterval(loadNotifications, 60000);
    }
</script>