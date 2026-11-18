@push('styles')
<style>
.aurel-amenity-editor{min-width:0}.aurel-amenity-editor fieldset{border:0;margin:0;padding:0;min-width:0}.aurel-amenity-tools{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:10px}.aurel-amenity-tools input{flex:1;min-width:120px;width:auto!important;min-height:36px;padding:8px 10px;border:1px solid #d8e2ef;border-radius:8px;font:inherit;font-size:12px}.aurel-amenity-tools button{padding:7px 11px;border:1px solid #bfdbfe;border-radius:8px;background:#eff6ff;color:#1d4ed8;font:inherit;font-size:12px;cursor:pointer}.aurel-amenity-options{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px}.aurel-amenity-option{display:flex;align-items:center;gap:7px;min-width:0;padding:8px;border:1px solid #e4eaf1;border-radius:8px;background:#fff;color:#344054}.aurel-amenity-option:has(input:checked){background:#eff6ff;border-color:#bfdbfe}.aurel-amenity-editor .aurel-amenity-option label{display:flex;align-items:center;gap:7px;flex:1;min-width:0;margin:0;font-size:12px;cursor:pointer}.aurel-amenity-editor input[type=checkbox]{width:14px!important;height:14px!important;min-height:14px!important;flex:0 0 14px;margin:0;padding:0;accent-color:#2563eb}.aurel-amenity-icon{width:16px;height:16px;flex:0 0 16px;color:#2563eb}.aurel-amenity-option span{overflow-wrap:anywhere}.aurel-amenity-remove{flex:0 0 24px;width:24px;height:24px;padding:0;border:0;background:transparent;color:#667085;cursor:pointer}.aurel-amenity-selected{display:flex;flex-wrap:wrap;gap:6px;margin:10px 0}.aurel-amenity-selected button{padding:4px 8px;border:1px solid #bfdbfe;border-radius:20px;background:#eff6ff;color:#1d4ed8;font:inherit;font-size:11px;cursor:pointer}.aurel-amenity-status{margin:6px 0;color:#667085;font-size:12px}.aurel-amenity-status[data-error=true]{color:#b42318}@media(min-width:1200px){.aurel-amenity-options{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:640px){.aurel-amenity-options{grid-template-columns:1fr}}
</style>
@endpush
@push('scripts')
<script>
window.AdminAmenityEditor = class {
    static options = @js(\App\Support\HotelAmenityCatalog::options());
    static escape(value) { return String(value).replace(/[&<>"']/g, c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
    static normalize(value) {
        const name=String(value).replace(/\s+/gu,' ').trim(), identity=name.toLocaleLowerCase('vi');
        if(identity==='airport')return 'airport_shuttle';
        return this.options.find(option=>option.key===identity||option.label.toLocaleLowerCase('vi')===identity)?.key||name;
    }
    static icon(key) {
        const paths={wifi:'M2 8a16 16 0 0 1 20 0M5 12a11 11 0 0 1 14 0M8 16a6 6 0 0 1 8 0M12 20h.01',parking:'M7 21V3h6a5 5 0 0 1 0 10H7',pool:'M3 18q3-3 6 0t6 0t6 0M3 22q3-3 6 0t6 0t6 0M6 14V4a2 2 0 0 1 4 0M15 14V4a2 2 0 0 1 4 0M6 8h9M6 12h9'};
        return `<svg class="aurel-amenity-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="${paths[key]||'M9 12l2 2 4-4m6-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0'}"/></svg>`;
    }
    constructor(id, selected=[]) {
        // Each opening owns its listeners and pending updates, including the reused hotel modal.
        const previousRoot=document.getElementById(id);
        this.root=previousRoot.cloneNode(false);previousRoot.replaceWith(this.root);this.root.classList.add('aurel-amenity-editor');
        this.options=AdminAmenityEditor.options.map(option=>({...option}));this.selected=new Set(selected.map(value=>AdminAmenityEditor.normalize(value)));
        this.catalogReady=false;this.loading=false;this.adding=false;this.saving=false;this.addPromise=null;
        for(const key of this.selected)if(!this.options.some(option=>option.key===key))this.options.push({key,label:key});
        this.root.innerHTML=`<fieldset><strong>Đang chọn cho khách sạn</strong><div class="aurel-amenity-selected" data-amenity-selected aria-label="Tiện nghi đang chọn cho khách sạn"></div><p class="aurel-amenity-status">Bấm Lưu để áp dụng lựa chọn cho khách sạn. Bỏ chọn không xóa tiện nghi khỏi danh mục.</p><strong>Danh mục tiện nghi</strong><p class="aurel-amenity-status">Tiện nghi mới được lưu vào danh mục ngay và có thể dùng cho các khách sạn khác.</p><div class="aurel-amenity-tools"><input data-amenity-search type="search" placeholder="Tìm trong danh mục tiện nghi" aria-label="Tìm tiện nghi"></div><div class="aurel-amenity-tools"><input data-amenity-name maxlength="100" placeholder="Tên tiện nghi mới" aria-label="Tên tiện nghi mới"><button type="button" data-amenity-add>Thêm tiện nghi</button></div><p class="aurel-amenity-status" data-amenity-status role="status" aria-live="polite"></p><button type="button" data-amenity-retry hidden>Tải lại danh mục</button><div class="aurel-amenity-options" data-amenity-options></div></fieldset>`;
        this.root.querySelector('[data-amenity-search]').addEventListener('input',()=>this.render());
        this.root.querySelector('[data-amenity-name]').addEventListener('keydown',event=>{if(event.key==='Enter'){event.preventDefault();this.add();}});
        this.root.addEventListener('change',event=>{if(event.target.matches('[data-amenity-check]')){event.target.checked?this.selected.add(event.target.value):this.selected.delete(event.target.value);this.render();}});
        this.root.addEventListener('click',event=>{
            if(event.target.closest('[data-amenity-add]'))this.add();
            if(event.target.closest('[data-amenity-retry]'))this.loadPromise=this.load();
            const remove=event.target.closest('[data-amenity-remove]');
            if(remove){this.selected.delete(remove.dataset.amenityRemove);this.render();}
        });this.render();this.loadPromise=this.load();
    }
    values(){return [...this.selected];}
    setSaving(saving){this.saving=saving;this.updateBusy();}
    updateBusy(){this.root.querySelector('fieldset').disabled=this.saving||this.loading||this.adding;}
    status(message,error=false){const status=this.root.querySelector('[data-amenity-status]');status.textContent=message;status.dataset.error=String(error);}
    async load(){
        this.loading=true;this.updateBusy();this.status('Đang tải danh mục tiện nghi…');
        this.root.querySelector('[data-amenity-retry]').hidden=true;
        try{
            const response=await adminApi('/amenities');
            const data=await response.json();
            if(!response.ok)throw new Error(data.message||'Không tải được danh mục tiện nghi.');
            if(!Array.isArray(data.amenities)||data.amenities.some(option=>typeof option.key!=='string'||typeof option.label!=='string'))throw new Error('Danh mục tiện nghi không hợp lệ.');
            this.options=data.amenities;
            this.selected=new Set(this.values().map(key=>this.options.find(option=>AdminAmenityEditor.normalize(option.key).toLocaleLowerCase('vi')===AdminAmenityEditor.normalize(key).toLocaleLowerCase('vi'))?.key||key));
            for(const key of this.selected)if(!this.options.some(option=>option.key===key))this.options.push({key,label:key});
            this.catalogReady=true;this.render();this.status('');return true;
        }catch(error){
            this.catalogReady=false;this.status(error.message||'Không tải được danh mục tiện nghi. Vui lòng thử lại.',true);
            this.root.querySelector('[data-amenity-retry]').hidden=false;return false;
        }finally{this.loading=false;this.updateBusy();}
    }
    async readyForSave(){
        await this.loadPromise;
        if(this.addPromise&&!await this.addPromise)return false;
        if(!this.catalogReady){this.status('Vui lòng tải lại danh mục tiện nghi trước khi lưu.',true);return false;}
        return true;
    }
    add(){
        if(this.adding||this.loading||this.saving)return;
        const input=this.root.querySelector('[data-amenity-name]'),name=AdminAmenityEditor.normalize(input.value),identity=name.toLocaleLowerCase('vi');
        let error=!this.catalogReady?'Vui lòng tải lại danh mục tiện nghi.':!name?'Vui lòng nhập tên tiện nghi.':Array.from(name).length>100?'Tên tiện nghi tối đa 100 ký tự.':this.options.some(option=>AdminAmenityEditor.normalize(option.key).toLocaleLowerCase('vi')===identity)?'Tiện nghi đã có trong danh mục. Vui lòng tìm và chọn tiện nghi này.':this.selected.size>=100?'Tối đa 100 tiện nghi.':'';
        this.status(error||'Đang lưu tiện nghi vào danh mục…',Boolean(error));
        if(error)return;
        this.adding=true;this.updateBusy();
        this.addPromise=this.create(name,input);
        return this.addPromise;
    }
    async create(name,input){
        try{
            const response=await adminApi('/amenities',{method:'POST',body:JSON.stringify({name})});
            const data=await response.json();
            if(!response.ok)throw new Error(Object.values(data.errors||{}).flat().join(' ')||data.message||'Không thể lưu tiện nghi.');
            if(typeof data.amenity?.key!=='string'||typeof data.amenity?.label!=='string')throw new Error('Phản hồi lưu tiện nghi không hợp lệ.');
            this.options.push(data.amenity);this.selected.add(data.amenity.key);input.value='';
            this.root.querySelector('[data-amenity-search]').value='';this.render();
            this.status('Đã lưu tiện nghi vào danh mục. Bấm Lưu để gán cho khách sạn này.');return true;
        }catch(error){this.status(error.message||'Không thể lưu tiện nghi. Vui lòng thử lại.',true);return false;}
        finally{this.adding=false;this.addPromise=null;this.updateBusy();input.focus();}
    }
    render(){
        const esc=AdminAmenityEditor.escape,term=this.root.querySelector('[data-amenity-search]').value.replace(/\s+/gu,' ').trim().toLocaleLowerCase('vi');
        const visible=this.options.filter(option=>(option.label+' '+option.key).toLocaleLowerCase('vi').includes(term));
        this.root.querySelector('[data-amenity-options]').innerHTML=visible.map(option=>`<div class="aurel-amenity-option"><label><input type="checkbox" data-amenity-check value="${esc(option.key)}" ${this.selected.has(option.key)?'checked':''}>${AdminAmenityEditor.icon(option.key)}<span>${esc(option.label)}</span></label></div>`).join('')||'<p class="aurel-amenity-status">Không tìm thấy tiện nghi.</p>';
        this.root.querySelector('[data-amenity-selected]').innerHTML=this.values().map(key=>`<button type="button" data-amenity-remove="${esc(key)}" aria-label="Bỏ chọn ${esc(key)}">${esc(this.options.find(option=>option.key===key)?.label||key)} ×</button>`).join('')||'<span class="aurel-amenity-status">Chưa chọn tiện nghi.</span>';
    }
};
</script>
@endpush
