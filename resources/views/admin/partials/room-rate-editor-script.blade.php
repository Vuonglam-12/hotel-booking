<script>
(() => {
    const $=id=>document.getElementById(id), panel=$('rtRateEditor');
    if(!panel)return;
    const host=window.AdminRoomRateHost||{isSaving:()=>roomSaving,setSaving:value=>{roomSaving=value;},update:()=>updateRoomControls()};
    const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const money=value=>new Intl.NumberFormat('vi-VN',{style:'currency',currency:'VND',maximumFractionDigits:2}).format(Number(value));
    let hotel=null,type=null,rows=[],editing=null,serial=0,controller,ready=false,busy=false;
    const endpoint=()=>'/hotels/'+hotel+'/room-types/'+type+'/rates';
    function feedback(message,error=false){$('rtRateFeedback').textContent=message;$('rtRateFeedback').dataset.error=String(error);}
    function syncControls(){panel.querySelectorAll('input,select,button').forEach(input=>input.disabled=busy||host.isSaving()||!ready);$('rtRateReload').disabled=busy||host.isSaving();}
    function reset(){editing=null;$('rtRateForm').reset();$('rtRateSave').textContent='Lưu mức giá';$('rtRateReset').hidden=true;kindChanged();}
    function kindChanged(){const season=$('rtRateKind').value==='season';$('rtRateEndField').hidden=!season;$('rtRateEnd').required=season;$('rtRateEnd').min=$('rtRateStart').value;$('rtRateStartLabel').textContent=season?'Từ ngày':'Ngày áp dụng';}
    const dateLabel=value=>String(value).split('-').reverse().join('/');
    function previewLink(rate){const start=new Date(rate.start_date+'T00:00:00Z');start.setUTCDate(start.getUTCDate()+1);return '/hotels/'+hotel+'?room_type_id='+type+'&check_in='+encodeURIComponent(rate.start_date)+'&check_out='+encodeURIComponent(start.toISOString().slice(0,10))+'&quantity=1';}
    function render(){
        $('rtRateCount').textContent='('+rows.length+')';
        $('rtRateRows').innerHTML=rows.length?rows.map(rate=>`<tr><td data-label="Loại giá"><div><span class="rr-tag">${rate.kind==='day'?'Ngày cụ thể':'Khoảng mùa'}</span>${rate.label?'<span class="rr-rule-name">'+esc(rate.label)+'</span>':''}</div></td><td data-label="Áp dụng"><div><span class="rr-period">${esc(dateLabel(rate.start_date))}${rate.kind==='season'?'<br>đến '+esc(dateLabel(rate.end_date)):''}</span><a class="rr-preview" href="${esc(previewLink(rate))}" target="_blank" rel="noopener">Xem giá phía khách ↗</a></div></td><td class="rr-money" data-label="Mỗi đêm">${rate.amount_mode==='surcharge'?'+':''}${money(rate.price)}<small class="rr-rule-name">${rate.amount_mode==='surcharge'?'Phụ thu cộng thêm':'Giá thay thế cũ — chưa chuyển'}</small></td><td data-label="Thao tác"><div class="rr-row-actions"><button type="button" class="rr-button" data-rate-edit="${Number(rate.id)}" aria-label="Sửa giá ${esc(dateLabel(rate.start_date))}">Sửa</button><button type="button" class="rr-button rr-button-delete" data-rate-delete="${Number(rate.id)}" aria-label="Xóa giá ${esc(dateLabel(rate.start_date))}">Xóa</button></div></td></tr>`).join(''):'<tr><td class="rr-empty" colspan="4">Chưa có phụ thu ngày/mùa. Các đêm chỉ tính giá cơ bản.</td></tr>';
        syncControls();
    }
    async function load(){
        if(!hotel||!type||busy)return;
        const version=++serial;controller?.abort();controller=new AbortController();ready=false;syncControls();feedback('Đang tải bảng giá…');
        try{
            const response=await adminApi(endpoint(),{signal:controller.signal,cache:'no-store'}),data=await response.json();
            if(version!==serial)return;
            if(!response.ok)throw new Error(data.message||'Không tải được bảng giá.');
            if(!Array.isArray(data.rates))throw new Error('Bảng giá trả về không hợp lệ.');
            rows=data.rates;
            const context=data.context;
            if(context&&(Number(context.hotel_id)!==Number(hotel)||Number(context.room_type_id)!==Number(type)))throw new Error('Bảng giá không thuộc khách sạn và loại phòng đang chọn.');
            $('rtRateContext').textContent=context?[context.hotel_name,context.room_type_name].filter(Boolean).join(' · '):'';
            $('rtRateBasePrice').textContent=context?.base_price!=null?money(context.base_price)+' / đêm':'—';
            ready=true;render();feedback('');
        }catch(error){if(version!==serial||error.name==='AbortError')return;feedback(error.message||'Không tải được bảng giá.',true);syncControls();}
    }
    async function mutate(url,options,message){
        if(busy||host.isSaving()||!ready)return;
        const version=serial,activeType=type;busy=true;host.setSaving(true);host.update();syncControls();feedback('Đang lưu bảng giá…');
        try{
            const response=await adminApi(url,options),data=await response.json();
            if(version!==serial)return;
            if(!response.ok)throw new Error(Object.values(data.errors||{}).flat().join(' ')||data.message||'Chưa lưu được bảng giá.');
            try{window.localStorage?.setItem('angel.room-pricing.changed',JSON.stringify({hotel_id:hotel,room_type_id:type,at:Date.now()}));}catch{}
            reset();busy=false;host.setSaving(false);await load();if(type===activeType)feedback(message);
        }catch(error){if(version===serial)feedback(error.message||'Không kết nối được máy chủ.',true);}
        finally{busy=false;host.setSaving(false);host.update();syncControls();}
    }
    window.AdminRoomRates={
        syncControls,
        select(hotelId,typeId,model){
            serial++;controller?.abort();hotel=hotelId;type=typeId;ready=false;rows=[];reset();$('rtRateRows').replaceChildren();$('rtRateContext').textContent='';$('rtRateBasePrice').textContent='—';feedback('');
            panel.hidden=!hotel||!type||model!=='scoped';
            if(!panel.hidden&&panel.open)load();else syncControls();
        }
    };
    panel.addEventListener('toggle',()=>{if(panel.open&&!panel.hidden&&!ready)load();});
    $('rtRateReload').addEventListener('click',load);$('rtRateKind').addEventListener('change',kindChanged);$('rtRateStart').addEventListener('change',kindChanged);$('rtRateReset').addEventListener('click',reset);
    $('rtRateForm').addEventListener('submit',event=>{
        event.preventDefault();if(!$('rtRateForm').reportValidity()||!hotel||!type)return;
        const legacy=editing&&rows.find(row=>Number(row.id)===Number(editing))?.amount_mode!=='surcharge';
        if(legacy&&!confirm('Bản ghi cũ là giá thay thế. Xác nhận chuyển số tiền vừa nhập thành phụ thu cộng vào giá cơ bản?'))return;
        const payload={kind:$('rtRateKind').value,start_date:$('rtRateStart').value,end_date:$('rtRateKind').value==='day'?$('rtRateStart').value:$('rtRateEnd').value,price:$('rtRatePrice').value.trim(),label:$('rtRateLabel').value.trim()||null,amount_mode:'surcharge'};
        mutate(endpoint()+(editing?'/'+editing:''),{method:editing?'PUT':'POST',body:JSON.stringify(payload)},'Đã lưu bảng giá. Các đơn đã đặt giữ nguyên giá.');
    });
    $('rtRateRows').addEventListener('click',event=>{
        if(busy||host.isSaving()||!ready)return;
        const edit=event.target.closest('[data-rate-edit]'),remove=event.target.closest('[data-rate-delete]');
        if(edit){const rate=rows.find(row=>Number(row.id)===Number(edit.dataset.rateEdit));if(!rate)return;editing=rate.id;$('rtRateKind').value=rate.kind;$('rtRateStart').value=rate.start_date;$('rtRateEnd').value=rate.end_date;$('rtRatePrice').value=rate.amount_mode==='surcharge'?rate.price:'';$('rtRateLabel').value=rate.label||'';$('rtRateSave').textContent='Lưu mức giá';$('rtRateReset').hidden=false;kindChanged();if(rate.amount_mode!=='surcharge')feedback('Bản ghi cũ là giá thay thế. Nhập phụ thu mới và xác nhận khi lưu để chuyển; bỏ qua nếu chưa rõ ý nghĩa.',true);$('rtRateStart').focus();}
        if(remove&&confirm('Xóa mức giá này? Các đơn đã đặt vẫn giữ nguyên giá.'))mutate(endpoint()+'/'+Number(remove.dataset.rateDelete),{method:'DELETE'},'Đã xóa mức giá. Các đơn đã đặt giữ nguyên giá.');
    });
    syncControls();
})();
</script>
