{{--
    =====================================================
    COMPONENT: notification-bell.blade.php
    Dùng: @include('components.notification-bell')
    Đặt trong layout.blade.php, bên trong <head> hoặc
    trước </body> (CSS), và trong navbar (HTML dropdown).
    =====================================================
--}}

{{-- resources/views/components/notification-bell.blade.php --}}
<style>
    /* Nút Chuông */
    .notif-bell-btn {
        position: relative;
        width: 38px; height: 38px; border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        color: rgba(255,255,255,0.8); background: rgba(255,255,255,0.05);
        border: 1px solid rgba(255,255,255,0.1);
        transition: all 0.2s; cursor: pointer;
    }
    .notif-bell-btn:hover { background: rgba(255,255,255,0.15); color: white; }

    /* Chấm đỏ báo số lượng */
    .notif-badge {
        position: absolute; top: -2px; right: -2px;
        min-width: 18px; height: 18px; padding: 0 4px;
        background: #EF4444; border-radius: 9px;
        font-size: 10px; font-weight: 700; color: white;
        display: none; align-items: center; justify-content: center;
        border: 2px solid #0F172A;
    }
    .notif-badge.show { display: flex; }

    /* Khung Dropdown Kính mờ (Đã sửa lỗi định vị) */
    .notif-dropdown {
        position: absolute; 
        top: calc(100% + 12px); right: 0; /* Căn chuẩn xuống dưới nút chuông */
        width: 360px;
        background: rgba(255, 255, 255, 0.85); /* Hiệu ứng Glass Trắng */
        backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
        border-radius: 20px;
        box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        border: 1px solid rgba(255,255,255,0.6);
        z-index: 9999;
        display: none; overflow: hidden;
        transform-origin: top right;
    }
    .notif-dropdown.open { display: block; animation: notifPop 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
    @keyframes notifPop { from { opacity: 0; transform: scale(0.95); } to { opacity: 1; transform: scale(1); } }

    /* Item thông báo */
    .notif-item {
        display: flex; align-items: flex-start; gap: 12px;
        padding: 14px 16px;
        border-bottom: 1px solid rgba(0,0,0,0.05);
        cursor: pointer; transition: background 0.15s;
    }
    .notif-item:hover { background: rgba(59, 130, 246, 0.05); }
    .notif-item.unread { background: rgba(59, 130, 246, 0.08); }
    .notif-dot { width: 8px; height: 8px; border-radius: 50%; background: #3B82F6; flex-shrink: 0; margin-top: 6px; }
    .notif-item.read .notif-dot { background: transparent; border: 2px solid #CBD5E1; }

    /* Tùy chỉnh thanh cuộn cho danh sách thông báo */
    #notif-list::-webkit-scrollbar { width: 4px; }
    #notif-list::-webkit-scrollbar-track { background: transparent; }
    #notif-list::-webkit-scrollbar-thumb { background: #e2e8f0; border-radius: 10px; }

</style>

{{-- Khung HTML Dropdown --}}
<div id="notif-dropdown" class="notif-dropdown">
    <!-- Header Dropdown -->
    <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200/50 bg-white/40 backdrop-blur-md sticky top-0 z-10">
        <span class="font-bold text-slate-800 text-sm">Thông báo</span>
        <button onclick="markAllRead()" class="text-xs text-blue-600 hover:text-blue-700 font-medium transition">Đánh dấu tất cả đã đọc</button>
    </div>
    
    <!-- Danh sách thông báo (Cho phép cuộn nội dung) -->
    <div id="notif-list" style="max-height: 420px; overflow-y: auto;">
        <div class="text-center py-12 text-slate-400 text-sm">Đang tải thông báo...</div>
    </div>
</div>