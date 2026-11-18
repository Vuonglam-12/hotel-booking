<style>
#notif-dropdown .notif-open { flex:1; min-width:0; padding:0; border:0; background:transparent; text-align:left; color:inherit; font:inherit; cursor:pointer; overflow-wrap:anywhere; }
#notif-dropdown .notif-open>span { display:block; }
#notif-dropdown .notif-delete { flex-shrink:0; display:inline-flex; align-items:center; justify-content:center; width:30px; height:30px; border:0; border-radius:6px; background:transparent; color:#64748B; cursor:pointer; }
#notif-dropdown .notif-delete:hover { color:#B91C1C; background:#FEF2F2; }
#notif-dropdown :is(.notif-open,.notif-delete):focus-visible { outline:2px solid #2563EB; outline-offset:3px; }
</style>
<script>
    // One notification reader shared by the public Header and Customer account layout.
    const _notificationApi = typeof _layoutApi === 'function' ? _layoutApi : api;
    const _notificationShowToast = typeof showToast === 'function' ? showToast : null;
    let _allNotifications = [], _showingAll = false, _unreadCount = 0;
    let _notificationReady = false, _notificationLoading = false, _notificationToken = null;
    const _seenNotificationIds = new Set();

    // The public Header already provides these controls. Account pages use the same IDs/API.
    if (typeof window.toggleNotifDropdown !== 'function') {
        window.closeNotificationDropdown = function() {
            document.getElementById('notif-dropdown')?.classList.remove('open');
            document.getElementById('notif-bell')?.setAttribute('aria-expanded','false');
        };
        window.toggleNotifDropdown = function(event) {
            event.stopPropagation();
            const bell=document.getElementById('notif-bell'), menu=document.getElementById('notif-dropdown');
            if (!bell || !menu) return;
            const open=!menu.classList.contains('open');
            closeNotificationDropdown();
            if (!open) return;
            const rect=bell.getBoundingClientRect(), width=Math.min(360,innerWidth-24);
            Object.assign(menu.style,{position:'fixed',top:(rect.bottom+8)+'px',left:Math.max(12,Math.min(rect.right-width,innerWidth-width-12))+'px',right:'auto',width:width+'px'});
            menu.classList.add('open');bell.setAttribute('aria-expanded','true');loadNotifications();
        };
        window.addEventListener('resize',()=>closeNotificationDropdown());
    }
    document.addEventListener('click',event=>{
        const menu=document.getElementById('notif-dropdown'), bell=document.getElementById('notif-bell');
        if (menu && !menu.contains(event.target) && bell && !bell.contains(event.target)) closeNotificationDropdown();
    });
    document.addEventListener('keydown',event=>{
        if (event.key==='Escape' && document.getElementById('notif-dropdown')?.classList.contains('open')) {
            closeNotificationDropdown();document.getElementById('notif-bell')?.focus();
        }
    });

    function notificationToast(title,message='',type='info') {
        if (!_notificationShowToast) return;
        if (document.body.classList.contains('account-ui')) _notificationShowToast(title,type,message);
        else _notificationShowToast([title,message].filter(Boolean).join(' — '),6500);
    }
    function notificationLink(value) {
        try {
            const url=new URL(value||'/bookings',location.origin);
            if (url.origin===location.origin && ['http:','https:'].includes(url.protocol)) {
                return url.pathname==='/dashboard' && url.hash==='#bookings' ? '/bookings' : url.pathname+url.search+url.hash;
            }
        } catch {}
        return '/bookings';
    }
    async function loadNotifications() {
        const token=localStorage.getItem('token');
        if (!token || _notificationLoading) return;
        if (_notificationToken!==token) {
            _notificationToken=token;_notificationReady=false;_seenNotificationIds.clear();_allNotifications=[];_unreadCount=0;
        }
        _notificationLoading=true;
        try {
            const options={cache:'no-store',signal:AbortSignal.timeout(10000)};
            const [res,countRes]=await Promise.all([
                _notificationApi('/notifications?per_page=100',options),
                _notificationApi('/notifications/unread-count',options).catch(()=>null),
            ]);
            if (!res.ok || localStorage.getItem('token')!==token) return;
            const data=await res.json(), rows=Array.isArray(data)?data:(data.data||[]);
            if (!Array.isArray(rows)) return;
            _allNotifications=rows.map(n=>n.type==='payment_success' && String(n.data?.title||'').startsWith('DEMO:')
                ? {...n,data:{...n.data,title:'Thanh toán thành công',message:'Đơn #'+n.data.booking_id+' đã được xác nhận. Bạn có thể tải hóa đơn trong lịch sử đặt phòng.'}}
                : n);
            const fresh=_notificationReady?_allNotifications.filter(n=>!n.read_at && !_seenNotificationIds.has(String(n.id))):[];
            _allNotifications.forEach(n=>_seenNotificationIds.add(String(n.id)));
            _notificationReady=true;
            let count=null;
            if(countRes?.ok)try{count=(await countRes.json()).count;}catch{}
            if (localStorage.getItem('token')!==token) return;
            _unreadCount=Number.isSafeInteger(count)&&count>=0?count:_allNotifications.filter(n=>!n.read_at).length;
            updateBadge();renderNotifications();
            fresh.forEach(n=>window.dispatchEvent(new CustomEvent('aurel:notification',{detail:n})));
            fresh.slice(0,3).reverse().forEach(n=>notificationToast(n.data?.title||'Có thông báo mới',n.data?.message||'', ['payment_success','booking_success'].includes(n.type)?'success':'info'));
        } catch {
            // Retain the last successful state on connection errors; never invent a notification.
        } finally { _notificationLoading=false; }
    }
    function updateBadge() {
        const badge=document.getElementById('notif-badge'), bell=document.getElementById('notif-bell'), mobile=document.getElementById('mobile-notif-count');
        if (badge) { badge.textContent=_unreadCount>99?'99+':String(_unreadCount);badge.classList.toggle('show',_unreadCount>0); }
        bell?.setAttribute('aria-label',_unreadCount>0?'Thông báo, '+_unreadCount+' chưa đọc':'Thông báo');
        if (mobile) { mobile.textContent=String(_unreadCount);mobile.classList.toggle('hidden',_unreadCount===0); }
    }
    function renderNotifications() {
        const list=document.getElementById('notif-list'), footer=document.getElementById('notif-footer');
        if (!list) return;
        list.replaceChildren();footer?.replaceChildren();
        if (!_allNotifications.length) {
            const empty=document.createElement('p');empty.className='account-notif-empty text-center py-12 text-slate-400 text-sm';empty.textContent='Hiện chưa có thông báo nào.';list.append(empty);
            if (footer) footer.style.display='none';return;
        }
        for (const n of (_showingAll?_allNotifications:_allNotifications.slice(0,3))) {
            const row=document.createElement('div');row.className='notif-item '+(n.read_at?'read':'unread');row.dataset.notificationId=n.id;
            const dot=document.createElement('span');dot.className='notif-dot';dot.setAttribute('aria-hidden','true');
            const open=document.createElement('button');open.type='button';open.className='notif-open';
            const title=document.createElement('span');title.className='notif-title text-sm font-semibold text-slate-800 leading-snug';title.textContent=n.data?.title||'Thông báo';
            const message=document.createElement('span');message.className='notif-message text-xs text-slate-500 mt-0.5 leading-relaxed';message.textContent=n.data?.message||'';
            const time=document.createElement('span');time.className='notif-time text-xs text-blue-500 mt-1';time.textContent=formatNotifTime(n.created_at);
            open.append(title,message,time);open.addEventListener('click',()=>handleNotifClick(n.id,n.data?.link));
            const remove=document.createElement('button');remove.type='button';remove.className='notif-delete';remove.setAttribute('aria-label','Xóa thông báo');remove.textContent='×';remove.addEventListener('click',event=>deleteNotif(n.id,event));
            row.append(dot,open,remove);list.append(row);
        }
        if (footer && _allNotifications.length>3) {
            footer.style.display='block';const more=document.createElement('button');more.type='button';more.className='text-xs text-blue-600 font-semibold';more.textContent=_showingAll?'Thu gọn ↑':'Xem thêm '+(_allNotifications.length-3)+' thông báo ↓';more.addEventListener('click',_showingAll?collapseNotifications:showAllNotifications);footer.append(more);
        } else if (footer) footer.style.display='none';
    }
    function showAllNotifications() { _showingAll=true;renderNotifications(); }
    function collapseNotifications() { _showingAll=false;renderNotifications(); }
    async function deleteNotif(id,event) {
        event?.stopPropagation();
        try {
            const res=await _notificationApi('/notifications/'+id,{method:'DELETE'});
            if (!res.ok) throw new Error();
            if (_allNotifications.some(n=>n.id===id && !n.read_at)) _unreadCount=Math.max(0,_unreadCount-1);
            _allNotifications=_allNotifications.filter(n=>n.id!==id);updateBadge();renderNotifications();
        } catch { notificationToast('Chưa xóa được thông báo. Vui lòng thử lại.','','error'); }
    }
    async function handleNotifClick(id,link) {
        try {
            const res=await _notificationApi('/notifications/'+id+'/read',{method:'POST'});
            if (!res.ok) throw new Error();
            const n=_allNotifications.find(n=>n.id===id);
            if (n && !n.read_at) { n.read_at=new Date().toISOString();_unreadCount=Math.max(0,_unreadCount-1); }
            updateBadge();renderNotifications();location.assign(notificationLink(link));
        } catch { notificationToast('Chưa mở được thông báo. Vui lòng thử lại.','','error'); }
    }
    async function markAllRead() {
        try {
            const res=await _notificationApi('/notifications/read-all',{method:'POST'});
            if (!res.ok) throw new Error();
            _allNotifications.forEach(n=>n.read_at=n.read_at||new Date().toISOString());_unreadCount=0;updateBadge();renderNotifications();notificationToast('Đã đánh dấu tất cả là đã đọc');
        } catch { notificationToast('Chưa đánh dấu được thông báo. Vui lòng thử lại.','','error'); }
    }
    function formatNotifTime(dateStr) {
        if (!dateStr || Number.isNaN(Date.parse(dateStr))) return '';
        const diff=Math.max(0,Math.floor((Date.now()-Date.parse(dateStr))/1000));
        if (diff<60) return 'Vừa xong';
        if (diff<3600) return Math.floor(diff/60)+' phút trước';
        if (diff<86400) return Math.floor(diff/3600)+' giờ trước';
        return new Date(dateStr).toLocaleDateString('vi-VN');
    }
    loadNotifications();
    setInterval(()=>{if(!document.hidden)loadNotifications();},5000);
    document.addEventListener('visibilitychange',()=>{if(!document.hidden)loadNotifications();});
    window.addEventListener('focus',()=>loadNotifications());
</script>
