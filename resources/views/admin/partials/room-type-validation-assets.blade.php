@once
@push('styles')
<style>
.room-validation-summary{margin-top:12px;padding:10px 12px;border:1px solid #fda29b;border-radius:8px;background:#fff8f7;color:#b42318;font-size:12px;line-height:1.6;overflow-wrap:anywhere}.room-validation-summary[hidden]{display:none}.room-validation-summary ul{margin:4px 0 0;padding-left:18px}.room-validation-summary button{padding:0;border:0;background:none;color:inherit;text-align:left;text-decoration:underline;font:inherit;cursor:pointer}.room-field-error{margin:5px 0 0;color:#b42318;font-size:12px;line-height:1.5;overflow-wrap:anywhere}input[aria-invalid="true"],select[aria-invalid="true"],textarea[aria-invalid="true"]{border-color:#d92d20!important;box-shadow:0 0 0 1px #fecdca}.room-readiness-note{display:block;margin-top:3px;color:#b54708;font-size:11px;line-height:1.45}
</style>
@endpush
<script>
window.AdminRoomTypeValidation=(()=>{
    const messages=value=>Array.isArray(value)?value.map(String):[String(value)];
    function collect(fields,{model,creating}){
        const errors={},get=key=>fields[key]?.value.trim()??'',add=(key,message)=>{errors[key]=[message];};
        if(!get('name'))add('name','Tên loại phòng chưa nhập.');
        else if([...get('name')].length>100)add('name','Tên loại phòng tối đa 100 ký tự.');
        const price=get('base_price');
        if(!price)add('base_price','Giá phòng chưa nhập.');
        else if(!/^\d{1,10}(\.\d{1,2})?$/.test(price))add('base_price','Giá phòng phải là số không âm, tối đa 10 chữ số nguyên và 2 chữ số thập phân. Không nhập dấu phân cách hàng nghìn.');
        function integer(key,label,{required=true}={}){
            const input=fields[key];if(!input)return;
            const value=get(key);
            if(input.validity?.badInput){add(key,label+' phải là số nguyên.');return;}
            if(!value){if(required)add(key,label+' chưa nhập.');return;}
            if(!/^-?\d+$/.test(value)||!Number.isSafeInteger(Number(value))){add(key,label+' phải là số nguyên.');return;}
            const min=input.min===''?0:Number(input.min),max=input.max===''?2147483647:Number(input.max);
            if(Number(value)<min)add(key,label+' phải từ '+min+' trở lên.');
            else if(Number(value)>max)add(key,label+' tối đa '+new Intl.NumberFormat('vi-VN').format(max)+'.');
        }
        integer('capacity','Sức chứa');integer('total_rooms',creating&&model!=='v2'?'Số phòng cần tạo':'Tổng số phòng');
        if(model!=='v2')integer('floor_start','Tầng bắt đầu',{required:false});
        if([...get('bed_type')].length>50)add('bed_type','Loại giường tối đa 50 ký tự.');
        const description=get('description');
        if(model==='legacy'&&[...description].length>500)add('description','Mô tả tối đa 500 ký tự.');
        else if(new TextEncoder().encode(description).length>65535)add('description','Mô tả tối đa 65.535 byte.');
        if(model!=='legacy'&&!['active','inactive'].includes(get('status')))add('status','Vui lòng chọn trạng thái đang bán hoặc ngừng bán.');
        if(model==='scoped'){
            const amenities=[...new Set(get('amenities').split('\n').map(value=>value.trim()).filter(Boolean))];
            if(amenities.length>50)add('amenities','Tiện nghi riêng tối đa 50 mục.');
            else if(amenities.some(value=>[...value].length>100))add('amenities','Mỗi tiện nghi tối đa 100 ký tự.');
            else if(new Set(amenities.map(value=>value.replace(/\s+/g,' ').toLocaleLowerCase('vi-VN'))).size!==amenities.length)add('amenities','Tiện nghi riêng có mục trùng nhau.');
        }
        return errors;
    }
    function editor(fields,summary,options){
        let current={},armed=false,nodes=[];
        summary.classList.add('room-validation-summary');summary.setAttribute('role','alert');
        const inputFor=key=>fields[key.split('.')[0]==='room_count'?'total_rooms':key.split('.')[0]];
        function cleanup(){
            for(const node of nodes){
                const input=document.getElementById(node.dataset.for);
                if(input){const ids=(input.getAttribute('aria-describedby')||'').split(' ').filter(id=>id&&id!==node.id);if(ids.length)input.setAttribute('aria-describedby',ids.join(' '));else input.removeAttribute('aria-describedby');}
                node.remove();
            }
            nodes=[];Object.values(fields).forEach(input=>input?.removeAttribute('aria-invalid'));
            summary.replaceChildren();summary.hidden=true;delete summary.dataset.validationSummary;
        }
        function focus(){Object.values(fields).find(input=>input?.getAttribute('aria-invalid')==='true'&&!input.disabled)?.focus();}
        function show(errors={},takeFocus=false){
            current=errors;cleanup();const entries=Object.entries(errors);if(!entries.length)return;
            armed=true;summary.dataset.validationSummary='true';summary.hidden=false;
            const title=document.createElement('strong');title.textContent='Cần sửa '+entries.reduce((sum,[,value])=>sum+messages(value).length,0)+' thông tin:';summary.append(title);
            const list=document.createElement('ul');summary.append(list);
            for(const [key,value] of entries){
                const input=inputFor(key);
                if(input){
                    const node=document.createElement('p');node.id=summary.id+'-'+key.replace(/[^a-z0-9_-]/gi,'-');node.className='room-field-error';node.dataset.for=input.id;node.textContent=messages(value).join(' ');
                    input.setAttribute('aria-invalid','true');input.setAttribute('aria-describedby',[input.getAttribute('aria-describedby'),node.id].filter(Boolean).join(' '));input.parentElement.append(node);nodes.push(node);
                }
                for(const message of messages(value)){
                    const li=document.createElement('li');
                    if(input){const button=document.createElement('button');button.type='button';button.textContent=message;button.addEventListener('click',()=>input.focus());li.append(button);}
                    else li.textContent=message;
                    list.append(li);
                }
            }
            if(takeFocus)focus();
        }
        Object.entries(fields).forEach(([key,input])=>input?.addEventListener('input',()=>{
            if(!armed)return;
            const errors=collect(fields,options()),next={...current};
            for(const errorKey of Object.keys(next))if(inputFor(errorKey)===input)delete next[errorKey];
            if(errors[key])next[key]=errors[key];show(next);
        }));
        return {validate(){const errors=collect(fields,options());show(errors,true);return Object.keys(errors).length===0;},show,focus,reset(){armed=false;current={};cleanup();}};
    }
    return {editor};
})();
</script>
@endonce
