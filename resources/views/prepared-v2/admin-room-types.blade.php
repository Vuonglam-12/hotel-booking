@extends('admin.layouts.admin')
@section('title','Quản lý loại phòng')
@section('content')
<div class="page-header"><div><h1>Loại phòng — {{ $hotelName }}</h1><p>Quản lý tồn theo loại phòng. Ngừng bán giữ lịch sử; không tạo phòng vật lý.</p></div><a href="/admin/hotels">Khách sạn</a></div>
<p role="status" id="type-feedback"></p>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,340px),1fr));gap:20px">
    <section class="card" style="padding:20px"><h2>Danh sách loại phòng</h2><div id="type-list"></div><button class="btn-primary" id="type-new">Thêm loại phòng</button></section>
    <section class="card" style="padding:20px"><h2 id="type-form-title">Thêm loại phòng</h2><form id="type-form" style="display:grid;gap:12px">
        <label>Tên<input id="type-name" type="text" required maxlength="100"></label>
        <label>Giá / phòng / đêm<input id="type-price" type="text" inputmode="decimal" required pattern="[0-9]{1,10}(\.[0-9]{1,2})?"></label>
        <label>Sức chứa tổng khách<input id="type-capacity" type="number" required min="1" max="2147483647" step="1" value="2"></label>
        <label>Tổng phòng có thể bán<input id="type-total" type="number" required min="0" max="2147483647" step="1" value="0"></label>
        <label>Trạng thái<select id="type-status"><option value="active">Hoạt động</option><option value="inactive">Ngừng bán</option></select></label>
        <label>Loại giường<input id="type-bed" type="text" maxlength="50"></label>
        <label>Mô tả<textarea id="type-description"></textarea></label>
        <p style="font-size:12px;color:#6B7280">Tổng mới phải đủ các cam kết hiện tại và tương lai. Không thay giá của Booking cũ.</p>
        <div class="type-form-actions"><button type="submit" class="btn-primary" id="type-save">Lưu loại phòng</button><button type="button" id="type-cancel">Hủy thay đổi</button><button type="button" id="type-disable" hidden>Ngừng bán</button></div>
    </form></section>
</div>
@endsection
@push('styles')
<style>#type-form>label input,#type-form>label select,#type-form>label textarea{display:block;width:100%;box-sizing:border-box;border:1px solid #E5E7EB;border-radius:10px;padding:10px;font:inherit}#type-list button{display:block;text-align:left;width:100%;border:1px solid #E5E7EB;border-radius:10px;padding:12px;margin:8px 0;background:#FFF;font:inherit}#type-list button[aria-pressed=true]{background:#EEF4FF;border-color:#2563EB}.type-form-actions{display:flex;flex-wrap:wrap;gap:10px}.type-form-actions button{padding:10px;border-radius:10px;border:1px solid #dce4ed;font:inherit;cursor:pointer}#type-form[aria-busy=true]{opacity:.75}</style>
@endpush
@push('scripts')
<script>
(() => {
    const hotel=@json($hotelId); const prefix='/hotels/'+hotel+'/room-types';
    let types=[]; let current=null; let busy=false; let listVersion=0;
    const $=id=>document.getElementById(id);
    const esc=v=>String(v??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    function controls(){
        $('type-save').disabled=busy;
        $('type-cancel').disabled=busy; $('type-new').disabled=busy; $('type-disable').disabled=busy;
        $('type-form').setAttribute('aria-busy',String(busy));
        document.querySelectorAll('[data-type]').forEach(button=>button.disabled=busy);
    }
    async function request(url,options={}){
        const response=await adminApi(url,options);const data=await response.json();
        if(!response.ok)throw new Error(Object.values(data.errors||{}).flat().join(' ')||data.message||'Không thực hiện được thao tác.');return data;
    }
    function select(type, saved=false){
        if(busy&&!saved)return;
        current=type;
        document.querySelectorAll('[data-type]').forEach(button=>button.setAttribute('aria-pressed',String(Number(button.dataset.type)===type?.id)));
        $('type-form-title').textContent=type?'Chỉnh sửa loại phòng — '+type.name:'Thêm loại phòng';
        $('type-name').value=type?.name||''; $('type-price').value=type?.base_price||'';
        $('type-capacity').value=type?.capacity??2; $('type-total').value=type?.total_rooms??0;
        $('type-status').value=type?.status||'active'; $('type-bed').value=type?.bed_type||'';
        $('type-description').value=type?.description||''; $('type-disable').hidden=!type;
        $('type-feedback').textContent='';
    }
    function error(e){$('type-feedback').textContent=e.message;}
    async function load(){
        const version=++listVersion; const data=await request(prefix); if(version!==listVersion)return;
        types=data.room_types;
        $('type-list').innerHTML=types.map(t=>`<button type="button" data-type="${t.id}" aria-pressed="${current?.id===t.id}"><strong>${esc(t.name)}</strong><br>${esc(t.base_price)} VND · ${t.total_rooms} phòng · ${esc(t.status)}</button>`).join('')||'<p>Chưa có loại phòng.</p>';
        controls();
    }
    $('type-list').addEventListener('click',e=>{const button=e.target.closest('[data-type]');if(button&&!busy)select(types.find(t=>t.id===Number(button.dataset.type)));});
    $('type-new').addEventListener('click',()=>select(null));
    $('type-cancel').addEventListener('click',()=>{select(null);$('type-feedback').textContent='Đã hủy thay đổi chưa lưu.';});
    $('type-form').addEventListener('submit',async e=>{
        e.preventDefault(); if(busy)return;
        try{
            const payload={name:$('type-name').value,base_price:$('type-price').value,capacity:Number($('type-capacity').value),total_rooms:Number($('type-total').value),status:$('type-status').value,bed_type:$('type-bed').value||null,description:$('type-description').value||null};
            const method=current?'PUT':'POST';const endpoint=prefix+(current?'/'+current.id:'');
            busy=true;controls();$('type-save').textContent='Đang lưu…';$('type-feedback').textContent='Đang lưu thông tin…';
            const data=await request(endpoint,{method,body:JSON.stringify(payload)});
            select(data.room_type,true);
            $('type-feedback').textContent='Đã lưu thông tin loại phòng.';
            await load().catch(()=>{$('type-feedback').textContent='Đã lưu. Chưa tải lại được danh sách loại phòng.';});
        }catch(e){error(e);}
        finally{busy=false;controls();$('type-save').textContent='Lưu loại phòng';}
    });
    $('type-disable').addEventListener('click',async()=>{
        if(!current||busy)return;busy=true;controls();
        try{const data=await request(prefix+'/'+current.id,{method:'DELETE'});current=data.room_type;$('type-status').value=current.status;await load();$('type-feedback').textContent='Đã ngừng bán.';}
        catch(e){error(e);}finally{busy=false;controls();}
    });
    select(null); adminAuthReady.then(ok=>{if(ok)load().catch(error);});
})();
</script>
@endpush
